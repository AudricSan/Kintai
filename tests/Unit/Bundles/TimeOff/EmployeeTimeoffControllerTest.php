<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\TimeOff;

require_once dirname(__DIR__, 3) . '/Fixtures/bundles/timeoff-1.0.0/src/Controllers/Web/EmployeeTimeoffController.php';

use kintai\Bundles\Installed\TimeOff\Controllers\Web\EmployeeTimeoffController;
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
            $this->createMock(\kintai\Core\Repositories\UserRepositoryInterface::class),
            $this->createMock(\kintai\Core\Services\NotificationService::class),
            new \kintai\Core\Auth\PermissionService(
                $this->createMock(\kintai\Core\Repositories\RoleAssignmentRepositoryInterface::class),
                $this->createMock(\kintai\Core\Repositories\RoleRepositoryInterface::class),
            ),
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

    /** Audit du 13/10/2026 : les dates étaient enregistrées telles quelles, sans aucun contrôle. */
    public function testStoreTimeoffRejectsInvalidDates(): void
    {
        $this->storeUsers->method('findByUser')->with(9)->willReturn([['store_id' => 1, 'user_id' => 9]]);
        $this->stores->method('getFeatures')->with(1)->willReturn([]);
        $this->timeoffRequests->expects($this->never())->method('save');

        $_POST = ['type' => 'sick', 'start_date' => 'pas-une-date', 'end_date' => ''];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $this->expectException(\kintai\Core\Exceptions\ValidationException::class);
        $this->controller->storeTimeoff($req);
    }

    public function testStoreTimeoffRejectsEndBeforeStart(): void
    {
        $this->storeUsers->method('findByUser')->with(9)->willReturn([['store_id' => 1, 'user_id' => 9]]);
        $this->stores->method('getFeatures')->with(1)->willReturn([]);
        $this->timeoffRequests->expects($this->never())->method('save');

        $_POST = ['type' => 'sick', 'start_date' => '2026-08-05', 'end_date' => '2026-08-01'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $this->expectException(\kintai\Core\Exceptions\ValidationException::class);
        $this->controller->storeTimeoff($req);
    }

    public function testStoreTimeoffUsesTheChosenStoreWhenMember(): void
    {
        $this->storeUsers->method('findByUser')->with(9)->willReturn([
            ['store_id' => 1, 'user_id' => 9], ['store_id' => 4, 'user_id' => 9],
        ]);
        $this->stores->method('getFeatures')->willReturn([]);

        $_POST = ['type' => 'sick', 'start_date' => '2026-08-01', 'store_id' => '4'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $this->timeoffRequests->expects($this->once())->method('save')->with($this->callback(
            fn(array $d) => $d['store_id'] === 4 && $d['end_date'] === '2026-08-01'
        ))->willReturn(['id' => 7]);

        $this->assertSame(302, $this->controller->storeTimeoff($req)->status());
    }

    public function testStoreTimeoffRefusesAStoreTheEmployeeDoesNotBelongTo(): void
    {
        $this->storeUsers->method('findByUser')->with(9)->willReturn([['store_id' => 1, 'user_id' => 9]]);
        $this->stores->method('getFeatures')->willReturn([]);
        $this->timeoffRequests->expects($this->never())->method('save');

        $_POST = ['type' => 'sick', 'start_date' => '2026-08-01', 'store_id' => '99'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $this->expectException(\kintai\Core\Exceptions\ValidationException::class);
        $this->controller->storeTimeoff($req);
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
