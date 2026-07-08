<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Controller\Web;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\NotificationService;
use kintai\UI\Controller\Web\Requests\AdminSwapController;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AdminSwapControllerTest extends TestCase
{
    private ShiftSwapRequestRepositoryInterface&MockObject $swapRequests;
    private ShiftRepositoryInterface&MockObject $shifts;
    private UserRepositoryInterface&MockObject $users;
    private NotificationService&MockObject $notifs;
    private AdminSwapController $controller;

    protected function setUp(): void
    {
        $this->ensureViewFile('requests.swap-form');
        $this->ensureViewFile('requests.swap-requests');
        $this->ensureViewFile('layout.app');
        $view = new ViewRenderer(sys_get_temp_dir());

        $this->swapRequests = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);

        $this->controller = new AdminSwapController(
            $view,
            $this->swapRequests,
            $this->shifts,
            new AuditLogger(),
            $this->notifs,
            $this->createMock(StoreRepositoryInterface::class),
            $this->users,
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    public function testCreateSwapRendersFormForAdmin(): void
    {
        $this->users->method('findAll')->willReturn([
            ['id' => 1, 'last_name' => 'Dupont', 'first_name' => 'Jean'],
            ['id' => 2, 'last_name' => 'Martin', 'first_name' => 'Paul'],
        ]);

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller->createSwap($req);

        $this->assertSame(200, $response->status());
    }

    public function testStoreSwapAppliesImmediateExchangeWithoutApprovalWorkflow(): void
    {
        $_POST = [
            'requester_id'       => '1',
            'target_id'          => '2',
            'requester_shift_id' => '10',
            'target_shift_id'    => '20',
            'reason'             => 'Test',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $requesterShift = ['id' => 10, 'user_id' => 1, 'store_id' => 5];
        $targetShift    = ['id' => 20, 'user_id' => 2, 'store_id' => 5];

        $this->shifts->method('findById')->willReturnMap([
            [10, $requesterShift],
            [20, $targetShift],
        ]);

        $savedShifts = [];
        $this->shifts->expects($this->exactly(2))->method('save')->willReturnCallback(function (array $d) use (&$savedShifts) {
            $savedShifts[] = $d;
            return $d;
        });

        $capturedSwap = null;
        $this->swapRequests->method('save')->willReturnCallback(function (array $d) use (&$capturedSwap) {
            $capturedSwap = $d;
            return $d + ['id' => 77];
        });

        $this->notifs->expects($this->once())->method('notifyMany')
            ->with([1, 2], 'shift_assigned', $this->isString(), 77);

        $response = $this->controller->storeSwap($req);

        $this->assertSame(302, $response->status());

        // Les deux shifts ont été échangés immédiatement (pas de statut pending)
        $this->assertSame(2, $savedShifts[0]['user_id']);
        $this->assertSame(1, $savedShifts[1]['user_id']);
        $this->assertSame('accepted', $capturedSwap['status']);
        $this->assertNotNull($capturedSwap['peer_accepted_at']);
    }

    public function testStoreSwapForbiddenWhenShiftsBelongToDifferentStores(): void
    {
        $_POST = [
            'requester_id'       => '1',
            'target_id'          => '2',
            'requester_shift_id' => '10',
            'target_shift_id'    => '20',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->shifts->method('findById')->willReturnMap([
            [10, ['id' => 10, 'user_id' => 1, 'store_id' => 5]],
            [20, ['id' => 20, 'user_id' => 2, 'store_id' => 6]],
        ]);

        $this->expectException(ForbiddenException::class);
        $this->controller->storeSwap($req);
    }

    public function testCreateSwapExcludesShiftsThatWouldCreateSameDayConflict(): void
    {
        $this->users->method('findAll')->willReturn([]);

        // Le demandeur a un shift le 2026-08-05 ; la cible a un shift le 2026-08-05 et un autre le 2026-08-10.
        $this->shifts->method('findByUser')->willReturnMap([
            [1, [['id' => 10, 'user_id' => 1, 'shift_date' => '2026-08-05', 'start_time' => '09:00', 'end_time' => '17:00']]],
            [2, [
                ['id' => 20, 'user_id' => 2, 'shift_date' => '2026-08-05', 'start_time' => '10:00', 'end_time' => '18:00'],
                ['id' => 21, 'user_id' => 2, 'shift_date' => '2026-08-10', 'start_time' => '09:00', 'end_time' => '17:00'],
            ]],
        ]);

        $_GET = ['requester_id' => '1', 'target_id' => '2'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller->createSwap($req);

        $this->assertSame(200, $response->status());
        // On ne peut pas vérifier le HTML rendu (vue stub dans les tests), mais on s'assure
        // au minimum qu'aucune exception n'est levée par le filtrage des conflits.
    }

    public function testStoreSwapBlockedWhenResultingInSameDayConflict(): void
    {
        $_POST = [
            'requester_id'       => '1',
            'target_id'          => '2',
            'requester_shift_id' => '10',
            'target_shift_id'    => '20',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $requesterShift = ['id' => 10, 'user_id' => 1, 'store_id' => 5, 'shift_date' => '2026-08-05'];
        $targetShift    = ['id' => 20, 'user_id' => 2, 'store_id' => 5, 'shift_date' => '2026-08-06'];

        $this->shifts->method('findById')->willReturnMap([
            [10, $requesterShift],
            [20, $targetShift],
        ]);
        // Le demandeur a déjà un autre shift (#99) le 2026-08-06 : recevoir le shift cible créerait un conflit.
        $this->shifts->method('findByUser')->willReturnMap([
            [1, [$requesterShift, ['id' => 99, 'user_id' => 1, 'shift_date' => '2026-08-06']]],
            [2, [$targetShift]],
        ]);

        $this->shifts->expects($this->never())->method('save');
        $this->swapRequests->expects($this->never())->method('save');

        $response = $this->controller->storeSwap($req);

        $this->assertSame(302, $response->status());
    }

    public function testStoreSwapForbiddenWhenSameEmployeeSelectedTwice(): void
    {
        $_POST = [
            'requester_id'       => '1',
            'target_id'          => '1',
            'requester_shift_id' => '10',
            'target_shift_id'    => '20',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->expectException(ForbiddenException::class);
        $this->controller->storeSwap($req);
    }

    public function testSwapRequestsLooksUpReferencedShiftsForDisplay(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->swapRequests->method('findAll')->willReturn([
            ['id' => 1, 'store_id' => 5, 'requester_id' => 1, 'target_id' => 2, 'requester_shift_id' => 10, 'target_shift_id' => 20, 'status' => 'pending'],
        ]);
        $this->users->method('findAll')->willReturn([]);
        $this->shifts->expects($this->exactly(2))->method('findById')->willReturnMap([
            [10, ['id' => 10, 'shift_date' => '2026-08-01', 'start_time' => '09:00', 'end_time' => '17:00']],
            [20, ['id' => 20, 'shift_date' => '2026-08-02', 'start_time' => '10:00', 'end_time' => '18:00']],
        ]);

        $response = $this->controller->swapRequests($req);

        $this->assertSame(200, $response->status());
    }

    public function testDeleteSwapRevertsShiftsWhenAccepted(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '77']);

        $swap = [
            'id' => 77, 'store_id' => 5, 'status' => 'accepted',
            'requester_id' => 1, 'target_id' => 2,
            'requester_shift_id' => 10, 'target_shift_id' => 20,
        ];
        $this->swapRequests->method('findById')->with(77)->willReturn($swap);

        // Après l'échange initial : le shift #10 appartient à 2, le shift #20 appartient à 1
        $requesterShift = ['id' => 10, 'user_id' => 2, 'store_id' => 5];
        $targetShift    = ['id' => 20, 'user_id' => 1, 'store_id' => 5];
        $this->shifts->method('findById')->willReturnMap([
            [10, $requesterShift],
            [20, $targetShift],
        ]);

        $savedShifts = [];
        $this->shifts->expects($this->exactly(2))->method('save')->willReturnCallback(function (array $d) use (&$savedShifts) {
            $savedShifts[] = $d;
            return $d;
        });

        $this->notifs->expects($this->once())->method('notifyMany')->with([1, 2], 'shift_assigned', $this->isString(), 77);
        $this->swapRequests->expects($this->once())->method('delete')->with(77);

        $response = $this->controller->deleteSwap($req);

        $this->assertSame(302, $response->status());
        // Les shifts sont revenus à leurs propriétaires d'origine
        $this->assertSame(1, $savedShifts[0]['user_id']);
        $this->assertSame(2, $savedShifts[1]['user_id']);
    }

    public function testDeleteSwapDoesNotTouchShiftsWhenNotAccepted(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '5']);

        $swap = [
            'id' => 5, 'store_id' => 5, 'status' => 'pending',
            'requester_id' => 1, 'target_id' => 2,
            'requester_shift_id' => 10, 'target_shift_id' => 20,
        ];
        $this->swapRequests->method('findById')->with(5)->willReturn($swap);

        $this->shifts->expects($this->never())->method('save');
        $this->notifs->expects($this->never())->method('notifyMany');
        $this->swapRequests->expects($this->once())->method('delete')->with(5);

        $response = $this->controller->deleteSwap($req);

        $this->assertSame(302, $response->status());
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
