<?php

declare(strict_types=1);

namespace kintai\Tests\Integration;

use kintai\Core\Container;
use kintai\Core\Router;
use kintai\Core\Services\SetupWizard;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

/** Chaque écran de l'assistant se rend sans erreur, avec la barre de progression et le bon formulaire. */
final class SetupWizardRenderTest extends TestCase
{
    private ViewRenderer $view;

    protected function setUp(): void
    {
        $this->view = new ViewRenderer(BASE_PATH . '/src/UI/View');
        $router = new Router();
        foreach ([
            'admin.setup', 'admin.setup.license', 'admin.setup.bundles', 'admin.setup.step', 'admin.setup.finish', 'admin.owner_settings',
            'admin.stores', 'admin.mail_test', 'admin.notifications_settings', 'admin.backup', 'admin.bundles.market', 'admin.license',
        ] as $i => $name) {
            $router->post('/r' . $i . ($name === 'admin.setup.step' ? '/{step}' : ''), [\stdClass::class, 'x'], name: $name);
        }
        Container::getInstance()->instance(Router::class, $router);
    }

    /** @param array<string, mixed> $extra */
    private function render(string $step, array $extra = []): string
    {
        $screen = $step === 'bundles_result' ? 'bundles' : $step;
        return $this->view->renderPartial('system.setup', $extra + [
            'BASE_URL'  => '',
            'title'     => 'Setup',
            'step'      => $step,
            'steps'     => SetupWizard::STEPS,
            'progress'  => ['organisation' => 'done', 'store' => 'skipped', 'mail' => null, 'notifications' => null, 'operations' => null, 'bundles' => null, 'license' => null],
            'position'  => SetupWizard::position($screen),
            'total'     => count(SetupWizard::STEPS),
            'previous'  => SetupWizard::previousStep($screen),
            'next'      => SetupWizard::nextStep($screen),
            'skippable' => in_array($screen, SetupWizard::SKIPPABLE, true),
            'error'     => null,
            'errors'    => [],
        ]);
    }

    public function testProgressBarShowsEveryStepWithItsState(): void
    {
        $html = $this->render('mail', ['stepData' => [
            'driver' => 'native', 'configured' => false, 'from' => '', 'from_name' => 'Kintai', 'host' => '', 'port' => '465', 'username' => '',
            'has_password' => false, 'encryption' => 'ssl', 'encryptions' => ['ssl', 'tls', 'none'],
        ]]);

        $this->assertSame(8, substr_count($html, 'setup-steps__item"') + substr_count($html, 'setup-steps__item '));
        $this->assertStringContainsString('setup-steps__item setup-steps__item--done', $html);
        $this->assertStringContainsString('setup-steps__item--skipped', $html);
        $this->assertStringContainsString('aria-current="step"', $html);
        $this->assertStringContainsString('name="driver"', $html);
        $this->assertStringContainsString('name="action" value="test"', $html);
        $this->assertStringContainsString('name="skip" value="1"', $html, 'chaque étape facultative peut être passée');
    }

    public function testEveryFormStepRenders(): void
    {
        $data = [
            'organisation' => ['name' => '', 'support_email' => '', 'public_url' => '', 'public_url_forced' => false, 'public_url_suggestion' => 'http://127.0.0.7'],
            'store' => ['existing' => ['Paris'], 'timezones' => ['UTC', 'Asia/Tokyo'], 'currencies' => ['EUR', 'JPY'], 'defaults' => ['timezone' => 'UTC', 'currency' => 'EUR', 'type' => 'retail']],
            'notifications' => ['status' => [
                'web_push' => ['available' => true, 'has_keys' => true, 'missing_extensions' => [], 'subscriptions' => 0],
                'email' => ['available' => false, 'configured' => false, 'driver' => 'native', 'has_public_url' => false],
                'mobile' => ['available' => false, 'enabled' => false, 'has_project' => false, 'has_credentials' => false],
            ]],
            'operations' => ['backup_auto' => true, 'backup_keep' => 10, 'update_channel' => 'release', 'channels' => ['release', 'beta', 'alpha'],
                'cron_command' => 'php /srv/scripts/cron.php', 'cron_url' => 'https://exemple.fr/cron/run/backup', 'cron_token_command' => 'php scripts/create-cron-token.php'],
        ];
        $needle = ['organisation' => 'name="support_email"', 'store' => 'name="timezone"', 'notifications' => 'name="send_test"', 'operations' => 'name="update_channel"'];

        foreach ($data as $step => $stepData) {
            $html = $this->render($step, ['stepData' => $stepData]);
            $this->assertStringContainsString($needle[$step], $html, $step);
            $this->assertStringContainsString('name="_token"', $html, $step);
        }
    }

    public function testErrorsAreShownAndTheLicenseAndRecapRender(): void
    {
        $html = $this->render('license', ['licenseConfigured' => true, 'errors' => ['Phrase d\'erreur <b>']]);
        $this->assertStringContainsString('Phrase d&#039;erreur &lt;b&gt;', $html);
        $this->assertStringContainsString('name="license_key"', $html);

        $recap = $this->render('recap', ['summaries' => ['organisation' => 'Ma Boutique', 'store' => null, 'mail' => null, 'notifications' => 'web push', 'operations' => 'backup ✓ · release']]);
        $this->assertStringContainsString('Ma Boutique', $recap);
        $this->assertStringContainsString('setup_finish', $recap, 'le bouton de fin existe');
        $this->assertSame(7, substr_count($recap, '<tr>'), 'une ligne par étape sauf le récapitulatif');
    }

    public function testTheBundlesResultOffersToContinue(): void
    {
        $html = $this->render('bundles_result', ['result' => ['installed' => ['timeoff'], 'inactive' => [], 'failed' => ['messaging' => 'réseau']]]);

        // Les textes ne sont pas traduits ici (clés affichées) ; la liste des échecs, elle, vient du bilan.
        $this->assertStringContainsString('setup_done_installed', $html);
        $this->assertStringContainsString('<strong>messaging</strong>', $html);
        $this->assertStringContainsString('step=license', $html, 'la suite est la licence');
    }
}
