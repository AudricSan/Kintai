<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Api\V1;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Exceptions\ValidationException;
use kintai\Core\Repositories\DevicePushTokenRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\UI\Controller\Api\V1\PushTokenController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PushTokenControllerTest extends TestCase
{
    private DevicePushTokenRepositoryInterface&MockObject $tokens;
    private UserRepositoryInterface&MockObject $users;
    private PushTokenController $controller;

    protected function setUp(): void
    {
        $this->tokens = $this->createMock(DevicePushTokenRepositoryInterface::class);
        $this->users  = $this->createMock(UserRepositoryInterface::class);
        $this->controller = new PushTokenController($this->tokens, $this->users);
    }

    public function testStoreRegistersDeviceToken(): void
    {
        $this->users->method('findById')->with(20)->willReturn(['id' => 20]);
        $this->tokens->expects($this->once())->method('save')->with($this->callback(
            fn(array $d) => $d['user_id'] === 20 && $d['token'] === 'device-abc' && $d['platform'] === 'android'
        ))->willReturn(['id' => 1, 'user_id' => 20, 'token' => 'device-abc', 'platform' => 'android']);

        $req = $this->requestWithJson(['token' => 'device-abc', 'platform' => 'android'], ['user_id' => '20']);
        $response = $this->controller->store($req);

        $this->assertSame(201, $response->status());
    }

    public function testStoreRejectsMissingToken(): void
    {
        $this->users->method('findById')->with(20)->willReturn(['id' => 20]);

        $req = $this->requestWithJson(['platform' => 'ios'], ['user_id' => '20']);

        $this->expectException(ValidationException::class);
        $this->controller->store($req);
    }

    public function testStoreRejectsInvalidPlatform(): void
    {
        $this->users->method('findById')->with(20)->willReturn(['id' => 20]);

        $req = $this->requestWithJson(['token' => 'x', 'platform' => 'windows-phone'], ['user_id' => '20']);

        $this->expectException(ValidationException::class);
        $this->controller->store($req);
    }

    public function testStoreThrowsWhenUserNotFound(): void
    {
        $this->users->method('findById')->with(99)->willReturn(null);

        $req = $this->requestWithJson(['token' => 'x'], ['user_id' => '99']);

        $this->expectException(NotFoundException::class);
        $this->controller->store($req);
    }

    public function testDestroyUnregistersToken(): void
    {
        $this->users->method('findById')->with(20)->willReturn(['id' => 20]);
        $this->tokens->expects($this->once())->method('deleteByToken')->with('device-abc');

        $req = $this->requestWithJson(['token' => 'device-abc'], ['user_id' => '20']);
        $response = $this->controller->destroy($req);

        $this->assertSame(204, $response->status());
    }

    private function requestWithJson(array $json, array $routeParams = []): Request
    {
        $req = new Request();
        $ref = new \ReflectionProperty(Request::class, 'jsonBody');
        $ref->setAccessible(true);
        $ref->setValue($req, $json);
        $req->setRouteParams($routeParams);
        return $req;
    }
}
