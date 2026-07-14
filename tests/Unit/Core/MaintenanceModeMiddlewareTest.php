<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core;

use kintai\Core\Auth\AuthService;
use kintai\Core\Container;
use kintai\Core\Exceptions\ServiceUnavailableException;
use kintai\Core\Middleware\MaintenanceModeMiddleware;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AppSettingsService;
use PHPUnit\Framework\TestCase;

final class MaintenanceModeMiddlewareTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    protected function tearDown(): void
    {
        unset($_SESSION['auth_user_id']);
    }

    private function makeRequest(string $uri, string $method = 'GET'): Request
    {
        $_SERVER = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI'    => $uri,
            'SCRIPT_NAME'    => '/index.php',
        ];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];

        return new Request();
    }

    private function bindSettings(array $stored): void
    {
        $repo = $this->createStub(AppSettingsRepositoryInterface::class);
        $repo->method('all')->willReturn($stored);
        $this->container->instance(AppSettingsService::class, new AppSettingsService($repo));
    }

    private function bindAuth(?array $user): void
    {
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findById')->willReturn($user);
        $storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $stores = $this->createStub(StoreRepositoryInterface::class);
        $this->container->instance(AuthService::class, new AuthService($users, $storeUsers, $stores));

        if ($user !== null) {
            $_SESSION['auth_user_id'] = $user['id'];
        }
    }

    private function noop(): \Closure
    {
        return fn(Request $req) => Response::json(['ok' => true]);
    }

    public function testPassesThroughWhenMaintenanceDisabled(): void
    {
        $this->bindSettings(['maintenance_mode_enabled' => '0']);
        $this->bindAuth(null);
        $middleware = new MaintenanceModeMiddleware($this->container);

        $response = $middleware->handle($this->makeRequest('/'), $this->noop());

        $this->assertSame(200, $response->status());
    }

    public function testAdminAlwaysPassesThrough(): void
    {
        $this->bindSettings(['maintenance_mode_enabled' => '1']);
        $this->bindAuth(['id' => 1, 'is_admin' => 1]);
        $middleware = new MaintenanceModeMiddleware($this->container);

        $response = $middleware->handle($this->makeRequest('/admin/stores'), $this->noop());

        $this->assertSame(200, $response->status());
    }

    public function testAnonymousCanReachLoginPage(): void
    {
        $this->bindSettings(['maintenance_mode_enabled' => '1']);
        $this->bindAuth(null);
        $middleware = new MaintenanceModeMiddleware($this->container);

        $response = $middleware->handle($this->makeRequest('/login'), $this->noop());

        $this->assertSame(200, $response->status());
    }

    public function testAnonymousBlockedOnOtherPagesWithCustomMessage(): void
    {
        $this->bindSettings([
            'maintenance_mode_enabled' => '1',
            'maintenance_message'      => 'Retour dans 10 minutes.',
        ]);
        $this->bindAuth(null);
        $middleware = new MaintenanceModeMiddleware($this->container);

        $this->expectException(ServiceUnavailableException::class);
        $this->expectExceptionMessage('Retour dans 10 minutes.');
        $middleware->handle($this->makeRequest('/'), $this->noop());
    }

    public function testApiRequestsBypassMaintenance(): void
    {
        $this->bindSettings(['maintenance_mode_enabled' => '1']);
        $this->bindAuth(null);
        $middleware = new MaintenanceModeMiddleware($this->container);

        $response = $middleware->handle($this->makeRequest('/api/v1/ping'), $this->noop());

        $this->assertSame(200, $response->status());
    }

    public function testCronRequestsBypassMaintenance(): void
    {
        $this->bindSettings(['maintenance_mode_enabled' => '1']);
        $this->bindAuth(null);
        $middleware = new MaintenanceModeMiddleware($this->container);

        $response = $middleware->handle($this->makeRequest('/cron/run/backup'), $this->noop());

        $this->assertSame(200, $response->status());
    }

    public function testFailsOpenWhenSettingsResolutionThrows(): void
    {
        $this->container->bind(AppSettingsService::class, function (): AppSettingsService {
            throw new \RuntimeException('table absente');
        });
        $this->bindAuth(null);
        $middleware = new MaintenanceModeMiddleware($this->container);

        $response = $middleware->handle($this->makeRequest('/'), $this->noop());

        $this->assertSame(200, $response->status());
    }
}
