<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use kintai\Core\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\UserShiftTypeRateRepositoryInterface;
use kintai\Core\Services\StoreStatsService;

/**
 * Régression : multiStoreComparison() lisait des clés imbriquées
 * ($stats['planning']['totalShifts'], $stats['financial']['avgHourlyCost']...)
 * qui n'ont jamais existé dans le retour PLAT de storeStats() (n, totalNetHours,
 * avgCostPerHour...) — la méthode retournait donc des zéros/null partout, et
 * n'était appelée nulle part (code mort jusqu'ici).
 */
final class StoreStatsServiceMultiStoreComparisonTest extends TestCase
{
    private StoreRepositoryInterface $stores;
    private ShiftRepositoryInterface $shifts;
    private StoreUserRepositoryInterface $storeUsers;
    private StoreStatsService $service;

    protected function setUp(): void
    {
        $this->stores     = $this->createStub(StoreRepositoryInterface::class);
        $this->shifts     = $this->createStub(ShiftRepositoryInterface::class);
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);

        $shiftTypes = $this->createStub(ShiftTypeRepositoryInterface::class);
        $shiftTypes->method('findByStore')->willReturn([]);
        $userRates = $this->createStub(UserShiftTypeRateRepositoryInterface::class);

        $this->service = new StoreStatsService(
            $this->stores,
            $this->shifts,
            $shiftTypes,
            $this->storeUsers,
            $this->createStub(TimeoffRequestRepositoryInterface::class),
            $this->createStub(ShiftSwapRequestRepositoryInterface::class),
            $userRates,
            $this->createStub(UserRepositoryInterface::class),
            $this->createStub(DailyReportRepositoryInterface::class),
        );
    }

    public function testReturnsFlatMetricsPerStoreWithNames(): void
    {
        $this->stores->method('findById')->willReturnMap([
            [1, ['id' => 1, 'name' => 'Store A', 'currency' => 'JPY', 'currency_symbol_style' => 'kanji']],
            [2, ['id' => 2, 'name' => 'Store B']],
        ]);
        $this->storeUsers->method('findByStore')->willReturnMap([
            [1, [['user_id' => 10], ['user_id' => 11]]],
            [2, []],
        ]);
        $this->shifts->method('findByStore')->willReturnMap([
            [1, [
                [
                    'id' => 1, 'store_id' => 1, 'user_id' => 10, 'shift_date' => date('Y-m-d'),
                    'start_time' => '09:00', 'end_time' => '17:00', 'cross_midnight' => 0,
                    'duration_minutes' => 480, 'pause_minutes' => 60, 'deleted_at' => null,
                ],
            ]],
            [2, []],
        ]);

        $rows = $this->service->multiStoreComparison([1, 2], 30);

        $this->assertCount(2, $rows);
        $this->assertSame('Store A', $rows[0]['store_name']);
        $this->assertSame('JPY', $rows[0]['currency']);
        $this->assertSame(1, $rows[0]['total_shifts']);
        $this->assertSame(2, $rows[0]['employee_count']);
        $this->assertGreaterThan(0, $rows[0]['total_hours']);

        $this->assertSame('Store B', $rows[1]['store_name']);
        $this->assertSame('EUR', $rows[1]['currency'], 'store sans devise configurée retombe sur EUR par défaut');
        $this->assertSame(0, $rows[1]['total_shifts']);
        $this->assertSame(0, $rows[1]['employee_count']);
    }
}
