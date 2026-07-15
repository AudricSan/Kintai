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
 * Couvre spécifiquement storeProfitability() vis-à-vis du mode de saisie
 * cumulatif du rapport journalier (item 4 : les valeurs cumulées ne doivent
 * pas être sommées telles quelles sur la plage).
 */
final class StoreStatsServiceProfitabilityTest extends TestCase
{
    private DailyReportRepositoryInterface $dailyReports;
    private StoreRepositoryInterface $stores;
    private StoreStatsService $service;

    protected function setUp(): void
    {
        $this->stores = $this->createStub(StoreRepositoryInterface::class);
        $this->dailyReports = $this->createStub(DailyReportRepositoryInterface::class);

        $shifts     = $this->createStub(ShiftRepositoryInterface::class);
        $shifts->method('findByStore')->willReturn([]);
        $shiftTypes = $this->createStub(ShiftTypeRepositoryInterface::class);
        $shiftTypes->method('findByStore')->willReturn([]);
        $storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $storeUsers->method('findByStore')->willReturn([]);
        $userRates  = $this->createStub(UserShiftTypeRateRepositoryInterface::class);

        $this->service = new StoreStatsService(
            $this->stores,
            $shifts,
            $shiftTypes,
            $storeUsers,
            $this->createStub(TimeoffRequestRepositoryInterface::class),
            $this->createStub(ShiftSwapRequestRepositoryInterface::class),
            $userRates,
            $this->createStub(UserRepositoryInterface::class),
            $this->dailyReports,
        );
    }

    private function reports(): array
    {
        return [
            ['report_date' => '2026-07-01', 'sales_total' => 1000, 'customer_count' => 10, 'labor_cost' => 200, 'waste_total' => 5, 'status' => 'validated'],
            ['report_date' => '2026-07-02', 'sales_total' => 2100, 'customer_count' => 22, 'labor_cost' => 410, 'waste_total' => 11, 'status' => 'validated'],
            ['report_date' => '2026-07-03', 'sales_total' => 3300, 'customer_count' => 33, 'labor_cost' => 610, 'waste_total' => 15, 'status' => 'validated'],
        ];
    }

    public function testPerDayModeSumsRawValues(): void
    {
        $this->stores->method('findById')->willReturn(['id' => 1, 'daily_report_settings' => null]);
        $this->dailyReports->method('findByStoreAndDateRange')->willReturn($this->reports());

        $result = $this->service->storeProfitability(1, '2026-07-01', '2026-07-03');

        // Mode par défaut (per_day) : somme brute des 3 jours = 1000 + 2100 + 3300
        $this->assertSame(6400.0, $result['totalSales']);
    }

    public function testCumulativeInputModeUsesDailyDeltasInsteadOfRawSum(): void
    {
        $store = ['id' => 1, 'daily_report_settings' => json_encode(['cumulative_mode' => 'cumulative_input'])];
        $this->stores->method('findById')->willReturn($store);
        $this->dailyReports->method('findByStoreAndDateRange')->willReturn($this->reports());

        $result = $this->service->storeProfitability(1, '2026-07-01', '2026-07-03');

        // Valeurs cumulées 1000/2100/3300 → deltas 1000/1100/1200 → somme réelle = 3300 (= dernière valeur cumulée)
        $this->assertSame(3300.0, $result['totalSales']);
    }

    public function testCumulativeInputModePerDayRowsReflectDeltas(): void
    {
        $store = ['id' => 1, 'daily_report_settings' => json_encode(['cumulative_mode' => 'cumulative_input'])];
        $this->stores->method('findById')->willReturn($store);
        $this->dailyReports->method('findByStoreAndDateRange')->willReturn($this->reports());

        $result = $this->service->storeProfitability(1, '2026-07-01', '2026-07-03');

        $this->assertSame(1000.0, $result['rows'][0]['sales']);
        $this->assertSame(1100.0, $result['rows'][1]['sales']);
        $this->assertSame(1200.0, $result['rows'][2]['sales']);
    }
}
