<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use kintai\Core\Container;
use kintai\Core\Repositories\JsonLanguageRepository;
use kintai\Core\Repositories\JsonTranslationRepository;
use kintai\Core\Services\IcalService;
use kintai\Core\Services\TranslationService;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 3));
}

/**
 * IcalService::build() doit produire un contenu traduit dans la langue de
 * l'employé propriétaire du flux (user['language']), avec repli sur la
 * locale du store puis 'fr' — même pattern que DailyReportMailService.
 *
 * build() utilise le helper global __() (voir src/helpers.php), qui résout
 * TranslationService via le Container plutôt que via l'instance injectée
 * directement : il faut donc lier cette même instance au Container le temps
 * du test (comme BackupControllerTest), sans quoi __() retombe silencieusement
 * sur la clé brute faute de TranslationService dans le container.
 */
final class IcalServiceTest extends TestCase
{
    private TranslationService $translations;
    private IcalService $service;

    protected function setUp(): void
    {
        $this->translations = new TranslationService(
            new JsonTranslationRepository(BASE_PATH . '/lang', BASE_PATH . '/src/Bundles'),
            new JsonLanguageRepository(BASE_PATH . '/lang'),
        );
        Container::getInstance()->instance(TranslationService::class, $this->translations);

        $this->service = new IcalService($this->translations);
    }

    protected function tearDown(): void
    {
        $instancesProp = new \ReflectionProperty(Container::class, 'instances');
        $instancesProp->setAccessible(true);
        $instances = $instancesProp->getValue(Container::getInstance());
        unset($instances[TranslationService::class]);
        $instancesProp->setValue(Container::getInstance(), $instances);
    }

    private function store(): array
    {
        return ['id' => 1, 'name' => 'Boutique Centrale', 'timezone' => 'Europe/Paris'];
    }

    private function timeoff(): array
    {
        return ['id' => 42, 'start_date' => '2026-09-20', 'end_date' => '2026-09-22'];
    }

    private function shift(): array
    {
        return [
            'id'            => 7,
            'shift_date'    => '2026-09-21',
            'start_time'    => '09:00',
            'end_time'      => '17:00',
            'pause_minutes' => 30,
        ];
    }

    public function testBuildProducesFrenchSummaryByDefault(): void
    {
        $user    = ['id' => 1, 'first_name' => 'Jean', 'last_name' => 'Dupont'];
        $content = $this->service->build([], [$this->timeoff()], $this->store(), $user);

        $this->assertStringContainsString('SUMMARY:Congé — Boutique Centrale', $content);
    }

    public function testBuildProducesEnglishSummaryWhenUserLanguageIsEnglish(): void
    {
        $user    = ['id' => 2, 'first_name' => 'John', 'last_name' => 'Smith', 'language' => 'en'];
        $content = $this->service->build([], [$this->timeoff()], $this->store(), $user);

        $this->assertStringContainsString('SUMMARY:Leave — Boutique Centrale', $content);
        $this->assertStringNotContainsString('Congé', $content);
    }

    public function testBuildProducesJapaneseSummaryWhenUserLanguageIsJapanese(): void
    {
        $user    = ['id' => 3, 'first_name' => '太郎', 'last_name' => '山田', 'language' => 'ja'];
        $content = $this->service->build([], [$this->timeoff()], $this->store(), $user);

        $this->assertStringContainsString('SUMMARY:休暇 — Boutique Centrale', $content);
    }

    public function testBuildFallsBackToStoreLocaleWhenUserHasNoLanguage(): void
    {
        $user    = ['id' => 4, 'first_name' => 'John', 'last_name' => 'Smith'];
        $store   = $this->store() + ['locale' => 'en'];
        $content = $this->service->build([], [$this->timeoff()], $store, $user);

        $this->assertStringContainsString('SUMMARY:Leave — Boutique Centrale', $content);
    }

    public function testBuildTranslatesPauseDescriptionInShiftEvent(): void
    {
        $user    = ['id' => 5, 'first_name' => 'John', 'last_name' => 'Smith', 'language' => 'en'];
        $content = $this->service->build([$this->shift()], [], $this->store(), $user, []);

        $this->assertStringContainsString('DESCRIPTION:Break: 30 min', $content);
    }

    public function testBuildRestoresPreviousLocaleAfterCompletion(): void
    {
        $this->translations->setLocale('fr');

        $user = ['id' => 6, 'first_name' => 'John', 'last_name' => 'Smith', 'language' => 'ja'];
        $this->service->build([], [$this->timeoff()], $this->store(), $user);

        $this->assertSame('fr', $this->translations->getLocale());
    }

    public function testBuildUsesShiftTypeFallbackWhenTypeUnknown(): void
    {
        $user    = ['id' => 7, 'first_name' => 'John', 'last_name' => 'Smith', 'language' => 'en'];
        $content = $this->service->build([$this->shift()], [], $this->store(), $user, []);

        $this->assertStringContainsString('SUMMARY:Shift — Boutique Centrale', $content);
    }
}
