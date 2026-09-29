<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Api\V1;

use kintai\Core\Exceptions\PlanLimitExceededException;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use kintai\Core\Services\StoreServiceInterface;
use kintai\UI\Controller\Api\V1\StoreController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StoreControllerTest extends TestCase
{
    private StoreRepositoryInterface&MockObject $stores;
    private StoreServiceInterface&MockObject $storeService;
    private StoreController $controller;

    protected function setUp(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeService = $this->createMock(StoreServiceInterface::class);
        $this->controller = new StoreController($this->stores, $this->storeService, new AuditLogger());
    }

    protected function tearDown(): void
    {
        Log::reset();
    }

    private function makeJsonRequest(array $body): Request
    {
        $req = new Request();
        $ref = new \ReflectionProperty(Request::class, 'jsonBody');
        $ref->setAccessible(true);
        $ref->setValue($req, $body);
        return $req;
    }

    /** POST /api/v1/stores délègue à StoreService::createStore() (et non plus au repository directement), pour que les règles métier (validation, quota du plan) s'appliquent aussi côté API. */
    public function testStoreDelegatesToStoreService(): void
    {
        $this->storeService->expects($this->once())
            ->method('createStore')
            ->with(['code' => 'ST01', 'name' => 'Store A'])
            ->willReturn(['id' => 1, 'code' => 'ST01']);

        $response = $this->controller->store($this->makeJsonRequest(['code' => 'ST01', 'name' => 'Store A']));

        $this->assertSame(201, $response->status());
    }

    public function testStorePropagatesPlanLimitException(): void
    {
        $this->storeService->method('createStore')->willThrowException(new PlanLimitExceededException('plan_limit_stores'));

        $this->expectException(PlanLimitExceededException::class);
        $this->controller->store($this->makeJsonRequest(['code' => 'ST01', 'name' => 'Store A']));
    }
}
