<?php

declare(strict_types=1);

namespace kintai\Core\Extension;

use kintai\Contract\Provider\DailySales;
use kintai\Core\Container;
use kintai\Core\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Services\DailyReportDataNormalizer;
use kintai\Core\Services\Log;

/**
 * Ventes journalières des magasins, lues à travers des fournisseurs (contrat 1.14) — exposé aux bundles sous le nom
 * kintai\Contract\Services\Sales :
 * - celles des fournisseurs déclarés par les bundles actifs (Bundle::salesProviders()), réunies ;
 * - à défaut, en repli, la table historique des rapports journaliers (DailyReportRepositoryInterface), comme jusqu'ici
 *   — y compris quand le bundle daily-report est désactivé. Ce repli disparaîtra avec l'extraction de daily-report
 *   (étape 4) ; sans fournisseur, il n'y aura alors plus de ventes.
 */
final class Sales
{
    public function __construct(
        private readonly ?ExtensionRegistry $registry = null,
        private readonly ?DailyReportRepositoryInterface $legacy = null,
        private readonly ?StoreRepositoryInterface $stores = null,
    ) {}

    /** Instance de la requête courante (registre des extensions et repli historique du conteneur). */
    public static function current(): self
    {
        try {
            $container = Container::getInstance();
            return new self(
                $container->has(ExtensionRegistry::class) ? $container->make(ExtensionRegistry::class) : null,
                $container->has(DailyReportRepositoryInterface::class) ? $container->make(DailyReportRepositoryInterface::class) : null,
                $container->has(StoreRepositoryInterface::class) ? $container->make(StoreRepositoryInterface::class) : null,
            );
        } catch (\Throwable) {
            return new self();
        }
    }

    /**
     * Ventes du magasin pour chaque jour de la période (bornes incluses), en valeurs du jour, triées par date.
     *
     * @param list<string>|null $statuses Ne garder que les jours à ces statuts (avant la conversion en valeurs du jour)
     * @return list<array{report_date: string, status: string, sales_total: float, labor_cost: float, customer_count: int, waste_total: float}>
     */
    public function dailySales(int $storeId, string $from, string $to, ?array $statuses = null): array
    {
        $bySlug = $this->registry?->salesProvidersByBundle() ?? [];
        if ($bySlug === []) {
            return $this->legacyDailySales($storeId, $from, $to, $statuses);
        }

        $rows = [];
        foreach ($bySlug as $slug => $providers) {
            foreach ($providers as $provider) {
                try {
                    foreach ($provider->dailySales($storeId, $from, $to, $statuses) as $day) {
                        if ($day instanceof DailySales) {
                            $rows[] = $day->toArray();
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('bundle_provider_failed', ['bundle' => $slug, 'provider' => 'sales', 'error' => $e->getMessage()]);
                }
            }
        }
        usort($rows, static fn(array $a, array $b): int => strcmp($a['report_date'], $b['report_date']));
        return $rows;
    }

    /** Repli : rapports journaliers historiques, ramenés à des valeurs du jour selon le mode de saisie du magasin. */
    private function legacyDailySales(int $storeId, string $from, string $to, ?array $statuses): array
    {
        if ($this->legacy === null) {
            return [];
        }
        $reports = array_values(array_filter(
            $this->legacy->findByStoreAndDateRange($storeId, $from, $to),
            static fn(array $r): bool => empty($r['deleted_at']) && ($statuses === null || in_array($r['status'] ?? '', $statuses, true))
        ));
        usort($reports, static fn(array $a, array $b): int => strcmp((string) $a['report_date'], (string) $b['report_date']));

        $store = $this->stores?->findById($storeId);
        $reports = DailyReportDataNormalizer::toDailyDeltas($reports, $store !== null ? DailyReportDataNormalizer::cumulativeModeOf($store) : 'per_day');

        return array_map(static fn(array $r): array => (new DailySales(
            (string) $r['report_date'],
            (string) ($r['status'] ?? 'draft'),
            (float) ($r['sales_total'] ?? 0),
            (float) ($r['labor_cost'] ?? 0),
            (int) ($r['customer_count'] ?? 0),
            (float) ($r['waste_total'] ?? 0),
        ))->toArray(), $reports);
    }
}
