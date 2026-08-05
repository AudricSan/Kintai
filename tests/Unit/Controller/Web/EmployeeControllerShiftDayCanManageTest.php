<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Auth\PermissionService;
use kintai\Core\Repositories\AvailabilityRepositoryInterface;
use kintai\Core\Repositories\IcalTokenRepositoryInterface;
use kintai\Core\Repositories\NotificationRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserDashboardPrefsRepositoryInterface;
use kintai\Core\Repositories\UserNavPrefsRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\UserShiftTypeRateRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\NotificationService;
use kintai\UI\Controller\Web\EmployeeController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie que 'can_manage' (bouton de gestion des shifts sur la page planning
 * employé) dépend désormais du RBAC réel (shifts.update sur le store affiché),
 * plus de la colonne legacy store_user.role, qui n'est jamais resynchronisée
 * quand les permissions d'un rôle sont modifiées après coup.
 */
final class EmployeeControllerShiftDayCanManageTest extends TestCase
{
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private StoreRepositoryInterface&MockObject $stores;
    private RoleAssignmentRepositoryInterface&MockObject $assignments;
    private RoleRepositoryInterface&MockObject $roles;
    private EmployeeController $controller;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-employee-controller-views';
        $this->writeViewFile($viewDir, 'scheduling.shifts-timeline', '<?= $can_manage ? "CAN_MANAGE_TRUE" : "CAN_MANAGE_FALSE" ?>');
        $this->writeViewFile($viewDir, 'layout.app', '<?= $content ?>');

        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->assignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->roles = $this->createMock(RoleRepositoryInterface::class);

        $this->controller = new EmployeeController(
            new ViewRenderer($viewDir),
            $this->createMock(ShiftRepositoryInterface::class),
            $this->createMock(ShiftTypeRepositoryInterface::class),
            $this->stores,
            $this->storeUsers,
            $this->createMock(UserRepositoryInterface::class),
            $this->createMock(TimeoffRequestRepositoryInterface::class),
            $this->createMock(ShiftSwapRequestRepositoryInterface::class),
            $this->createMock(UserShiftTypeRateRepositoryInterface::class),
            new AuditLogger(),
            $this->createMock(IcalTokenRepositoryInterface::class),
            $this->createMock(TimeclockRepositoryInterface::class),
            $this->createMock(AvailabilityRepositoryInterface::class),
            $this->createMock(UserDashboardPrefsRepositoryInterface::class),
            new NotificationService($this->createMock(NotificationRepositoryInterface::class)),
            $this->createMock(UserNavPrefsRepositoryInterface::class),
            new PermissionService($this->assignments, $this->roles),
        );

        $this->storeUsers->method('findByUser')->with(9)->willReturn([
            ['user_id' => 9, 'store_id' => 1, 'role' => 'staff'],
            ['user_id' => 9, 'store_id' => 2, 'role' => 'staff'],
        ]);
        $this->stores->method('findById')->willReturn(['id' => 1, 'name' => 'Store 1', 'currency' => 'JPY']);
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    private function grantShiftsUpdateOnStore(int $storeId): void
    {
        $this->assignments->method('findByUser')->with(9)->willReturn([
            ['id' => 1, 'user_id' => 9, 'role_id' => 5, 'scope_type' => 'store', 'scope_id' => $storeId],
        ]);
        $this->roles->method('findById')->with(5)->willReturn(['id' => 5, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(5)->willReturn(['shifts.update']);
    }

    public function testCanManageTrueWhenRoleGrantsShiftsUpdateOnDisplayedStore(): void
    {
        $this->grantShiftsUpdateOnStore(1);

        $_GET = ['store_id' => '1'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $response = $this->controller->shiftDay($req);

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('CAN_MANAGE_TRUE', $response->body());
    }

    public function testCanManageFalseWhenRoleGrantsShiftsUpdateOnOtherStoreOnly(): void
    {
        // Le rôle accorde shifts.update sur le store 2, mais l'utilisateur consulte le store 1.
        $this->grantShiftsUpdateOnStore(2);

        $_GET = ['store_id' => '1'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $response = $this->controller->shiftDay($req);

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('CAN_MANAGE_FALSE', $response->body());
    }

    public function testCanManageFalseWhenNoRoleGrantsAnything(): void
    {
        $this->assignments->method('findByUser')->with(9)->willReturn([]);

        $_GET = ['store_id' => '1'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $response = $this->controller->shiftDay($req);

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('CAN_MANAGE_FALSE', $response->body());
    }

    public function testCanManageTrueForOwnerRegardlessOfPermissions(): void
    {
        $this->assignments->method('findByUser')->with(9)->willReturn([]);

        $_GET = ['store_id' => '1'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9, 'is_admin' => 1]);

        $response = $this->controller->shiftDay($req);

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('CAN_MANAGE_TRUE', $response->body());
    }

    private function writeViewFile(string $dir, string $view, string $phpBody): void
    {
        $file = $dir . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        $parent = dirname($file);
        if (!is_dir($parent)) {
            mkdir($parent, 0777, true);
        }
        file_put_contents($file, $phpBody);
    }
}
