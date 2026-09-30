<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controllers;

use kintai\Core\Container;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Repositories\LogRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AppSettingsService;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use kintai\Core\Services\PublicUrlResolver;
use kintai\UI\Controller\Web\System\OwnerSettingsController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

final class OwnerSettingsControllerTest extends TestCase
{
    /** Snapshot de app_settings vu par le test courant — modifié en place par setMany(). */
    private array $stored = [];

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/system';
        $layoutDir = sys_get_temp_dir() . '/layout';
        foreach ([$viewDir, $layoutDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }
        touch($viewDir . '/owner-settings.php');
        touch($layoutDir . '/app.php');

        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->createMock(LogRepositoryInterface::class));
        Log::setContainer($container);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        Log::reset();
    }

    /**
     * Construit un contrôleur dont l'AppSettingsService voit $initialStored comme état
     * initial — doit être appelé APRÈS avoir préparé $this->stored, car AppSettingsService
     * met en cache repo->all() une seule fois, à la construction.
     */
    private function makeController(array $initialStored = [], string $envUrl = ''): OwnerSettingsController
    {
        $this->stored = $initialStored;

        $repo = $this->createMock(AppSettingsRepositoryInterface::class);
        $repo->method('all')->willReturnCallback(fn() => $this->stored);
        $repo->method('setMany')->willReturnCallback(function (array $settings): void {
            foreach ($settings as $k => $v) {
                $this->stored[(string) $k] = (string) $v;
            }
        });

        $view = new ViewRenderer(sys_get_temp_dir());

        $settings = new AppSettingsService($repo);

        return new OwnerSettingsController($view, $settings, new AuditLogger(), new PublicUrlResolver($settings, $envUrl));
    }

    public function testShowRendersWithDefaultFoxyColors(): void
    {
        $controller = $this->makeController();

        $response = $controller->show(new Request());

        $this->assertSame(200, $response->status());
    }

    public function testSaveStoresACustomLightColor(): void
    {
        $controller = $this->makeController();
        $_POST = ['app_success_color' => '#00FF00'];

        $controller->save(new Request());

        $this->assertSame('#00ff00', $this->stored['app_success_color']);
    }

    public function testSaveFallsBackToPreviousValueOnInvalidHex(): void
    {
        $controller = $this->makeController(['app_danger_color' => '#123456']);
        $_POST = ['app_danger_color' => 'not-a-color'];

        $controller->save(new Request());

        $this->assertSame('#123456', $this->stored['app_danger_color']);
    }

    public function testSavePersistsDarkModeToggle(): void
    {
        $controller = $this->makeController();
        $_POST = ['app_theme_dark_mode' => 'manual'];

        $controller->save(new Request());

        $this->assertSame('manual', $this->stored['app_theme_dark_mode']);
    }

    public function testSaveRejectsUnknownDarkModeValue(): void
    {
        $controller = $this->makeController();
        $_POST = ['app_theme_dark_mode' => 'nightly'];

        $controller->save(new Request());

        $this->assertSame('auto', $this->stored['app_theme_dark_mode']);
    }

    public function testSaveStoresAManualDarkColor(): void
    {
        $controller = $this->makeController();
        $_POST = [
            'app_theme_dark_mode'    => 'manual',
            'app_primary_color_dark' => '#112233',
        ];

        $controller->save(new Request());

        $this->assertSame('#112233', $this->stored['app_primary_color_dark']);
    }

    public function testSaveClearsAnInvalidManualDarkColorToEmptyMeaningAuto(): void
    {
        $controller = $this->makeController(['app_primary_color_dark' => '#112233']);
        $_POST = ['app_primary_color_dark' => 'nope'];

        $controller->save(new Request());

        $this->assertSame('', $this->stored['app_primary_color_dark']);
    }

    public function testSaveDefaultsAccessLogEnabledToOffWhenCheckboxUnchecked(): void
    {
        $controller = $this->makeController(['access_log_enabled' => '1']);
        $_POST = [];

        $controller->save(new Request());

        $this->assertSame('0', $this->stored['access_log_enabled']);
    }

    public function testSavePersistsAccessLogEnabled(): void
    {
        $controller = $this->makeController();
        $_POST = ['access_log_enabled' => '1'];

        $controller->save(new Request());

        $this->assertSame('1', $this->stored['access_log_enabled']);
    }

    public function testSaveClampsLogRetentionDaysToRange(): void
    {
        $controller = $this->makeController();
        $_POST = ['log_retention_days' => '99999'];

        $controller->save(new Request());

        $this->assertSame('3650', $this->stored['log_retention_days']);
    }

    public function testSaveAllowsZeroLogRetentionDaysMeaningUnlimited(): void
    {
        $controller = $this->makeController();
        $_POST = ['log_retention_days' => '0'];

        $controller->save(new Request());

        $this->assertSame('0', $this->stored['log_retention_days']);
    }

    public function testSaveStoresANormalizedPublicUrl(): void
    {
        $controller = $this->makeController();
        $_POST = ['app_public_url' => 'HTTPS://Kintai.Example.com/app/'];

        $controller->save(new Request());

        $this->assertSame('https://kintai.example.com/app', $this->stored[PublicUrlResolver::SETTING_KEY]);
    }

    public function testSaveRefusesAnInvalidPublicUrlAndKeepsThePreviousOne(): void
    {
        // Un chemin seul (« /Kintai ») donnerait à nouveau des liens d'e-mail inutilisables : refusé.
        $controller = $this->makeController([PublicUrlResolver::SETTING_KEY => 'https://old.example.com']);
        $_POST = ['app_public_url' => '/Kintai', 'app_subtitle' => 'Changed'];

        $response = $controller->save(new Request());

        $this->assertSame('https://old.example.com', $this->stored[PublicUrlResolver::SETTING_KEY]);
        $this->assertArrayNotHasKey('app_subtitle', $this->stored, 'Rien ne doit être enregistré quand l\'URL est refusée.');
        $this->assertStringContainsString('error=public_url_invalid', $this->locationOf($response));
    }

    public function testSaveAcceptsAnEmptyPublicUrl(): void
    {
        $controller = $this->makeController([PublicUrlResolver::SETTING_KEY => 'https://old.example.com']);
        $_POST = ['app_public_url' => ''];

        $controller->save(new Request());

        $this->assertSame('', $this->stored[PublicUrlResolver::SETTING_KEY]);
    }

    private function locationOf(\kintai\Core\Response $response): string
    {
        $ref = new \ReflectionProperty($response, 'headers');

        return $ref->getValue($response)['Location'] ?? '';
    }

    public function testSaveRedirectsWithSuccessFlag(): void
    {
        $controller = $this->makeController();

        $response = $controller->save(new Request());

        $this->assertSame(302, $response->status());
    }
}
