<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Container;
use kintai\Core\FeatureManager;
use kintai\Core\Repositories\LogRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use kintai\Core\Services\RoleAssignmentSyncService;
use kintai\Core\Services\StoreServiceInterface;
use kintai\Core\Services\StoreStatsServiceInterface;
use kintai\UI\Controller\Web\Staff\AdminStoreController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminStoreControllerTest extends TestCase
{
    private StoreRepositoryInterface&MockObject $stores;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private UserRepositoryInterface&MockObject $users;
    private StoreStatsServiceInterface&MockObject $storeStatsService;
    private LogRepositoryInterface&MockObject $logRepo;
    private RoleRepositoryInterface&MockObject $roles;
    private RoleAssignmentRepositoryInterface&MockObject $roleAssignments;
    private AdminStoreController $controller;

    protected function setUp(): void
    {
        $this->ensureViewFile('staff.stores');
        $this->ensureViewFile('staff.stores-form');
        $this->ensureViewFile('staff.employee-stats');
        $this->ensureViewFile('staff.employee-payslip');
        $this->ensureViewFile('layout.app');
        $view = new ViewRenderer(sys_get_temp_dir());
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->storeStatsService = $this->createMock(StoreStatsServiceInterface::class);
        $this->roles = $this->createMock(RoleRepositoryInterface::class);
        $this->roleAssignments = $this->createMock(RoleAssignmentRepositoryInterface::class);

        // AuditLogger::log() délègue à Log::record() -> LogRepositoryInterface::record().
        $this->logRepo = $this->createMock(LogRepositoryInterface::class);
        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->logRepo);
        Log::setContainer($container);

        $this->controller = new AdminStoreController(
            $view,
            $this->users,
            $this->stores,
            $this->storeUsers,
            new AuditLogger(),
            $this->createMock(StoreServiceInterface::class),
            $this->storeStatsService,
            new FeatureManager(['messaging', 'daily-report', 'store-photos']),
            new RoleAssignmentSyncService($this->roles, $this->roleAssignments),
        );
    }

    public function testStoresRendersList(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->stores->method('findAll')->willReturn([]);

        $response = $this->controller->stores($req);

        $this->assertSame(200, $response->status());
    }

    public function testCreateStoreRendersForm(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $response = $this->controller->createStore($req);

        $this->assertSame(200, $response->status());
    }

    public function testEditStoreRendersForm(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setRouteParams(['id' => 1]);

        $store = [
            'id' => 1, 'name' => 'Test Store', 'currency' => 'JPY',
            'week_start_day' => 1, 'timezone' => 'Asia/Tokyo',
        ];
        $this->stores->method('findById')->with(1)->willReturn($store);
        $this->stores->method('getFeatures')->with(1)->willReturn(['shifts', 'timeclock']);
        $this->stores->method('getImportSettings')->with(1)->willReturn([]);
        $this->stores->method('getDeductionSettings')->with(1)->willReturn([]);
        $this->storeUsers->method('findByStore')->with(1)->willReturn([]);
        $this->users->method('findAll')->willReturn([]);

        $response = $this->controller->editStore($req);

        $this->assertSame(200, $response->status());
    }

    public function testEditStoreRendersFormWhenABundleIsDisabledInstanceWide(): void
    {
        $controller = new AdminStoreController(
            new ViewRenderer(sys_get_temp_dir()),
            $this->users,
            $this->stores,
            $this->storeUsers,
            new AuditLogger(),
            $this->createMock(StoreServiceInterface::class),
            $this->storeStatsService,
            new FeatureManager(['daily-report']), // messaging et store-photos désactivés
            new RoleAssignmentSyncService($this->roles, $this->roleAssignments),
        );

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setRouteParams(['id' => 1]);

        $store = ['id' => 1, 'name' => 'Test Store', 'currency' => 'JPY', 'week_start_day' => 1, 'timezone' => 'Asia/Tokyo'];
        $this->stores->method('findById')->with(1)->willReturn($store);
        $this->stores->method('getFeatures')->with(1)->willReturn(['shifts', 'timeclock', 'messages']);
        $this->stores->method('getImportSettings')->with(1)->willReturn([]);
        $this->stores->method('getDeductionSettings')->with(1)->willReturn([]);
        $this->storeUsers->method('findByStore')->with(1)->willReturn([]);
        $this->users->method('findAll')->willReturn([]);

        $response = $controller->editStore($req);

        $this->assertSame(200, $response->status());
    }

    public function testUpdateStorePersistsPhotosFeatureSlug(): void
    {
        $_POST = ['name' => 'Test Store', 'feature_photos' => '1'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1']);

        $this->stores->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Test Store']);

        $captured = null;
        $storeService = $this->createMock(StoreServiceInterface::class);
        $storeService->method('updateStore')->willReturnCallback(function (int $id, array $data) use (&$captured) {
            $captured = $data;
            return ['id' => $id] + $data;
        });

        $controller = new AdminStoreController(
            new ViewRenderer(sys_get_temp_dir()),
            $this->users,
            $this->stores,
            $this->storeUsers,
            new AuditLogger(),
            $storeService,
            $this->storeStatsService,
            new FeatureManager(['messaging', 'daily-report', 'store-photos']),
            new RoleAssignmentSyncService($this->roles, $this->roleAssignments),
        );

        $response = $controller->updateStore($req);

        $this->assertSame(302, $response->status());
        $this->assertNotNull($captured);
        $this->assertSame(['photos'], $captured['_features']);
    }

    public function testEmployeeStatsLogsConsultation(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'uid' => '5']);

        $this->stores->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A', 'currency' => 'JPY']);
        $this->users->method('findById')->with(5)->willReturn(['id' => 5, 'last_name' => 'Dupont', 'first_name' => 'Jean']);
        $this->storeUsers->method('findMembership')->with(1, 5)->willReturn(['id' => 1, 'store_id' => 1, 'user_id' => 5]);
        $this->storeStatsService->method('employeeStats')->willReturn([]);

        $this->logRepo->expects($this->once())->method('record')->with(
            $this->anything(), $this->anything(), $this->anything(),
            'employee_stats.viewed', 'user', 5,
            $this->anything(), $this->anything(), 1,
            $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(),
        );

        $response = $this->controller->employeeStats($req);

        $this->assertSame(200, $response->status());
    }

    public function testEmployeePayslipLogsConsultation(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'uid' => '5']);

        $this->stores->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A', 'currency' => 'JPY']);
        $this->users->method('findById')->with(5)->willReturn(['id' => 5, 'last_name' => 'Dupont', 'first_name' => 'Jean']);
        $this->storeUsers->method('findMembership')->with(1, 5)->willReturn(['id' => 1, 'store_id' => 1, 'user_id' => 5]);
        $this->storeStatsService->method('buildPayslipData')->willReturn([]);

        $this->logRepo->expects($this->once())->method('record')->with(
            $this->anything(), $this->anything(), $this->anything(),
            'payslip.viewed', 'user', 5,
            $this->anything(), $this->anything(), 1,
            $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(),
        );

        $response = $this->controller->employeePayslip($req);

        $this->assertSame(200, $response->status());
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        Log::reset();
    }

    private function ensureViewFile(string $view): void
    {
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        touch($file);
    }
}
