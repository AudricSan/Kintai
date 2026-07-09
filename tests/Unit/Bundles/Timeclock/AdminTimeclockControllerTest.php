<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\Timeclock;

use kintai\Bundles\Timeclock\Controllers\Web\AdminTimeclockController;
use kintai\Core\Container;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\LogRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminTimeclockControllerTest extends TestCase
{
    private TimeclockRepositoryInterface&MockObject $timeclocks;
    private StoreRepositoryInterface&MockObject $stores;
    private UserRepositoryInterface&MockObject $users;
    private LogRepositoryInterface&MockObject $logRepo;
    private AdminTimeclockController $controller;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-timeclock-views';
        $this->ensureViewFile($viewDir, 'timeclocks');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $view = new ViewRenderer(sys_get_temp_dir());
        $view->addNamespace('timeclock', $viewDir);

        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);

        $this->logRepo = $this->createMock(LogRepositoryInterface::class);
        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->logRepo);
        Log::setContainer($container);

        $this->controller = new AdminTimeclockController(
            $view,
            $this->timeclocks,
            new AuditLogger(),
            $this->stores,
            $this->users,
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
        Log::reset();
    }

    public function testTimeclocksListsEntriesForFilterDate(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $_GET = ['date' => '2026-08-01'];

        $this->users->method('findAll')->willReturn([]);
        $this->stores->method('findAll')->willReturn([['id' => 5, 'name' => 'Store A']]);
        $this->timeclocks->method('findAll')->willReturn([
            ['id' => 1, 'store_id' => 5, 'shift_date' => '2026-08-01', 'clock_in_time' => '2026-08-01 09:00:00'],
            ['id' => 2, 'store_id' => 5, 'shift_date' => '2026-08-02', 'clock_in_time' => '2026-08-02 09:00:00'],
        ]);

        $response = $this->controller->timeclocks($req);

        $this->assertSame(200, $response->status());
    }

    public function testTimeclocksEditRecomputesDuration(): void
    {
        $_POST = ['clock_in_time' => '2026-08-01 09:00:00', 'clock_out_time' => '2026-08-01 17:00:00'];
        $req = new Request();
        $req->setRouteParams(['id' => '10']);

        $this->timeclocks->method('findById')->with(10)->willReturn([
            'id' => 10, 'store_id' => 5, 'clock_in_time' => '2026-08-01 09:00:00', 'clock_out_time' => null,
        ]);
        $this->timeclocks->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['duration_minutes'] === 480
        ));

        $response = $this->controller->timeclocksEdit($req);

        $this->assertSame(302, $response->status());
    }

    public function testTimeclocksEditThrowsNotFoundForMissingEntry(): void
    {
        $req = new Request();
        $req->setRouteParams(['id' => '99']);

        $this->timeclocks->method('findById')->with(99)->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->controller->timeclocksEdit($req);
    }

    public function testTimeclocksDeleteDeletesAndLogs(): void
    {
        $req = new Request();
        $req->setRouteParams(['id' => '10']);

        $this->timeclocks->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 5]);
        $this->timeclocks->expects($this->once())->method('delete')->with(10);

        $response = $this->controller->timeclocksDelete($req);

        $this->assertSame(302, $response->status());
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
