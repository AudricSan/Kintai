<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use kintai\Core\Exceptions\PlanLimitExceededException;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
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
        $this->service = new PlanLimitService($this->stores, $this->users);
    }

    public function testMaxActiveBundlesIsFourOnFreePlan(): void
    {
        $this->assertSame(4, $this->service->maxActiveBundles());
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
}
