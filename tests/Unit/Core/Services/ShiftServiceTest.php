<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core\Services;

use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Services\ShiftService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ShiftServiceTest extends TestCase
{
    private TimeoffRequestRepositoryInterface&MockObject $timeoffRequests;
    private ShiftService $service;

    protected function setUp(): void
    {
        $this->timeoffRequests = $this->createMock(TimeoffRequestRepositoryInterface::class);

        $this->service = new ShiftService(
            $this->createMock(ShiftRepositoryInterface::class),
            $this->createMock(ShiftTypeRepositoryInterface::class),
            $this->createMock(UserRepositoryInterface::class),
            $this->timeoffRequests,
        );
    }

    public function testGetTimeoffForCalendarExpandsApprovedRequestOverEachDay(): void
    {
        $this->timeoffRequests->method('findByUser')->with(5)->willReturn([
            ['status' => 'approved', 'start_date' => '2026-08-01', 'end_date' => '2026-08-03', 'type' => 'vacation'],
        ]);

        $result = $this->service->getTimeoffForCalendar([5], '2026-08-01', '2026-08-31');

        $this->assertArrayHasKey('2026-08-01', $result);
        $this->assertArrayHasKey('2026-08-02', $result);
        $this->assertArrayHasKey('2026-08-03', $result);
        $this->assertArrayNotHasKey('2026-08-04', $result);
        $this->assertSame(5, $result['2026-08-01'][0]['user_id']);
        $this->assertSame('vacation', $result['2026-08-01'][0]['type']);
    }

    public function testGetTimeoffForCalendarIgnoresNonApprovedRequests(): void
    {
        $this->timeoffRequests->method('findByUser')->with(5)->willReturn([
            ['status' => 'pending', 'start_date' => '2026-08-01', 'end_date' => '2026-08-03', 'type' => 'vacation'],
        ]);

        $result = $this->service->getTimeoffForCalendar([5], '2026-08-01', '2026-08-31');

        $this->assertSame([], $result);
    }

    public function testGetTimeoffForCalendarClipsRangeToRequestedWindow(): void
    {
        $this->timeoffRequests->method('findByUser')->with(5)->willReturn([
            ['status' => 'approved', 'start_date' => '2026-07-28', 'end_date' => '2026-08-02', 'type' => 'sick'],
        ]);

        $result = $this->service->getTimeoffForCalendar([5], '2026-08-01', '2026-08-31');

        $this->assertArrayNotHasKey('2026-07-28', $result);
        $this->assertArrayHasKey('2026-08-01', $result);
        $this->assertArrayHasKey('2026-08-02', $result);
    }
}
