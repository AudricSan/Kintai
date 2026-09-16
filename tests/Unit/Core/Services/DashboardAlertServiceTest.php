<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core\Services;

use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Services\DashboardAlertService;
use PHPUnit\Framework\TestCase;

/**
 * Les fixtures sont des propriétés mutables lues via willReturnCallback (plutôt que
 * plusieurs ->method()->willReturn() successifs sur le même mock) : PHPUnit ne garantit
 * pas qu'un second stub configuré dans un test override celui du setUp() pour la même
 * méthode sans contrainte with(), donc chaque test doit pouvoir personnaliser les
 * données sans re-stubber le mock.
 */
final class DashboardAlertServiceTest extends TestCase
{
    /** @var array<string, array> date (Y-m-d) => shifts renvoyés par findAllByDate() pour cette date */
    private array $shiftsByDate = [];

    /** @var array<int, array> store_id => membres (store_user) */
    private array $storeMembers = [];

    /** @var array<int, array|null> user_id => utilisateur */
    private array $usersById = [];

    private array $timeoffRequestsFixture = [];
    private array $swapRequestsFixture = [];
    private array $timeclocksFixture = [];

    protected function setUp(): void
    {
        $this->shiftsByDate = [];
        $this->storeMembers = [];
        $this->usersById = [];
        $this->timeoffRequestsFixture = [];
        $this->swapRequestsFixture = [];
        $this->timeclocksFixture = [];
    }

    private function service(): DashboardAlertService
    {
        $shifts = $this->createMock(ShiftRepositoryInterface::class);
        $shifts->method('findAllByDate')->willReturnCallback(
            fn(string $date) => $this->shiftsByDate[$date] ?? []
        );

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findById')->willReturnCallback(
            fn(int $id) => $this->usersById[$id] ?? null
        );

        $storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $storeUsers->method('findByStore')->willReturnCallback(
            fn(int $storeId) => $this->storeMembers[$storeId] ?? []
        );

        $timeoffRequests = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $timeoffRequests->method('findAll')->willReturnCallback(fn() => $this->timeoffRequestsFixture);

        $swapRequests = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $swapRequests->method('findAll')->willReturnCallback(fn() => $this->swapRequestsFixture);

        $timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $timeclocks->method('findAll')->willReturnCallback(fn() => $this->timeclocksFixture);

        return new DashboardAlertService($shifts, $users, $storeUsers, $timeoffRequests, $swapRequests, $timeclocks);
    }

    public function testDetectsUnfilledShiftInScopeWithinUpcomingWindow(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $this->shiftsByDate[$tomorrow] = [
            ['id' => 42, 'store_id' => 1, 'user_id' => null, 'shift_date' => $tomorrow, 'start_time' => '09:00', 'end_time' => '17:00'],
        ];

        $result = $this->service()->build([1], [1 => 'Store 1']);

        $this->assertCount(1, $result['unfilled_shifts']);
        $this->assertSame(42, $result['unfilled_shifts'][0]['id']);
        $this->assertSame('Store 1', $result['unfilled_shifts'][0]['store_name']);
    }

    public function testIgnoresUnfilledShiftOutsideScope(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $this->shiftsByDate[$tomorrow] = [
            ['id' => 42, 'store_id' => 2, 'user_id' => null, 'shift_date' => $tomorrow, 'start_time' => '09:00', 'end_time' => '17:00'],
        ];

        $result = $this->service()->build([1], [1 => 'Store 1', 2 => 'Store 2']);

        $this->assertSame([], $result['unfilled_shifts']);
    }

    public function testIgnoresAssignedShiftAsUnfilled(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $this->shiftsByDate[$tomorrow] = [
            ['id' => 42, 'store_id' => 1, 'user_id' => 7, 'shift_date' => $tomorrow, 'start_time' => '09:00', 'end_time' => '17:00'],
        ];

        $result = $this->service()->build([1], [1 => 'Store 1']);

        $this->assertSame([], $result['unfilled_shifts']);
    }

    public function testDetectsActiveUserWithoutShiftThisWeek(): void
    {
        // Aucun shift cette semaine (shiftsByDate reste vide pour toutes les dates).
        $this->storeMembers[1] = [['user_id' => 7]];
        $this->usersById[7] = ['id' => 7, 'display_name' => 'Bob', 'is_active' => 1];

        $result = $this->service()->build([1], [1 => 'Store 1']);

        $this->assertCount(1, $result['users_without_shift']);
        $this->assertSame(7, $result['users_without_shift'][0]['id']);
    }

    public function testExcludesInactiveUserFromUsersWithoutShift(): void
    {
        $this->storeMembers[1] = [['user_id' => 7]];
        $this->usersById[7] = ['id' => 7, 'display_name' => 'Bob', 'is_active' => 0];

        $result = $this->service()->build([1], [1 => 'Store 1']);

        $this->assertSame([], $result['users_without_shift']);
    }

    public function testExcludesUserWithAShiftThisWeekFromUsersWithoutShift(): void
    {
        $monday = date('Y-m-d', strtotime('monday this week'));
        $this->shiftsByDate[$monday] = [
            ['id' => 1, 'store_id' => 1, 'user_id' => 7, 'shift_date' => $monday, 'start_time' => '09:00', 'end_time' => '17:00'],
        ];
        $this->storeMembers[1] = [['user_id' => 7]];
        $this->usersById[7] = ['id' => 7, 'display_name' => 'Bob', 'is_active' => 1];

        $result = $this->service()->build([1], [1 => 'Store 1']);

        $this->assertSame([], $result['users_without_shift']);
    }

    public function testDetectsStaleTimeoffAndSwapRequests(): void
    {
        $old = date('Y-m-d H:i:s', strtotime('-5 days'));
        $recent = date('Y-m-d H:i:s', strtotime('-1 day'));

        $this->timeoffRequestsFixture = [
            ['id' => 1, 'store_id' => 1, 'status' => 'pending', 'created_at' => $old],
            ['id' => 2, 'store_id' => 1, 'status' => 'pending', 'created_at' => $recent],
            ['id' => 3, 'store_id' => 1, 'status' => 'approved', 'created_at' => $old],
            ['id' => 4, 'store_id' => 2, 'status' => 'pending', 'created_at' => $old],
        ];
        $this->swapRequestsFixture = [
            ['id' => 10, 'store_id' => 1, 'status' => 'pending', 'created_at' => $old],
        ];

        $result = $this->service()->build([1], [1 => 'Store 1']);

        $this->assertCount(2, $result['stale_requests']);
        $ids = array_column($result['stale_requests'], 'id');
        $this->assertContains(1, $ids);
        $this->assertContains(10, $ids);
    }

    public function testDetectsStaleOpenTimeclock(): void
    {
        $old = date('Y-m-d H:i:s', strtotime('-13 hours'));
        $recent = date('Y-m-d H:i:s', strtotime('-1 hour'));

        $this->timeclocksFixture = [
            ['id' => 1, 'store_id' => 1, 'user_id' => 7, 'clock_in_time' => $old, 'clock_out_time' => null],
            ['id' => 2, 'store_id' => 1, 'user_id' => 8, 'clock_in_time' => $recent, 'clock_out_time' => null],
            ['id' => 3, 'store_id' => 1, 'user_id' => 9, 'clock_in_time' => $old, 'clock_out_time' => $old],
        ];
        $this->usersById[7] = ['id' => 7, 'display_name' => 'Bob', 'is_active' => 1];

        $result = $this->service()->build([1], [1 => 'Store 1']);

        $this->assertCount(1, $result['stale_timeclocks']);
        $this->assertSame(1, $result['stale_timeclocks'][0]['id']);
        $this->assertSame('Bob', $result['stale_timeclocks'][0]['user_name']);
    }
}
