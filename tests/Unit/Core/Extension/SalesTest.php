<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core\Extension;

use kintai\Contract\Provider\DailySales;
use kintai\Contract\Provider\SalesProvider;
use kintai\Core\BundleContract\Bundle;
use kintai\Core\BundleManager;
use kintai\Core\Extension\ExtensionRegistry;
use kintai\Core\Extension\Sales;
use kintai\Core\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Ventes journalières lues à travers des fournisseurs (contrat 1.14) : fournisseurs des bundles actifs, sinon repli
 * sur les rapports journaliers historiques (valeurs du jour, mode cumulé, filtre de statut).
 */
final class SalesTest extends TestCase
{
    /** @param array<string, mixed> $active slug => fournisseurs déclarés (list, ou \Throwable levée) */
    private function registry(array $active): ExtensionRegistry
    {
        $bundles = [];
        foreach ($active as $slug => $declared) {
            $bundle = new class extends Bundle {
                public string $slug = '';
                public mixed $declared = [];

                public function __construct() {}

                public function getName(): string
                {
                    return $this->slug;
                }

                public function register(): void {}

                public function salesProviders(): array
                {
                    if ($this->declared instanceof \Throwable) {
                        throw $this->declared;
                    }
                    return $this->declared;
                }
            };
            $bundle->slug = $slug;
            $bundle->declared = $declared;
            $bundles['B' . $slug] = $bundle;
        }
        $manager = (new \ReflectionClass(BundleManager::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($manager, 'bundles'))->setValue($manager, $bundles);

        return new ExtensionRegistry($manager);
    }

    /** @param list<DailySales> $days */
    private static function provider(array $days, array &$calls = []): SalesProvider
    {
        return new class($days, $calls) implements SalesProvider {
            public function __construct(private readonly array $days, private array &$calls) {}

            public function dailySales(int $storeId, string $from, string $to, ?array $statuses = null): array
            {
                $this->calls[] = [$storeId, $from, $to, $statuses];
                return $this->days;
            }
        };
    }

    private function legacy(array $reports, string $mode = 'per_day'): Sales
    {
        $repo = $this->createStub(DailyReportRepositoryInterface::class);
        $repo->method('findByStoreAndDateRange')->willReturn($reports);
        $stores = $this->createStub(StoreRepositoryInterface::class);
        $stores->method('findById')->willReturn(['id' => 4, 'daily_report_settings' => json_encode(['cumulative_mode' => $mode])]);

        return new Sales($this->registry([]), $repo, $stores);
    }

    public function testDeclaredProvidersAreMergedAndSortedByDate(): void
    {
        $calls = [];
        $sales = new Sales($this->registry([
            'daily-report' => [self::provider([new DailySales('2026-10-02', 'validated', 120.0, 40.0, 12)], $calls)],
            'acme'         => [self::provider([new DailySales('2026-10-01', 'draft', 80.5)]), 'pas un fournisseur'],
            'boom'         => [new class implements SalesProvider {
                public function dailySales(int $storeId, string $from, string $to, ?array $statuses = null): array
                {
                    throw new \RuntimeException('kaput');
                }
            }],
        ]), $this->createStub(DailyReportRepositoryInterface::class));

        $rows = $sales->dailySales(4, '2026-10-01', '2026-10-31', ['validated']);

        $this->assertSame(['2026-10-01', '2026-10-02'], array_column($rows, 'report_date'));
        $this->assertSame(
            ['report_date' => '2026-10-02', 'status' => 'validated', 'sales_total' => 120.0, 'labor_cost' => 40.0, 'customer_count' => 12, 'waste_total' => 0.0],
            $rows[1],
        );
        $this->assertSame([[4, '2026-10-01', '2026-10-31', ['validated']]], $calls, 'période et statuts transmis');
    }

    /** Sans fournisseur : les rapports journaliers historiques, triés, sans les supprimés. */
    public function testLegacyFallbackReadsTheHistoricalDailyReports(): void
    {
        $rows = $this->legacy([
            ['report_date' => '2026-10-02', 'status' => 'validated', 'sales_total' => '200', 'labor_cost' => '50', 'customer_count' => '20'],
            ['report_date' => '2026-10-01', 'status' => 'draft', 'sales_total' => '100', 'labor_cost' => '30', 'customer_count' => '10'],
            ['report_date' => '2026-10-03', 'status' => 'validated', 'sales_total' => '999', 'deleted_at' => '2026-10-04 00:00:00'],
        ])->dailySales(4, '2026-10-01', '2026-10-31');

        $this->assertSame(['2026-10-01', '2026-10-02'], array_column($rows, 'report_date'));
        $this->assertSame([100.0, 200.0], array_column($rows, 'sales_total'));
        $this->assertSame([10, 20], array_column($rows, 'customer_count'));
    }

    /** Saisie cumulée : valeurs du jour par différence, calculées après le filtre de statut (comme le rapport de salaire). */
    public function testLegacyCumulativeInputIsConvertedAfterTheStatusFilter(): void
    {
        $sales = $this->legacy([
            ['report_date' => '2026-10-01', 'status' => 'validated', 'sales_total' => '100'],
            ['report_date' => '2026-10-02', 'status' => 'draft', 'sales_total' => '250'],
            ['report_date' => '2026-10-03', 'status' => 'submitted', 'sales_total' => '400'],
        ], 'cumulative_input');

        $this->assertSame([100.0, 150.0, 150.0], array_column($sales->dailySales(4, '2026-10-01', '2026-10-31'), 'sales_total'));
        $this->assertSame([100.0, 300.0], array_column($sales->dailySales(4, '2026-10-01', '2026-10-31', ['validated', 'submitted']), 'sales_total'));
    }

    public function testNothingWithoutProviderNorLegacy(): void
    {
        $this->assertSame([], (new Sales())->dailySales(4, '2026-10-01', '2026-10-31'));
    }

    public function testInvalidDateIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DailySales('01/10/2026', 'draft', 1.0);
    }
}
