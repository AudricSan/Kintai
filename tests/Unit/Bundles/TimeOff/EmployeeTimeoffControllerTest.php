<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\TimeOff;

use kintai\Bundles\TimeOff\Controllers\Web\EmployeeTimeoffController;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class EmployeeTimeoffControllerTest extends TestCase
{
    private TimeoffRequestRepositoryInterface&MockObject $timeoffRequests;
    private StoreRepositoryInterface&MockObject $stores;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private EmployeeTimeoffController $controller;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-timeoff-views';
        $this->ensureViewFile($viewDir, 'employee-timeoff');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $view = new ViewRenderer(sys_get_temp_dir());
        $view->addNamespace('timeoff', $viewDir);

        $this->timeoffRequests = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);

        $this->controller = new EmployeeTimeoffController(
            $view,
            $this->timeoffRequests,
            $this->stores,
            $this->storeUsers,
            new AuditLogger(),
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
    }

    public function testTimeoffRendersListForEmployee(): void
    {
        $this->storeUsers->method('findByUser')->with(9)->willReturn([['store_id' => 1, 'user_id' => 9]]);
        $this->stores->method('getFeatures')->with(1)->willReturn([]);
        $this->timeoffRequests->method('findByUser')->with(9)->willReturn([]);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $response = $this->controller->timeoff($req);

        $this->assertSame(200, $response->status());
    }

    public function testTimeoffRedirectsWhenFeatureDisabledForStore(): void
    {
        $this->storeUsers->method('findByUser')->with(9)->willReturn([['store_id' => 1, 'user_id' => 9]]);
        $this->stores->method('getFeatures')->with(1)->willReturn(['shifts', 'timeclock']);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $response = $this->controller->timeoff($req);

        $this->assertSame(302, $response->status());
    }

    public function testStoreTimeoffCreatesPendingRequest(): void
    {
        $this->storeUsers->method('findByUser')->with(9)->willReturn([['store_id' => 1, 'user_id' => 9]]);
        $this->stores->method('getFeatures')->with(1)->willReturn([]);

        $_POST = ['type' => 'sick', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02', 'reason' => 'Grippe'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $captured = null;
        $this->timeoffRequests->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d + ['id' => 7];
        });

        $response = $this->controller->storeTimeoff($req);

        $this->assertSame(302, $response->status());
        $this->assertNotNull($captured);
        $this->assertSame('pending', $captured['status']);
        $this->assertSame(9, $captured['user_id']);
        $this->assertSame(1, $captured['store_id']);
    }

    public function testCancelTimeoffOnlyAllowsOwnerOfPendingRequest(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);
        $req->setRouteParams(['id' => '5']);

        $this->timeoffRequests->method('findById')->with(5)->willReturn([
            'id' => 5, 'user_id' => 9, 'status' => 'pending',
        ]);
        $this->timeoffRequests->expects($this->once())->method('save')->willReturnCallback(fn(array $d) => $d);

        $response = $this->controller->cancelTimeoff($req);

        $this->assertSame(302, $response->status());
    }

    public function testCancelTimeoffForbiddenForAnotherUsersRequest(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);
        $req->setRouteParams(['id' => '5']);

        $this->timeoffRequests->method('findById')->with(5)->willReturn([
            'id' => 5, 'user_id' => 42, 'status' => 'pending',
        ]);

        $this->expectException(ForbiddenException::class);
        $this->controller->cancelTimeoff($req);
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
