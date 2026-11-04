<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use DateTimeImmutable;
use kintai\Core\Services\WeekRange;
use PHPUnit\Framework\TestCase;

final class WeekRangeTest extends TestCase
{
    public function testMondayStartFromWednesday(): void
    {
        $first = WeekRange::firstDay(new DateTimeImmutable('2026-11-04'), 1);
        $this->assertSame('2026-11-02', $first->format('Y-m-d'));
    }

    public function testSundayStartFromWednesday(): void
    {
        $first = WeekRange::firstDay(new DateTimeImmutable('2026-11-04'), 7);
        $this->assertSame('2026-11-01', $first->format('Y-m-d'));
    }

    public function testAnchorOnStartDayIsKept(): void
    {
        $first = WeekRange::firstDay(new DateTimeImmutable('2026-11-04'), 3);
        $this->assertSame('2026-11-04', $first->format('Y-m-d'));
    }

    public function testOutOfRangeStartDayIsClamped(): void
    {
        $first = WeekRange::firstDay(new DateTimeImmutable('2026-11-04'), 99);
        $this->assertSame('2026-11-01', $first->format('Y-m-d'));
    }
}
