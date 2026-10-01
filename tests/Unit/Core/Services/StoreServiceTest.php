<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core\Services;

use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Repositories\LanguageRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Exceptions\PlanLimitExceededException;
use kintai\Core\Services\LicenseClientService;
use kintai\Core\Services\PlanLimitService;
use kintai\Core\Services\StoreService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StoreServiceTest extends TestCase
{
    private StoreRepositoryInterface&MockObject $stores;
    private StoreService $service;

    protected function setUp(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);

        $languages = $this->createMock(LanguageRepositoryInterface::class);
        $languages->method('findAllActive')->willReturn([['code' => 'en']]);

        $this->service = new StoreService(
            $this->stores,
            $this->createMock(StoreUserRepositoryInterface::class),
            $this->createMock(UserRepositoryInterface::class),
            $languages,
            new PlanLimitService(
                $this->stores,
                $this->createMock(UserRepositoryInterface::class),
                new LicenseClientService($this->createMock(AppSettingsRepositoryInterface::class), ['base_url' => '', 'api_key' => '']),
            ),
        );
    }

    /**
     * Les fonctionnalités activées par store ne vivent plus que dans la table
     * store_features (via saveFeatures) — la colonne stores.features historique
     * a été retirée pour éviter la désynchronisation entre les deux sources.
     */
    public function testUpdateStorePersistsFeaturesViaSaveFeaturesOnly(): void
    {
        $existingStore = ['id' => 1, 'code' => 'A', 'name' => 'Store A'];
        $this->stores->method('findById')->with(1)->willReturn($existingStore);

        $enabled = ['shifts', 'timeclock', 'timeoff', 'swaps', 'open_shifts', 'messages', 'daily_reports'];
        $data = ['_features' => $enabled];

        $this->stores->expects($this->once())->method('saveFeatures')->with(1, $enabled);
        $this->stores->expects($this->once())->method('save')->with(
            $this->callback(fn (array $storeData) => !array_key_exists('features', $storeData)),
        )->willReturn(['id' => 1]);

        $this->service->updateStore(1, $data);
    }

    public function testUpdateStoreWithoutFeaturesDoesNotCallSaveFeatures(): void
    {
        $existingStore = ['id' => 1, 'code' => 'A', 'name' => 'Store A'];
        $this->stores->method('findById')->with(1)->willReturn($existingStore);

        $this->stores->expects($this->never())->method('saveFeatures');
        $this->stores->expects($this->once())->method('save')->willReturn($existingStore);

        $this->service->updateStore(1, ['name' => 'Store A renamed']);
    }

    public function testCreateStoreDefaultsCurrencySymbolStyleToKanji(): void
    {
        $this->stores->expects($this->once())->method('save')->with(
            $this->callback(fn (array $data) => $data['currency_symbol_style'] === 'kanji'),
        )->willReturn(['id' => 1]);

        $this->service->createStore(['code' => 'ST01', 'name' => 'Store A']);
    }

    public function testCreateStorePersistsInternationalCurrencySymbolStyle(): void
    {
        $this->stores->expects($this->once())->method('save')->with(
            $this->callback(fn (array $data) => $data['currency_symbol_style'] === 'international'),
        )->willReturn(['id' => 1]);

        $this->service->createStore(['code' => 'ST01', 'name' => 'Store A', 'currency_symbol_style' => 'international']);
    }

    public function testCreateStoreThrowsWhenFreePlanStoreLimitReached(): void
    {
        $this->stores->method('countActive')->willReturn(1);
        $this->stores->expects($this->never())->method('save');

        $this->expectException(PlanLimitExceededException::class);
        $this->service->createStore(['code' => 'ST01', 'name' => 'Store A']);
    }

    public function testUpdateStoreKeepsExistingCurrencySymbolStyleWhenNotProvided(): void
    {
        $existingStore = ['id' => 1, 'code' => 'A', 'name' => 'Store A', 'currency_symbol_style' => 'international'];
        $this->stores->method('findById')->with(1)->willReturn($existingStore);

        $this->stores->expects($this->once())->method('save')->with(
            $this->callback(fn (array $data) => $data['currency_symbol_style'] === 'international'),
        )->willReturn($existingStore);

        $this->service->updateStore(1, ['name' => 'Store A renamed']);
    }

    /** Audit du 01/10/2026 : la devise n'était pas contrôlée en modification et s'affichait telle quelle. */
    public function testUpdateStoreRejectsAForgedCurrency(): void
    {
        $this->stores->method('findById')->with(1)->willReturn(['id' => 1, 'code' => 'A', 'name' => 'Store A']);
        $this->stores->expects($this->never())->method('save');

        $this->expectException(\kintai\Core\Exceptions\ValidationException::class);
        $this->service->updateStore(1, ['name' => 'Store A', 'currency' => '<a href=//evil>SESSION</a>']);
    }

    public function testUpdateStoreStillAcceptsExistingFreeTypeAndLocale(): void
    {
        // Données réelles : type libre (コンビニ) et langue hors liste (JA) ne doivent pas bloquer une modification.
        $existingStore = ['id' => 1, 'code' => '58182', 'name' => '航空公園東口店', 'type' => 'コンビニ', 'locale' => 'JA', 'currency' => 'JPY'];
        $this->stores->method('findById')->with(1)->willReturn($existingStore);
        $this->stores->expects($this->once())->method('save')->willReturn($existingStore);

        $this->service->updateStore(1, ['name' => '航空公園東口店', 'type' => 'コンビニ', 'locale' => 'JA', 'currency' => 'JPY']);
    }

    public function testUnknownCurrencyCodeCannotInjectMarkup(): void
    {
        $this->assertSame('AHREFEVILSESSIONA', currency_symbol('<a href=//evil>SESSION</a>'));
        $this->assertSame('円', currency_symbol('JPY'));
        $this->assertStringNotContainsString('<', format_currency(12.5, '<b>x</b>'));
    }
}
