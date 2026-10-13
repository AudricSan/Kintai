<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Api\V1;

use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Tests\Support\ApiOwnerAuth;
use kintai\Core\Exceptions\PlanLimitExceededException;
use kintai\Core\Auth\OwnerGuard;
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
use kintai\Core\Exceptions\ValidationException;
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
            ApiOwnerAuth::permissions($this),
            $this->createMock(StoreUserRepositoryInterface::class),
            new OwnerGuard(ApiOwnerAuth::permissions($this), $this->users),
        );
    }

    protected function tearDown(): void
    {
        Log::reset();
    }

    private function makeJsonRequest(array $body): Request
    {
        $req = ApiOwnerAuth::owner(new Request());
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

    public function testAnOwnerCannotDeleteThemselvesThroughTheApi(): void
    {
        $this->users->method('findById')->willReturn(['id' => ApiOwnerAuth::OWNER_ID, 'is_active' => 1, 'deleted_at' => null]);
        $this->users->expects($this->never())->method('delete');

        $req = ApiOwnerAuth::owner(new Request());
        $req->setRouteParams(['id' => (string) ApiOwnerAuth::OWNER_ID]);

        $this->expectException(\kintai\Core\Exceptions\ForbiddenException::class);
        $this->controller->destroy($req);
    }

    public function testAnOwnerCannotDeactivateThemselvesThroughTheApi(): void
    {
        $this->users->method('findById')->willReturn(['id' => ApiOwnerAuth::OWNER_ID, 'is_active' => 1, 'deleted_at' => null]);
        $this->users->expects($this->never())->method('save');

        $req = $this->makeJsonRequest(['is_active' => 0]);
        $req->setRouteParams(['id' => (string) ApiOwnerAuth::OWNER_ID]);

        $this->expectException(\kintai\Core\Exceptions\ForbiddenException::class);
        $this->controller->update($req);
    }

    public function testStoreThrowsWhenFreePlanEmployeeLimitReached(): void
    {
        $this->users->method('countActive')->willReturn(15);
        $this->users->expects($this->never())->method('save');

        $this->expectException(PlanLimitExceededException::class);
        $this->controller->store($this->makeJsonRequest(['email' => 'new@example.com']));
    }

    /** Audit M1 : couleur de compte non hexadécimale refusée en création comme en modification. */
    public function testStoreRejectsAnInvalidColor(): void
    {
        $this->users->method('countActive')->willReturn(1);
        $this->users->expects($this->never())->method('save');

        $this->expectException(ValidationException::class);
        $this->controller->store($this->makeJsonRequest(['email' => 'new@example.com', 'color' => 'red;position:fixed']));
    }

    public function testUpdateRejectsAnInvalidColor(): void
    {
        $this->users->method('findById')->willReturn(['id' => 4, 'email' => 'a@example.com']);
        $this->users->expects($this->never())->method('save');

        $req = $this->makeJsonRequest(['color' => 'url(javascript:x)']);
        $req->setRouteParams(['id' => '4']);

        $this->expectException(ValidationException::class);
        $this->controller->update($req);
    }
}
