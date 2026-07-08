<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core\Services;

use kintai\Core\Repositories\LanguageRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
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

        $this->service = new StoreService(
            $this->stores,
            $this->createMock(StoreUserRepositoryInterface::class),
            $this->createMock(UserRepositoryInterface::class),
            $this->createMock(LanguageRepositoryInterface::class),
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
}
