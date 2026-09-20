<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use kintai\Core\Exceptions\PlanLimitExceededException;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Services\LicenseClientService;
use kintai\Core\Services\PlanLimitService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PlanLimitServiceTest extends TestCase
{
    private StoreRepositoryInterface&MockObject $stores;
    private UserRepositoryInterface&MockObject $users;
    private PlanLimitService $service;

    protected function setUp(): void
    {
        $this->stores  = $this->createMock(StoreRepositoryInterface::class);
        $this->users   = $this->createMock(UserRepositoryInterface::class);
        $this->service = $this->makeService();
    }

    /** Aucune clé de licence enregistrée (mock AppSettingsRepositoryInterface non stubbé -> get() = null) : isPaidPlanActive() reste false, donc le plan gratuit s'applique. */
    private function makeService(): PlanLimitService
    {
        $appSettings = $this->createMock(AppSettingsRepositoryInterface::class);
        $license = new LicenseClientService($appSettings, ['base_url' => '', 'api_key' => '', 'grace_period_days' => 14]);

        return new PlanLimitService($this->stores, $this->users, $license);
    }

    public function testMaxActiveBundlesIsFourOnFreePlan(): void
    {
        $this->assertSame(4, $this->service->maxActiveBundles());
    }

    public function testMaxStoresAndEmployeesOnFreePlan(): void
    {
        $this->assertSame(1, $this->service->maxStores());
        $this->assertSame(15, $this->service->maxEmployees());
    }

    public function testCurrentCountsDelegateToRepositories(): void
    {
        $this->stores->method('countActive')->willReturn(1);
        $this->users->method('countActive')->willReturn(7);

        $this->assertSame(1, $this->service->currentStoreCount());
        $this->assertSame(7, $this->service->currentEmployeeCount());
    }

    public function testAssertCanCreateStoreAllowsWhenBelowLimit(): void
    {
        $this->stores->method('countActive')->willReturn(0);
        $this->service->assertCanCreateStore();
        $this->addToAssertionCount(1);
    }

    public function testAssertCanCreateStoreThrowsWhenLimitReached(): void
    {
        $this->stores->method('countActive')->willReturn(1);

        $this->expectException(PlanLimitExceededException::class);
        $this->service->assertCanCreateStore();
    }

    public function testAssertCanCreateEmployeeAllowsWhenBelowLimit(): void
    {
        $this->users->method('countActive')->willReturn(14);
        $this->service->assertCanCreateEmployee();
        $this->addToAssertionCount(1);
    }

    public function testAssertCanCreateEmployeeThrowsWhenLimitReached(): void
    {
        $this->users->method('countActive')->willReturn(15);

        $this->expectException(PlanLimitExceededException::class);
        $this->service->assertCanCreateEmployee();
    }

    // -------------------------------------------------------------------------
    // Plan payant (licence active) : toutes les limites sont levées
    // -------------------------------------------------------------------------

    private function makePaidService(): PlanLimitService
    {
        $store = [];
        $appSettings = $this->createMock(AppSettingsRepositoryInterface::class);
        $appSettings->method('get')->willReturnCallback(function (string $k) use (&$store) {
            return $store[$k] ?? null;
        });
        $appSettings->method('set')->willReturnCallback(function (string $k, string $v) use (&$store): void {
            $store[$k] = $v;
        });

        $license = new LicenseClientService(
            $appSettings,
            ['base_url' => 'https://license.test/api/v1', 'api_key' => 'kintai-key', 'grace_period_days' => 14],
            transport: fn(): string => json_encode(['valid' => true, 'status' => 'active']),
        );
        $license->activate('KEY-1');

        return new PlanLimitService($this->stores, $this->users, $license);
    }

    public function testMaxActiveBundlesIsUnlimitedOnPaidPlan(): void
    {
        $this->assertNull($this->makePaidService()->maxActiveBundles());
    }

    public function testMaxStoresAndEmployeesAreUnlimitedOnPaidPlan(): void
    {
        $service = $this->makePaidService();

        $this->assertNull($service->maxStores());
        $this->assertNull($service->maxEmployees());
    }

    public function testAssertCanCreateStoreNeverThrowsOnPaidPlan(): void
    {
        $this->stores->method('countActive')->willReturn(99);
        $this->makePaidService()->assertCanCreateStore();
        $this->addToAssertionCount(1);
    }

    public function testAssertCanCreateEmployeeNeverThrowsOnPaidPlan(): void
    {
        $this->users->method('countActive')->willReturn(999);
        $this->makePaidService()->assertCanCreateEmployee();
        $this->addToAssertionCount(1);
    }
}
