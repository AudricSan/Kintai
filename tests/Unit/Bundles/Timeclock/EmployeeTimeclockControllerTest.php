<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\Timeclock;

use kintai\Bundles\Timeclock\Controllers\Web\EmployeeTimeclockController;
use kintai\Core\Container;
use kintai\Core\Exceptions\ConflictException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\LogRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class EmployeeTimeclockControllerTest extends TestCase
{
    private TimeclockRepositoryInterface&MockObject $timeclocks;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private StoreRepositoryInterface&MockObject $stores;
    private LogRepositoryInterface&MockObject $logRepo;
    private EmployeeTimeclockController $controller;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-timeclock-views';
        $this->ensureViewFile($viewDir, 'employee-timeclock');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $view = new ViewRenderer(sys_get_temp_dir());
        $view->addNamespace('timeclock', $viewDir);

        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);

        $this->logRepo = $this->createMock(LogRepositoryInterface::class);
        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->logRepo);
        Log::setContainer($container);

        $this->controller = new EmployeeTimeclockController(
            $view,
            $this->timeclocks,
            $this->storeUsers,
            $this->stores,
            new AuditLogger(),
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
        Log::reset();
    }

    public function testTimeclockRendersWhenFeatureEnabled(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->method('findByUser')->with(1)->willReturn([['store_id' => 5]]);
        $this->timeclocks->method('findActiveByUser')->with(1)->willReturn(null);
        $this->timeclocks->method('findByUserAndDate')->willReturn([]);
        $this->timeclocks->method('findByUser')->with(1)->willReturn([]);

        $response = $this->controller->timeclock($req);

        $this->assertSame(200, $response->status());
    }

    public function testTimeclockRedirectsWhenFeatureDisabled(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->method('findByUser')->with(1)->willReturn([['store_id' => 5]]);
        $this->stores->method('getFeatures')->with(5)->willReturn(['shifts']);

        $response = $this->controller->timeclock($req);

        $this->assertSame(302, $response->status());
    }

    public function testClockInCreatesEntry(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->method('findByUser')->with(1)->willReturn([['store_id' => 5]]);
        $this->timeclocks->method('findActiveByUser')->with(1)->willReturn(null);
        $this->timeclocks->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['user_id'] === 1 && $data['store_id'] === 5 && $data['clock_out_time'] === null
        ))->willReturn(['id' => 10]);

        $response = $this->controller->clockIn($req);

        $this->assertSame(201, $response->status());
    }

    public function testClockInRejectsWhenAlreadyActive(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->method('findByUser')->with(1)->willReturn([['store_id' => 5]]);
        $this->timeclocks->method('findActiveByUser')->with(1)->willReturn(['id' => 9]);
        $this->timeclocks->expects($this->never())->method('save');

        $this->expectException(ConflictException::class);
        $this->controller->clockIn($req);
    }

    public function testClockOutComputesDuration(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->timeclocks->method('findActiveByUser')->with(1)->willReturn([
            'id' => 9, 'store_id' => 5, 'clock_in_time' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
        ]);
        $this->timeclocks->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['id'] === 9 && $data['duration_minutes'] >= 29 && $data['duration_minutes'] <= 31
        ))->willReturn(['id' => 9]);

        $response = $this->controller->clockOut($req);

        $this->assertSame(200, $response->status());
    }

    public function testClockOutThrowsWhenNoActiveEntry(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->timeclocks->method('findActiveByUser')->with(1)->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->controller->clockOut($req);
    }

    private function ensureViewFile(string $dir, string $view): void
    {
        $file = $dir . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        $parent = dirname($file);
        if (!is_dir($parent)) {
            mkdir($parent, 0777, true);
        }
        touch($file);
    }
}
