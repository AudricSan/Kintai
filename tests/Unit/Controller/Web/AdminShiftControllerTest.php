<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Repositories\ImportAliasRepositoryInterface;
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
        $auditLogger = new AuditLogger();

        $this->controller = new AdminShiftController(
            $view,
            $this->createMock(UserRepositoryInterface::class),
            $this->stores,
            $this->shifts,
            $this->shiftTypes,
            $this->createMock(StoreUserRepositoryInterface::class),
            $this->createMock(UserShiftTypeRateRepositoryInterface::class),
            $auditLogger,
            $this->createMock(NotificationService::class),
            $this->createMock(ImportAliasRepositoryInterface::class),
            $this->shiftService,
            $this->timeoffRequests,
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

    public function testQuickShiftRejectsShiftTypeNotEnabledForStore(): void
    {
        $req = $this->makeJsonRequest([
            'store_id'      => 1,
            'shift_date'    => '2026-06-01',
            'start_time'    => '09:00',
            'end_time'      => '17:00',
            'shift_type_id' => 7,
        ]);
        $req->setAttribute('managed_store_ids', null);

        $this->shiftTypes->method('isEnabledForStore')->with(7, 1)->willReturn(false);
        $this->shifts->expects($this->never())->method('save');

        $response = $this->controller->quickShift($req);

        $this->assertSame(422, $response->status());
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

    /** Un type de shift ne peut désormais être appliqué qu'à un store où il est activé (table pivot). */
    public function testStoreShiftRejectsShiftTypeNotEnabledForStore(): void
    {
        $_POST = [
            'store_id'      => '1',
            'shift_date'    => '2026-08-02',
            'start_time'    => '09:00',
            'end_time'      => '17:00',
            'shift_type_id' => '7',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->shiftTypes->method('isEnabledForStore')->with(7, 1)->willReturn(false);
        $this->shifts->expects($this->never())->method('save');

        $response = $this->controller->storeShift($req);

        $this->assertSame(302, $response->status());
    }

    public function testStoreShiftAllowsShiftTypeEnabledForStore(): void
    {
        $_POST = [
            'store_id'      => '1',
            'shift_date'    => '2026-08-02',
            'start_time'    => '09:00',
            'end_time'      => '17:00',
            'shift_type_id' => '7',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->shiftTypes->method('isEnabledForStore')->with(7, 1)->willReturn(true);
        $this->shifts->expects($this->once())->method('save')->willReturnCallback(fn(array $d) => $d + ['id' => 1]);

        $response = $this->controller->storeShift($req);

        $this->assertSame(302, $response->status());
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

    public function testUpdateShiftRejectsShiftTypeNotEnabledForStore(): void
    {
        $existing = [
            'id' => 10, 'store_id' => 1, 'user_id' => 5, 'shift_date' => '2026-08-02',
            'start_time' => '09:00', 'end_time' => '17:00', 'is_open' => 0,
        ];
        $this->shifts->method('findById')->with(10)->willReturn($existing);

        $_POST = [
            'store_id'      => '1',
            'shift_date'    => '2026-08-02',
            'start_time'    => '09:00',
            'end_time'      => '17:00',
            'shift_type_id' => '7',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => 10]);

        $this->shiftTypes->method('isEnabledForStore')->with(7, 1)->willReturn(false);
        $this->shifts->expects($this->never())->method('save');

        $response = $this->controller->updateShift($req);

        $this->assertSame(302, $response->status());
    }

    // -------------------------------------------------------------------------
    // selectShiftToPublish
    // -------------------------------------------------------------------------

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
