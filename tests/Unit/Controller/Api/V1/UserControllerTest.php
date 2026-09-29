<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Api\V1;

use kintai\Core\Exceptions\PlanLimitExceededException;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\LicenseClientService;
use kintai\Core\Services\Log;
use kintai\Core\Services\PlanLimitService;
use kintai\UI\Controller\Api\V1\UserController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UserControllerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $users;
    private UserController $controller;

    protected function setUp(): void
    {
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $stores = $this->createMock(StoreRepositoryInterface::class);
        $this->controller = new UserController(
            $this->users,
            new AuditLogger(),
            new PlanLimitService(
                $stores,
                $this->users,
                new LicenseClientService($this->createMock(AppSettingsRepositoryInterface::class), ['base_url' => '', 'api_key' => '']),
            ),
        );
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

    public function testStoreCreatesUserWhenBelowFreePlanLimit(): void
    {
        $this->users->method('countActive')->willReturn(14);
        $this->users->method('save')->willReturn(['id' => 1, 'email' => 'new@example.com']);

        $response = $this->controller->store($this->makeJsonRequest(['email' => 'new@example.com']));

        $this->assertSame(201, $response->status());
    }

    public function testStoreThrowsWhenFreePlanEmployeeLimitReached(): void
    {
        $this->users->method('countActive')->willReturn(15);
        $this->users->expects($this->never())->method('save');

        $this->expectException(PlanLimitExceededException::class);
        $this->controller->store($this->makeJsonRequest(['email' => 'new@example.com']));
    }
}
