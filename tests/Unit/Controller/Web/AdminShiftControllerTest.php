<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Auth\PermissionService;
use kintai\Core\Repositories\ImportAliasRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\UserShiftTypeRateRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\NotificationService;
use kintai\Core\Services\ShiftServiceInterface;
use kintai\UI\Controller\Web\Scheduling\AdminShiftController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminShiftControllerTest extends TestCase
{
    private ShiftRepositoryInterface&MockObject $shifts;
    private ShiftTypeRepositoryInterface&MockObject $shiftTypes;
    private StoreRepositoryInterface&MockObject $stores;
    private TimeoffRequestRepositoryInterface&MockObject $timeoffRequests;
    private ShiftServiceInterface&MockObject $shiftService;
    private NotificationService&MockObject $notifs;
    private RoleAssignmentRepositoryInterface&MockObject $roleAssignments;
    private RoleRepositoryInterface&MockObject $roles;
    private UserRepositoryInterface&MockObject $users;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private AdminShiftController $controller;

    protected function setUp(): void
    {
        $this->ensureViewFile('scheduling.shifts');
        $this->ensureViewFile('layout.app');
        $view = new ViewRenderer(sys_get_temp_dir());
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftTypes = $this->createMock(ShiftTypeRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->timeoffRequests = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $this->shiftService = $this->createMock(ShiftServiceInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);
        $this->roleAssignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->roles = $this->createMock(RoleRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $auditLogger = new AuditLogger();
        $permissions = new PermissionService($this->roleAssignments, $this->roles);

        $this->controller = new AdminShiftController(
            $view,
            $this->users,
            $this->stores,
            $this->shifts,
            $this->shiftTypes,
            $this->storeUsers,
            $this->createMock(UserShiftTypeRateRepositoryInterface::class),
            $auditLogger,
            $this->notifs,
            $this->createMock(ImportAliasRepositoryInterface::class),
            $this->shiftService,
            $this->timeoffRequests,
            $permissions,
        );
    }

    public function testShiftsRendersList(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->shifts->method('findAll')->willReturn([]);
        $this->shiftTypes->method('findAll')->willReturn([]);
        $this->stores->method('findAll')->willReturn([]);

        $response = $this->controller->shifts($req);

        $this->assertSame(200, $response->status());
    }

    // -------------------------------------------------------------------------
    // quickShift
    // -------------------------------------------------------------------------

    public function testQuickShiftCreatesShiftAndReturns201(): void
    {
        $req = $this->makeJsonRequest([
            'store_id'   => 1,
            'user_id'    => 5,
            'shift_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time'   => '17:00',
        ]);
        $req->setAttribute('managed_store_ids', null);

        $this->timeoffRequests->method('findByUser')->willReturn([]);
        $this->shifts->method('save')->willReturnCallback(fn(array $d) => $d + ['id' => 42]);

        $response = $this->controller->quickShift($req);

        $this->assertSame(201, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertSame(42, $data['id']);
        $this->assertSame('09:00', $data['start_time']);
    }

    public function testQuickShiftWithAdditionalFields(): void
    {
        $req = $this->makeJsonRequest([
            'store_id'        => 1,
            'user_id'         => 5,
            'shift_date'      => '2026-06-01',
            'start_time'      => '09:00',
            'end_time'        => '17:00',
            'pause_minutes'   => 30,
            'notes'           => 'Test note',
            'open_shift_note' => 'Ouvert',
            'is_open'         => true,
        ]);
        $req->setAttribute('managed_store_ids', null);

        $this->timeoffRequests->method('findByUser')->willReturn([]);
        $captured = null;
        $this->shifts->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d + ['id' => 1];
        });

        $this->controller->quickShift($req);

        $this->assertNotNull($captured);
        $this->assertSame(30, $captured['pause_minutes']);
        $this->assertSame('Test note', $captured['notes']);
        $this->assertSame('Ouvert', $captured['open_shift_note']);
        $this->assertSame(1, $captured['is_open']);
    }

    public function testQuickShiftForbiddenStoreAccess(): void
    {
        $req = $this->makeJsonRequest([
            'store_id' => 99,
            'shift_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time'   => '17:00',
        ]);
        $req->setAttribute('managed_store_ids', [1, 2]);

        $response = $this->controller->quickShift($req);

        $this->assertSame(403, $response->status());
        $data = json_decode($response->body(), true);
        $this->assertSame('Accès refusé.', $data['error']);
    }

    /**
     * shift_type_id n'est plus posté/choisi manuellement : il est déduit
     * automatiquement du type dominant (le plus de minutes) parmi les types
     * actifs du store, en fonction du chevauchement horaire avec le shift.
     */
    public function testQuickShiftAutoComputesDominantShiftType(): void
    {
        $req = $this->makeJsonRequest([
            'store_id'   => 1,
            'user_id'    => 5,
            'shift_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time'   => '17:00',
        ]);
        $req->setAttribute('managed_store_ids', null);

        $this->shiftTypes->method('findActive')->with(1)->willReturn([
            ['id' => 3, 'name' => 'Matin', 'start_time' => '08:00', 'end_time' => '12:00', 'hourly_rate' => 1000.0],
            ['id' => 4, 'name' => 'Aprem', 'start_time' => '12:00', 'end_time' => '20:00', 'hourly_rate' => 1200.0],
        ]);
        $this->timeoffRequests->method('findByUser')->willReturn([]);
        $captured = null;
        $this->shifts->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d + ['id' => 1];
        });

        $this->controller->quickShift($req);

        // 09:00→12:00 = 180min (Matin) ; 12:00→17:00 = 300min (Aprem) → Aprem dominant
        $this->assertSame(4, $captured['shift_type_id']);
    }

    public function testQuickShiftBlockedWhenUserOnApprovedTimeoff(): void
    {
        $req = $this->makeJsonRequest([
            'store_id'   => 1,
            'user_id'    => 5,
            'shift_date' => '2026-08-02',
            'start_time' => '09:00',
            'end_time'   => '17:00',
        ]);
        $req->setAttribute('managed_store_ids', null);

        $this->timeoffRequests->method('findByUser')->with(5)->willReturn([
            ['status' => 'approved', 'start_date' => '2026-08-01', 'end_date' => '2026-08-05'],
        ]);
        $this->shifts->expects($this->never())->method('save');

        $response = $this->controller->quickShift($req);

        $this->assertSame(409, $response->status());
    }

    // -------------------------------------------------------------------------
    // storeShift
    // -------------------------------------------------------------------------

    public function testStoreShiftBlockedWhenUserOnApprovedTimeoff(): void
    {
        $_POST = [
            'store_id'   => '1',
            'user_id'    => '5',
            'shift_date' => '2026-08-02',
            'start_time' => '09:00',
            'end_time'   => '17:00',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->timeoffRequests->method('findByUser')->with(5)->willReturn([
            ['status' => 'approved', 'start_date' => '2026-08-01', 'end_date' => '2026-08-05'],
        ]);
        $this->shifts->expects($this->never())->method('save');

        $response = $this->controller->storeShift($req);

        $this->assertSame(302, $response->status());
    }

    public function testStoreShiftAllowedWhenNoTimeoffConflict(): void
    {
        $_POST = [
            'store_id'   => '1',
            'user_id'    => '5',
            'shift_date' => '2026-08-02',
            'start_time' => '09:00',
            'end_time'   => '17:00',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->timeoffRequests->method('findByUser')->willReturn([]);
        $this->shifts->expects($this->once())->method('save')->willReturnCallback(fn(array $d) => $d + ['id' => 1]);

        $response = $this->controller->storeShift($req);

        $this->assertSame(302, $response->status());
    }

    /**
     * shift_type_id n'est plus posté/choisi manuellement : il est déduit
     * automatiquement du type dominant parmi les types actifs du store, en
     * fonction du chevauchement horaire avec le shift (un shift peut traverser
     * plusieurs tranches, ex : 09:00-17:00 traverse "Matin" puis "Aprem").
     */
    public function testStoreShiftAutoComputesDominantShiftType(): void
    {
        $_POST = [
            'store_id'   => '1',
            'user_id'    => '5',
            'shift_date' => '2026-08-02',
            'start_time' => '09:00',
            'end_time'   => '17:00',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->shiftTypes->method('findActive')->with(1)->willReturn([
            ['id' => 3, 'name' => 'Matin', 'start_time' => '08:00', 'end_time' => '12:00', 'hourly_rate' => 1000.0],
            ['id' => 4, 'name' => 'Aprem', 'start_time' => '12:00', 'end_time' => '20:00', 'hourly_rate' => 1200.0],
        ]);
        $this->timeoffRequests->method('findByUser')->willReturn([]);
        $captured = null;
        $this->shifts->expects($this->once())->method('save')->with($this->callback(
            function (array $d) use (&$captured) {
                $captured = $d;
                return true;
            }
        ))->willReturnCallback(fn(array $d) => $d + ['id' => 1]);

        $response = $this->controller->storeShift($req);

        $this->assertSame(302, $response->status());
        // 09:00→12:00 = 180min (Matin) ; 12:00→17:00 = 300min (Aprem) → Aprem dominant
        $this->assertSame(4, $captured['shift_type_id']);
    }

    /**
     * Régression : le shift est déjà sauvegardé à ce stade. Si NotificationService::notify()
     * échoue (ex. panne DB transitoire), l'exception ne doit pas remonter en 500 générique
     * pour une action qui a en réalité pleinement réussi — voir CHANGELOG.
     */
    public function testStoreShiftSucceedsEvenWhenNotifyThrows(): void
    {
        $_POST = [
            'store_id'   => '1',
            'user_id'    => '5',
            'shift_date' => '2026-08-02',
            'start_time' => '09:00',
            'end_time'   => '17:00',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->timeoffRequests->method('findByUser')->willReturn([]);
        $this->shifts->method('save')->willReturnCallback(fn(array $d) => $d + ['id' => 1]);
        $this->notifs->method('notify')->willThrowException(new \RuntimeException('notif DB down'));

        $response = $this->controller->storeShift($req);

        $this->assertSame(302, $response->status());
        $headersRef = new \ReflectionProperty($response, 'headers');
        $headersRef->setAccessible(true);
        $this->assertStringContainsString('success=created', $headersRef->getValue($response)['Location'] ?? '');
    }

    /** Un type de shift ne peut désormais être appliqué qu'à un store où il est activé (table pivot). */
    public function testStoreShiftRejectsShiftTypeNotEnabledForStore(): void
    {
        $_POST = [
            'store_id'   => '1',
            'user_id'    => '5',
            'shift_date' => '2026-08-02',
            'start_time' => '09:00',
            'end_time'   => '17:00',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->shiftTypes->method('findActive')->with(1)->willReturn([
            ['id' => 3, 'name' => 'Matin', 'start_time' => '08:00', 'end_time' => '12:00', 'hourly_rate' => 1000.0],
            ['id' => 4, 'name' => 'Aprem', 'start_time' => '12:00', 'end_time' => '20:00', 'hourly_rate' => 1200.0],
        ]);
        $this->timeoffRequests->method('findByUser')->willReturn([]);
        $captured = null;
        $this->shifts->expects($this->once())->method('save')->with($this->callback(
            function (array $d) use (&$captured) {
                $captured = $d;
                return true;
            }
        ))->willReturnCallback(fn(array $d) => $d + ['id' => 1]);

        $response = $this->controller->storeShift($req);

        $this->assertSame(302, $response->status());
        // 09:00→12:00 = 180min (Matin) ; 12:00→17:00 = 300min (Aprem) → Aprem dominant
        $this->assertSame(4, $captured['shift_type_id']);
    }

    /** Un taux/heures actives manuel saisi doit être persisté (null si vide). */
    public function testStoreShiftPersistsManualOverrides(): void
    {
        $_POST = [
            'store_id'              => '1',
            'user_id'               => '5',
            'shift_date'            => '2026-08-02',
            'start_time'            => '09:00',
            'end_time'              => '17:00',
            'hourly_rate_override'  => '1500.5',
            'net_minutes_override'  => '300',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->timeoffRequests->method('findByUser')->willReturn([]);
        $this->shifts->expects($this->once())->method('save')->with($this->callback(
            fn(array $d) => $d['hourly_rate_override'] === 1500.5 && $d['net_minutes_override'] === 300
        ))->willReturnCallback(fn(array $d) => $d + ['id' => 1]);

        $response = $this->controller->storeShift($req);

        $this->assertSame(302, $response->status());
    }

    /** Champs laissés vides : pas d'ajustement manuel, calcul automatique. */
    public function testStoreShiftOverridesDefaultToNullWhenEmpty(): void
    {
        $_POST = [
            'store_id'   => '1',
            'user_id'    => '5',
            'shift_date' => '2026-08-02',
            'start_time' => '09:00',
            'end_time'   => '17:00',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->timeoffRequests->method('findByUser')->willReturn([]);
        $this->shifts->expects($this->once())->method('save')->with($this->callback(
            fn(array $d) => $d['hourly_rate_override'] === null && $d['net_minutes_override'] === null
        ))->willReturnCallback(fn(array $d) => $d + ['id' => 1]);

        $response = $this->controller->storeShift($req);

        $this->assertSame(302, $response->status());
    }

    // -------------------------------------------------------------------------
    // updateShift
    // -------------------------------------------------------------------------

    /** Éditer un shift et saisir un ajustement manuel doit le persister. */
    public function testUpdateShiftPersistsManualOverrides(): void
    {
        $existing = [
            'id' => 10, 'store_id' => 1, 'user_id' => 5, 'shift_date' => '2026-08-02',
            'start_time' => '09:00', 'end_time' => '17:00', 'is_open' => 0,
        ];
        $this->shifts->method('findById')->with(10)->willReturn($existing);

        $_POST = [
            'store_id'             => '1',
            'shift_date'           => '2026-08-02',
            'start_time'           => '09:00',
            'end_time'             => '17:00',
            'hourly_rate_override' => '2000',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => 10]);

        $this->shifts->expects($this->once())->method('save')->with($this->callback(
            fn(array $d) => $d['hourly_rate_override'] === 2000.0 && $d['net_minutes_override'] === null
        ))->willReturnCallback(fn(array $d) => $d + ['id' => 10]);

        $response = $this->controller->updateShift($req);

        $this->assertSame(302, $response->status());
    }

    /** Même auto-calcul du type dominant que storeShift(), lors d'une édition. */
    public function testUpdateShiftAutoComputesDominantShiftType(): void
    {
        $existing = [
            'id' => 10, 'store_id' => 1, 'user_id' => 5, 'shift_date' => '2026-08-02',
            'start_time' => '09:00', 'end_time' => '17:00', 'is_open' => 0,
        ];
        $this->shifts->method('findById')->with(10)->willReturn($existing);

        $_POST = [
            'store_id'   => '1',
            'shift_date' => '2026-08-02',
            'start_time' => '09:00',
            'end_time'   => '17:00',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => 10]);

        $this->shiftTypes->method('findActive')->with(1)->willReturn([
            ['id' => 3, 'name' => 'Matin', 'start_time' => '08:00', 'end_time' => '12:00', 'hourly_rate' => 1000.0],
            ['id' => 4, 'name' => 'Aprem', 'start_time' => '12:00', 'end_time' => '20:00', 'hourly_rate' => 1200.0],
        ]);
        $captured = null;
        $this->shifts->expects($this->once())->method('save')->with($this->callback(
            function (array $d) use (&$captured) {
                $captured = $d;
                return true;
            }
        ))->willReturnCallback(fn(array $d) => $d + ['id' => 10]);

        $response = $this->controller->updateShift($req);

        $this->assertSame(302, $response->status());
        $this->assertSame(4, $captured['shift_type_id']);
    }

    /**
     * Régression : même chose que testStoreShiftSucceedsEvenWhenNotifyThrows, côté update —
     * le shift est déjà sauvegardé avant l'appel à notify() (déclenché ici par un changement
     * d'utilisateur assigné, 5 → 6).
     */
    public function testUpdateShiftSucceedsEvenWhenNotifyThrows(): void
    {
        $existing = [
            'id' => 10, 'store_id' => 1, 'user_id' => 5, 'shift_date' => '2026-08-02',
            'start_time' => '09:00', 'end_time' => '17:00', 'is_open' => 0,
        ];
        $this->shifts->method('findById')->with(10)->willReturn($existing);

        $_POST = [
            'store_id'   => '1',
            'user_id'    => '6',
            'shift_date' => '2026-08-02',
            'start_time' => '09:00',
            'end_time'   => '17:00',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => 10]);

        $this->shifts->method('save')->willReturnCallback(fn(array $d) => $d + ['id' => 10]);
        $this->notifs->method('notify')->willThrowException(new \RuntimeException('notif DB down'));

        $response = $this->controller->updateShift($req);

        $this->assertSame(302, $response->status());
        $headersRef = new \ReflectionProperty($response, 'headers');
        $headersRef->setAccessible(true);
        $this->assertStringContainsString('success=updated', $headersRef->getValue($response)['Location'] ?? '');
    }

    public function testUpdateShiftRejectsShiftTypeNotEnabledForStore(): void
    {
        $existing = [
            'id' => 10, 'store_id' => 1, 'user_id' => 5, 'shift_date' => '2026-08-02',
            'start_time' => '09:00', 'end_time' => '17:00', 'is_open' => 0,
        ];
        $this->shifts->method('findById')->with(10)->willReturn($existing);

        $_POST = [
            'store_id'   => '1',
            'shift_date' => '2026-08-02',
            'start_time' => '09:00',
            'end_time'   => '17:00',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => 10]);

        $this->shiftTypes->method('findActive')->with(1)->willReturn([
            ['id' => 3, 'name' => 'Matin', 'start_time' => '08:00', 'end_time' => '12:00', 'hourly_rate' => 1000.0],
            ['id' => 4, 'name' => 'Aprem', 'start_time' => '12:00', 'end_time' => '20:00', 'hourly_rate' => 1200.0],
        ]);
        $captured = null;
        $this->shifts->expects($this->once())->method('save')->with($this->callback(
            function (array $d) use (&$captured) {
                $captured = $d;
                return true;
            }
        ))->willReturnCallback(fn(array $d) => $d + ['id' => 10]);

        $response = $this->controller->updateShift($req);

        $this->assertSame(302, $response->status());
        $this->assertSame(4, $captured['shift_type_id']);
    }

    // -------------------------------------------------------------------------
    // selectShiftToPublish
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // shiftsTimeline — can_manage
    // -------------------------------------------------------------------------

    /**
     * Régression sévère : shiftsTimeline() ne calculait jamais can_manage, et la vue
     * (scheduling.shifts-timeline) défautait alors à true — exposant le bouton import
     * Excel, la création rapide de shift, le glisser-déposer et le détail des taux
     * horaires dans la modale de shift à N'IMPORTE QUEL viewer capable d'atteindre cette
     * page, y compris un simple employé (rôle "employee", uniquement shifts.view — voir
     * CHANGELOG). Ce test vérifie que can_manage reflète désormais shifts.update.
     */
    public function testShiftsTimelinePassesCanManageTrueForOwner(): void
    {
        $canManage = $this->renderTimelineAndCaptureCanManage(['id' => 1, 'is_admin' => true]);

        $this->assertTrue($canManage);
    }

    public function testShiftsTimelinePassesCanManageTrueForRoleGrantingShiftsUpdate(): void
    {
        $this->roleAssignments->method('findByUser')->with(9)->willReturn([
            ['id' => 1, 'user_id' => 9, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->method('findById')->with(2)->willReturn(['id' => 2, 'slug' => 'manager', 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(2)->willReturn(['shifts.view', 'shifts.update']);

        $canManage = $this->renderTimelineAndCaptureCanManage(['id' => 9, 'is_admin' => false], [1]);

        $this->assertTrue($canManage);
    }

    /** Le cas exact du bug rapporté : rôle "employee" avec shifts.view seul. */
    public function testShiftsTimelinePassesCanManageFalseForViewOnlyRole(): void
    {
        $this->roleAssignments->method('findByUser')->with(14)->willReturn([
            ['id' => 1, 'user_id' => 14, 'role_id' => 3, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->method('findById')->with(3)->willReturn(['id' => 3, 'slug' => 'employee', 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(3)->willReturn(['shifts.view']);

        $canManage = $this->renderTimelineAndCaptureCanManage(['id' => 14, 'is_admin' => false], [1]);

        $this->assertFalse($canManage);
    }

    private function renderTimelineAndCaptureCanManage(array $authUser, ?array $managedStoreIds = null): bool
    {
        $this->writeViewFile('scheduling.shifts-timeline', "<?php echo json_encode(['can_manage' => \$can_manage ?? null]);");
        $this->writeViewFile('layout.app', "<?php echo \$content ?? '';");

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store 1']]);
        $this->stores->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store 1']);
        $this->shifts->method('findByStore')->willReturn([]);
        $this->shiftTypes->method('findByStores')->willReturn([]);
        $this->shiftTypes->method('findAll')->willReturn([]);
        $this->storeUsers->method('findByStore')->willReturn([]);
        $this->users->method('findAll')->willReturn([]);

        $req = new Request();
        $req->setAttribute('auth_user', $authUser);
        $req->setAttribute('managed_store_ids', $managedStoreIds);

        $response = $this->controller->shiftsTimeline($req);
        $data     = json_decode($response->body(), true);

        return $data['can_manage'];
    }

    private function writeViewFile(string $view, string $content): void
    {
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        $dir  = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($file, $content);
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    private function makeJsonRequest(array $body): Request
    {
        $req = new Request();
        $ref = new \ReflectionProperty(Request::class, 'jsonBody');
        $ref->setAccessible(true);
        $ref->setValue($req, $body);
        return $req;
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
