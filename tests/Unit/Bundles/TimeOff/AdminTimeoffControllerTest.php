<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\TimeOff;

use kintai\Bundles\TimeOff\Controllers\Web\AdminTimeoffController;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\NotificationService;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminTimeoffControllerTest extends TestCase
{
    private TimeoffRequestRepositoryInterface&MockObject $timeoffRequests;
    private UserRepositoryInterface&MockObject $users;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private NotificationService&MockObject $notifs;
    private AdminTimeoffController $controller;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-timeoff-views';
        $this->ensureViewFile($viewDir, 'timeoff-form');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $view = new ViewRenderer(sys_get_temp_dir());
        $view->addNamespace('timeoff', $viewDir);

        $this->timeoffRequests = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);

        $this->controller = new AdminTimeoffController(
            $view,
            $this->timeoffRequests,
            new AuditLogger(),
            $this->notifs,
            $this->createMock(StoreRepositoryInterface::class),
            $this->users,
            $this->storeUsers,
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function testCreateTimeoffRendersFormForAdmin(): void
    {
        $this->users->method('findAll')->willReturn([
            ['id' => 1, 'last_name' => 'Dupont', 'first_name' => 'Jean'],
        ]);

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller->createTimeoff($req);

        $this->assertSame(200, $response->status());
    }

    public function testStoreTimeoffForEmployeeCreatesApprovedRequestVisibleOnCalendar(): void
    {
        $_POST = [
            'user_id'    => '5',
            'type'       => 'vacation',
            'start_date' => '2026-08-01',
            'end_date'   => '2026-08-03',
            'reason'     => 'Congés imposés',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->users->method('findById')->with(5)->willReturn(['id' => 5, 'display_name' => 'Alice']);
        $this->storeUsers->method('findByUser')->with(5)->willReturn([['store_id' => 3, 'user_id' => 5]]);

        $captured = null;
        $this->timeoffRequests->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d + ['id' => 99];
        });

        $this->notifs->expects($this->once())->method('notify')
            ->with(5, 'timeoff_approved', $this->isString(), 99);

        $response = $this->controller->storeTimeoffForEmployee($req);

        $this->assertSame(302, $response->status());
        $this->assertNotNull($captured);
        $this->assertSame('approved', $captured['status']);
        $this->assertSame(5, $captured['user_id']);
        $this->assertSame(3, $captured['store_id']);
        $this->assertSame('2026-08-01', $captured['start_date']);
        $this->assertSame('2026-08-03', $captured['end_date']);
    }

    public function testStoreTimeoffForEmployeeForbiddenWhenEmployeeNotManaged(): void
    {
        $_POST = [
            'user_id'    => '5',
            'type'       => 'vacation',
            'start_date' => '2026-08-01',
            'end_date'   => '2026-08-01',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', [1, 2]);
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->users->method('findById')->with(5)->willReturn(['id' => 5]);
        $this->storeUsers->method('findByStore')->willReturn([]);

        $this->expectException(ForbiddenException::class);
        $this->controller->storeTimeoffForEmployee($req);
    }

    public function testDeleteTimeoffRemovesRecordRegardlessOfStatus(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '42']);

        $this->timeoffRequests->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 3, 'user_id' => 5, 'status' => 'approved',
        ]);
        $this->timeoffRequests->expects($this->once())->method('delete')->with(42);

        $response = $this->controller->deleteTimeoff($req);

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
