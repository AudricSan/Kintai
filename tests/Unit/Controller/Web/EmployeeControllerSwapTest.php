<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\AvailabilityRepositoryInterface;
use kintai\Core\Repositories\IcalTokenRepositoryInterface;
use kintai\Core\Repositories\NotificationRepositoryInterface;
use kintai\Core\Repositories\ShiftClaimRepositoryInterface;
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
 * Régression : storeSwap/acceptSwap/refuseSwap écrivaient et relisaient les clés
 * 'target_id'/'peer_accepted_at', qui n'existent pas dans la table shift_swap_requests
 * (colonnes réelles : target_user_id/accepted_at). En production, storeSwap plantait
 * avec une erreur SQL "unknown column 'target_id'" à chaque tentative d'échange.
 */
final class EmployeeControllerSwapTest extends TestCase
{
    private ShiftRepositoryInterface&MockObject $shifts;
    private ShiftSwapRequestRepositoryInterface&MockObject $swapRequests;
    private StoreUserRepositoryInterface&MockObject $storeUsers;
    private EmployeeController $controller;

    protected function setUp(): void
    {
        $this->shifts       = $this->createMock(ShiftRepositoryInterface::class);
        $this->swapRequests = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $this->storeUsers   = $this->createMock(StoreUserRepositoryInterface::class);

        $this->controller = new EmployeeController(
            new ViewRenderer(sys_get_temp_dir()),
            $this->shifts,
            $this->createMock(ShiftTypeRepositoryInterface::class),
            $this->createMock(StoreRepositoryInterface::class),
            $this->storeUsers,
            $this->createMock(UserRepositoryInterface::class),
            $this->createMock(TimeoffRequestRepositoryInterface::class),
            $this->swapRequests,
            $this->createMock(UserShiftTypeRateRepositoryInterface::class),
            new AuditLogger(),
            $this->createMock(IcalTokenRepositoryInterface::class),
            $this->createMock(TimeclockRepositoryInterface::class),
            $this->createMock(AvailabilityRepositoryInterface::class),
            $this->createMock(UserDashboardPrefsRepositoryInterface::class),
            $this->createMock(ShiftClaimRepositoryInterface::class),
            new NotificationService($this->createMock(NotificationRepositoryInterface::class)),
            $this->createMock(UserNavPrefsRepositoryInterface::class),
        );
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    public function testStoreSwapSavesTargetUserIdAndAcceptedAtColumns(): void
    {
        $_POST = [
            'requester_shift_id' => '10',
            'target_id'          => '2',
            'target_shift_id'    => '20',
            'reason'             => 'Test',
        ];

        $myShift     = ['id' => 10, 'user_id' => 1, 'store_id' => 5];
        $targetShift = ['id' => 20, 'user_id' => 2, 'store_id' => 5];
        $this->shifts->method('findById')->willReturnMap([
            [10, $myShift],
            [20, $targetShift],
        ]);

        $captured = null;
        $this->swapRequests->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d + ['id' => 42];
        });

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $response = $this->controller->storeSwap($req);

        $this->assertSame(302, $response->status());
        $this->assertArrayHasKey('target_user_id', $captured);
        $this->assertSame(2, $captured['target_user_id']);
        $this->assertArrayNotHasKey('target_id', $captured);
        $this->assertArrayHasKey('accepted_at', $captured);
        $this->assertNull($captured['accepted_at']);
        $this->assertArrayNotHasKey('peer_accepted_at', $captured);
    }

    public function testAcceptSwapReadsAndWritesAcceptedAtColumn(): void
    {
        $swap = [
            'id' => 5, 'store_id' => 3, 'requester_id' => 1,
            'target_user_id' => 9, 'status' => 'pending', 'accepted_at' => null,
        ];
        $this->swapRequests->method('findById')->with(5)->willReturn($swap);

        $captured = null;
        $this->swapRequests->expects($this->once())->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d;
        });

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);
        $req->setRouteParams(['id' => '5']);

        $response = $this->controller->acceptSwap($req);

        $this->assertSame(302, $response->status());
        $this->assertArrayHasKey('accepted_at', $captured);
        $this->assertNotNull($captured['accepted_at']);
    }

    public function testAcceptSwapForbiddenWhenCurrentUserIsNotTheTarget(): void
    {
        $swap = [
            'id' => 5, 'store_id' => 3, 'requester_id' => 1,
            'target_user_id' => 9, 'status' => 'pending', 'accepted_at' => null,
        ];
        $this->swapRequests->method('findById')->with(5)->willReturn($swap);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '5']);

        $this->expectException(ForbiddenException::class);
        $this->controller->acceptSwap($req);
    }

    public function testRefuseSwapReadsTargetUserIdColumn(): void
    {
        $swap = [
            'id' => 5, 'store_id' => 3, 'requester_id' => 1,
            'target_user_id' => 9, 'status' => 'pending', 'accepted_at' => null,
        ];
        $this->swapRequests->method('findById')->with(5)->willReturn($swap);

        $captured = null;
        $this->swapRequests->expects($this->once())->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d;
        });

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);
        $req->setRouteParams(['id' => '5']);

        $response = $this->controller->refuseSwap($req);

        $this->assertSame(302, $response->status());
        $this->assertSame('refused', $captured['status']);
    }
}
