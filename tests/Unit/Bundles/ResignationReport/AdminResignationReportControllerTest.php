<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\ResignationReport;

use kintai\Bundles\ResignationReport\Controllers\Web\AdminResignationReportController;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Container;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\LogRepositoryInterface;
use kintai\Core\Repositories\ResignationReportRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminResignationReportControllerTest extends TestCase
{
    private ResignationReportRepositoryInterface&MockObject $resignationReports;
    private StoreRepositoryInterface&MockObject $stores;
    private UserRepositoryInterface&MockObject $users;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private LogRepositoryInterface&MockObject $logRepo;
    private RoleAssignmentRepositoryInterface&MockObject $assignments;
    private RoleRepositoryInterface&MockObject $roles;
    private AdminResignationReportController $controller;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-resignation-report-views';
        $this->ensureViewFile($viewDir, 'reports-resignation');
        $this->ensureViewFile($viewDir, 'reports-resignation-show');
        $this->ensureViewFile($viewDir, 'reports-resignation-form');
        $this->ensureViewFile($viewDir, 'reports-resignation-export-pdf');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $view = new ViewRenderer(sys_get_temp_dir());
        $view->addNamespace('resignation-report', $viewDir);

        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);

        $this->logRepo = $this->createMock(LogRepositoryInterface::class);
        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->logRepo);
        Log::setContainer($container);

        $this->assignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->roles = $this->createMock(RoleRepositoryInterface::class);

        $this->controller = new AdminResignationReportController(
            $view,
            $this->stores,
            $this->users,
            $this->resignationReports,
            $this->storeUsers,
            new AuditLogger(),
            new PermissionService($this->assignments, $this->roles),
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
        Log::reset();
    }

    // -------------------------------------------------------------------------
    // Export de la liste (item 5)
    // -------------------------------------------------------------------------

    public function testExportResignationReportsJsonReturnsData(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A']]);
        $this->resignationReports->method('findAll')->willReturn([
            ['id' => 10, 'store_id' => 1, 'employee_name' => 'Jean Dupont'],
        ]);

        $response = $this->controller->exportResignationReportsJson($req);
        $this->assertSame(200, $response->status());

        $data = json_decode($response->body(), true)['data'];
        $this->assertCount(1, $data);
        $this->assertSame('Jean Dupont', $data[0]['employee_name']);
    }

    public function testExportResignationReportsPdfReturnsHtmlPreview(): void
    {
        $_SERVER['PHP_SELF'] ??= '/index.php';

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A']]);
        $this->resignationReports->method('findAll')->willReturn([]);

        $response = $this->controller->exportResignationReportsPdf($req);

        $this->assertSame(200, $response->status());
        $this->assertStringNotContainsString('%PDF', $response->body());
    }

    public function testExportResignationReportsPdfDownloadReturnsPdfResponse(): void
    {
        $_SERVER['PHP_SELF'] ??= '/index.php';

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A']]);
        $this->resignationReports->method('findAll')->willReturn([]);

        $response = $this->controller->exportResignationReportsPdfDownload($req);

        $this->assertSame(200, $response->status());
        $this->assertStringStartsWith('%PDF', $response->body());
    }

    public function testAllResignationReportsPassesYearMonthPersonFiltersToRepository(): void
    {
        $_GET = ['year' => '2026', 'month' => '07', 'person' => 'Dupont', 'store_id' => '1'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A']]);
        $this->resignationReports->expects($this->once())->method('findAll')->with(
            [1],
            ['store_id' => 1, 'year' => '2026', 'month' => '07', 'person_in_charge' => 'Dupont']
        )->willReturn([]);

        $response = $this->controller->allResignationReports($req);

        $this->assertSame(200, $response->status());
    }

    public function testAllResignationReportsWithoutFiltersPassesEmptyFilters(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A']]);
        $this->resignationReports->expects($this->once())->method('findAll')->with([], [])->willReturn([]);

        $response = $this->controller->allResignationReports($req);

        $this->assertSame(200, $response->status());
    }

    public function testCreateResignationReportOnlyListsMembersOfTheStore(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1']);

        $this->stores->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([
            ['user_id' => 10, 'store_id' => 1],
            ['user_id' => 20, 'store_id' => 1],
        ]);
        $this->users->method('findById')->willReturnMap([
            [10, ['id' => 10, 'first_name' => 'Jean', 'last_name' => 'Dupont', 'employee_code' => 'EMP010']],
            [20, ['id' => 20, 'first_name' => 'Paul', 'last_name' => 'Martin', 'employee_code' => 'EMP020']],
        ]);

        $response = $this->controller->createResignationReport($req);

        $this->assertSame(200, $response->status());
    }

    public function testGetManagersForReportFormIncludesOwnerEvenWithoutStoreMembership(): void
    {
        // L'Owner n'a pas forcément de ligne store_user (voir install.php) mais doit
        // rester sélectionnable comme responsable sur n'importe quel store.
        $this->storeUsers->method('findByStore')->with(1)->willReturn([
            ['user_id' => 10, 'store_id' => 1, 'role' => 'manager'],
        ]);
        $this->users->method('findAll')->willReturn([
            ['id' => 99, 'first_name' => 'Owner', 'last_name' => 'Test', 'is_admin' => 1],
            ['id' => 10, 'first_name' => 'Jean', 'last_name' => 'Dupont', 'is_admin' => 0],
        ]);
        $this->users->method('findById')->willReturnMap([
            [10, ['id' => 10, 'first_name' => 'Jean', 'last_name' => 'Dupont', 'employee_code' => 'EMP010']],
        ]);
        // Le rôle RBAC affecté à l'utilisateur 10 accorde employees.update sur le store 1.
        $this->assignments->method('findByUser')->with(10)->willReturn([
            ['id' => 1, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['employees.update']);

        $method = new \ReflectionMethod($this->controller, 'getManagersForReportForm');
        $method->setAccessible(true);
        $managers = $method->invoke($this->controller, 1);

        $ids = array_column($managers, 'id');
        $this->assertContains(99, $ids, 'Owner (is_admin) doit apparaître même sans adhésion au store');
        $this->assertContains(10, $ids, 'membre avec employees.update sur ce store doit apparaître');
    }

    public function testGetManagersForReportFormExcludesMemberWithoutEmployeesUpdatePermission(): void
    {
        // Un membre du store sans la permission RBAC employees.update ne doit plus
        // apparaître, même si sa colonne legacy store_user.role vaut 'manager'
        // (colonne jamais resynchronisée quand les permissions d'un rôle changent).
        $this->storeUsers->method('findByStore')->with(1)->willReturn([
            ['user_id' => 10, 'store_id' => 1, 'role' => 'manager'],
        ]);
        $this->users->method('findAll')->willReturn([]);
        $this->users->method('findById')->willReturnMap([
            [10, ['id' => 10, 'first_name' => 'Jean', 'last_name' => 'Dupont', 'employee_code' => 'EMP010']],
        ]);
        $this->assignments->method('findByUser')->with(10)->willReturn([
            ['id' => 1, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['shifts.view']);

        $method = new \ReflectionMethod($this->controller, 'getManagersForReportForm');
        $method->setAccessible(true);
        $managers = $method->invoke($this->controller, 1);

        $this->assertNotContains(10, array_column($managers, 'id'));
    }

    public function testCreateResignationReportKeepsPresetUserEvenIfNotAStoreMember(): void
    {
        $_GET = ['user_id' => '99'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1, 'display_name' => 'Owner']);
        $req->setRouteParams(['id' => '1']);

        $this->stores->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->storeUsers->method('findByStore')->with(1)->willReturn([]);
        $this->users->method('findById')->with(99)->willReturn(['id' => 99, 'first_name' => 'Ex', 'last_name' => 'Employee', 'employee_code' => 'EMP099']);

        $response = $this->controller->createResignationReport($req);

        $this->assertSame(200, $response->status());
    }

    public function testShowResignationReportLogsConsultation(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '20']);

        $this->resignationReports->method('findById')->with(20)->willReturn(['id' => 20, 'store_id' => 1, 'employee_name' => 'Bob']);
        $this->stores->method('findById')->willReturn(['id' => 1, 'name' => 'Store A']);

        $this->logRepo->expects($this->once())->method('record')->with(
            $this->anything(), $this->anything(), $this->anything(),
            'resignation_report.viewed', 'resignation_report', 20,
            $this->anything(), $this->anything(), 1,
            $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(),
        );

        $response = $this->controller->showResignationReport($req);

        $this->assertSame(200, $response->status());
    }

    public function testShowResignationReportOfAnotherStoreThrowsNotFound(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '20']);

        // Le rapport existe mais appartient au store 2, pas au store 1 de l'URL
        $this->resignationReports->method('findById')->with(20)->willReturn(['id' => 20, 'store_id' => 2]);

        $this->expectException(NotFoundException::class);
        $this->controller->showResignationReport($req);
    }

    public function testStoreResignationReportDeactivatesTheEmployee(): void
    {
        $_POST = [
            'employee_number'    => 'E-1',
            'employee_name'      => 'Alice Martin',
            'resignation_date'   => '2026-08-01',
            'reason'             => 'Départ volontaire',
            'resignation_notice' => '',
            'notes'              => '',
            'person_in_charge'   => 'Manager X',
            'user_id'            => '5',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '1']);

        $this->stores->method('findById')->with(1)->willReturn(['id' => 1]);
        $this->resignationReports->method('save')->willReturnCallback(fn(array $d) => $d + ['id' => 99]);
        $this->users->method('findById')->with(5)->willReturn(['id' => 5, 'is_active' => 1]);

        $captured = null;
        $this->users->expects($this->once())->method('save')->willReturnCallback(function (array $u) use (&$captured) {
            $captured = $u;
            return $u;
        });

        $response = $this->controller->storeResignationReport($req);

        $this->assertSame(302, $response->status());
        $this->assertNotNull($captured);
        $this->assertSame(0, $captured['is_active']);
    }

    public function testUpdateResignationReportMergesPostFieldsAndRedirects(): void
    {
        $_POST = [
            'user_id'            => '5',
            'employee_number'    => 'E-42',
            'employee_name'      => 'Alice Martin',
            'resignation_date'   => '2026-08-01',
            'reason'             => '',
            'resignation_notice' => '',
            'notes'              => '',
            'person_in_charge'   => '',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->resignationReports->method('findById')->with(10)
            ->willReturn(['id' => 10, 'store_id' => 1, 'employee_name' => 'Ancien nom']);

        $this->resignationReports->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) =>
                $data['id'] === 10
                && $data['user_id'] === 5
                && $data['employee_name'] === 'Alice Martin'
        ));

        $response = $this->controller->updateResignationReport($req);

        $this->assertSame(302, $response->status());
    }

    public function testDeleteResignationReportDeletesAndLogs(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->resignationReports->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 1]);
        $this->resignationReports->expects($this->once())->method('delete')->with(10);

        $response = $this->controller->deleteResignationReport($req);

        $this->assertSame(302, $response->status());
    }

    public function testDeleteResignationReportReactivatesTheAssociatedAccount(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->resignationReports->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 1, 'user_id' => 5]);
        $this->resignationReports->expects($this->once())->method('delete')->with(10);
        $this->users->method('findById')->with(5)->willReturn(['id' => 5, 'is_active' => 0]);

        $captured = null;
        $this->users->expects($this->once())->method('save')->willReturnCallback(function (array $u) use (&$captured) {
            $captured = $u;
            return $u;
        });

        $response = $this->controller->deleteResignationReport($req);

        $this->assertSame(302, $response->status());
        $this->assertSame(1, $captured['is_active']);
    }

    public function testDeleteResignationReportWithoutLinkedUserDoesNotTouchUsers(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->resignationReports->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 1]);
        $this->resignationReports->expects($this->once())->method('delete')->with(10);
        $this->users->expects($this->never())->method('save');

        $response = $this->controller->deleteResignationReport($req);

        $this->assertSame(302, $response->status());
    }

    public function testReactivateUserReactivatesTheAssociatedAccount(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->stores->method('findById')->with(1)->willReturn(['id' => 1]);
        $this->resignationReports->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 1, 'user_id' => 5]);
        $this->users->method('findById')->with(5)->willReturn(['id' => 5, 'is_active' => 0]);

        $captured = null;
        $this->users->expects($this->once())->method('save')->willReturnCallback(function (array $u) use (&$captured) {
            $captured = $u;
            return $u;
        });

        $response = $this->controller->reactivateUser($req);

        $this->assertSame(302, $response->status());
        $this->assertSame(1, $captured['is_active']);
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
