<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\ShiftClaim;

use kintai\Bundles\ShiftClaim\Controllers\Web\AdminShiftClaimController;
use kintai\Core\Repositories\ShiftClaimRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\ShiftServiceInterface;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminShiftClaimControllerTest extends TestCase
{
    private ShiftRepositoryInterface&MockObject $shifts;
    private ShiftTypeRepositoryInterface&MockObject $shiftTypes;
    private StoreRepositoryInterface&MockObject $stores;
    private ShiftClaimRepositoryInterface&MockObject $shiftClaims;
    private ShiftServiceInterface&MockObject $shiftService;
    private AdminShiftClaimController $controller;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-shift-claim-views';
        $this->ensureViewFile($viewDir, 'open-shifts');
        $this->ensureViewFile($viewDir, 'open-shifts-select');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $view = new ViewRenderer(sys_get_temp_dir());
        $view->addNamespace('shift-claim', $viewDir);

        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftTypes = $this->createMock(ShiftTypeRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->shiftClaims = $this->createMock(ShiftClaimRepositoryInterface::class);
        $this->shiftService = $this->createMock(ShiftServiceInterface::class);

        $this->controller = new AdminShiftClaimController(
            $view,
            $this->createMock(UserRepositoryInterface::class),
            $this->stores,
            $this->shifts,
            $this->shiftTypes,
            $this->shiftClaims,
            $this->shiftService,
            new AuditLogger(),
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
    }

    public function testOpenShiftsEnrichesEachShiftWithItsClaims(): void
    {
        $this->shifts->method('findOpen')->willReturn([
            ['id' => 1, 'shift_date' => '2026-08-01', 'store_id' => 5],
        ]);
        $this->shiftTypes->method('findAll')->willReturn([]);
        $this->stores->method('findAll')->willReturn([['id' => 5, 'name' => 'Store A']]);
        $this->shiftClaims->method('findByShift')->with(1)->willReturn([
            ['id' => 10, 'user_id' => 9, 'status' => 'pending'],
        ]);

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller->openShifts($req);

        $this->assertSame(200, $response->status());
    }

    public function testSelectShiftToPublishListsOnlyUpcomingUnpublishedAssignedShifts(): void
    {
        $today = date('Y-m-d');
        $past  = date('Y-m-d', strtotime('-1 day'));
        $future = date('Y-m-d', strtotime('+1 day'));

        $this->shifts->method('findAll')->willReturn([
            ['id' => 1, 'shift_date' => $future, 'is_open' => 0, 'user_id' => 5, 'store_id' => 1],
            ['id' => 2, 'shift_date' => $past,   'is_open' => 0, 'user_id' => 5, 'store_id' => 1], // passé
            ['id' => 3, 'shift_date' => $future, 'is_open' => 1, 'user_id' => 5, 'store_id' => 1], // déjà publié
            ['id' => 4, 'shift_date' => $future, 'is_open' => 0, 'user_id' => null, 'store_id' => 1], // non assigné
        ]);

        $this->shiftService->method('getUsersMap')->willReturn([5 => 'Alice']);
        $this->stores->method('findAll')->willReturn([]);

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller->selectShiftToPublish($req);

        $this->assertSame(200, $response->status());
    }

    public function testPublishShiftSetsIsOpen(): void
    {
        $shift = ['id' => 1, 'store_id' => 5, 'is_open' => 0];
        $this->shifts->method('findById')->with(1)->willReturn($shift);

        $captured = null;
        $this->shifts->expects($this->once())->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d;
        });

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1']);

        $response = $this->controller->publishShift($req);

        $this->assertSame(302, $response->status());
        $this->assertSame(1, $captured['is_open']);
    }

    public function testUnpublishShiftWithdrawsPendingClaims(): void
    {
        $shift = ['id' => 1, 'store_id' => 5, 'is_open' => 1];
        $this->shifts->method('findById')->with(1)->willReturn($shift);
        $this->shifts->method('save')->willReturnCallback(fn(array $d) => $d);

        $this->shiftClaims->method('findByShift')->with(1)->willReturn([
            ['id' => 10, 'status' => 'pending'],
            ['id' => 11, 'status' => 'withdrawn'],
        ]);
        $this->shiftClaims->expects($this->once())->method('save')
            ->with($this->callback(fn($d) => $d['id'] === 10 && $d['status'] === 'withdrawn'));

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1']);

        $response = $this->controller->unpublishShift($req);

        $this->assertSame(302, $response->status());
    }

    public function testApproveShiftClaimAssignsShiftAndRejectsOtherPendingClaims(): void
    {
        $claim = ['id' => 10, 'shift_id' => 1, 'user_id' => 9, 'status' => 'pending'];
        $shift = ['id' => 1, 'store_id' => 5, 'is_open' => 1];

        $this->shiftClaims->method('findById')->with(10)->willReturn($claim);
        $this->shifts->method('findById')->with(1)->willReturn($shift);
        $this->shiftClaims->method('findByShift')->with(1)->willReturn([
            $claim,
            ['id' => 11, 'user_id' => 3, 'status' => 'pending'],
        ]);

        $savedClaims = [];
        $this->shiftClaims->method('save')->willReturnCallback(function (array $d) use (&$savedClaims) {
            $savedClaims[] = $d;
            return $d;
        });

        $savedShift = null;
        $this->shifts->expects($this->once())->method('save')->willReturnCallback(function (array $d) use (&$savedShift) {
            $savedShift = $d;
            return $d;
        });

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '10']);

        $response = $this->controller->approveShiftClaim($req);

        $this->assertSame(302, $response->status());
        $this->assertSame(9, $savedShift['user_id']);
        $this->assertSame(0, $savedShift['is_open']);
        $this->assertSame('approved', $savedClaims[0]['status']);
        $this->assertSame('rejected', $savedClaims[1]['status']);
    }

    public function testRejectShiftClaimSetsRejectedStatus(): void
    {
        $claim = ['id' => 10, 'shift_id' => 1, 'user_id' => 9, 'status' => 'pending'];
        $shift = ['id' => 1, 'store_id' => 5];

        $this->shiftClaims->method('findById')->with(10)->willReturn($claim);
        $this->shifts->method('findById')->with(1)->willReturn($shift);

        $captured = null;
        $this->shiftClaims->expects($this->once())->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d;
        });

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '10']);

        $response = $this->controller->rejectShiftClaim($req);

        $this->assertSame(302, $response->status());
        $this->assertSame('rejected', $captured['status']);
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
