<?php

declare(strict_types=1);

namespace kintai\Core\Extension;

use kintai\Contract\Extension\DashboardStat;
use kintai\Core\BundleManager;
use kintai\Core\Container;
use kintai\Core\Services\Log;

/**
 * Cartes de statistiques du tableau de bord employé apportées par les bundles (point d'extension
 * Bundle::dashboardStats(), contrat 1.19) :
 * - celles déclarées par les bundles actifs ;
 * - en repli, celles du Core pour les bundles qui ne déclarent rien (aucune depuis l'extraction de timeoff).
 */
final class DashboardStats
{
    public function __construct(
        private readonly ?ExtensionRegistry $registry = null,
        private readonly ?BundleManager $bundles = null,
    ) {}

    /** Instance de la requête courante (registre des extensions et bundles actifs), ou aucune carte si indisponible. */
    public static function current(): self
    {
        try {
            $container = Container::getInstance();
            return new self(
                $container->has(ExtensionRegistry::class) ? $container->make(ExtensionRegistry::class) : null,
                $container->has(BundleManager::class) ? $container->make(BundleManager::class) : null,
            );
        } catch (\Throwable) {
            return new self();
        }
    }

    /**
     * Cartes historiques du Core, par slug de bundle.
     *
     * @param array<string, int> $legacyValues valeurs calculées par l'appelant (demandes en attente)
     * @return array<string, list<DashboardStat>>
     */
    public static function legacyBundleStats(array $legacyValues): array
    {
        return [
        ];
    }

    /**
     * Cartes à afficher, triées par ordre : déclarées par les bundles actifs, puis repli des bundles actifs qui ne
     * déclarent rien. Une clé déjà prise n'est pas reprise ; une carte dont la valeur lève une exception est retirée.
     *
     * @param array<string, mixed>    $context
     * @param callable(?string): bool $feat         Fonctionnalité par magasin active ?
     * @param array<string, int> $legacyValues
     * @return list<array{key: string, label: string, value: int|string, icon: string, tone: string}>
     */
    public function cards(array $context, callable $feat, array $legacyValues = []): array
    {
        $declared = $this->registry?->dashboardStatsByBundle() ?? [];
        $all = [];
        foreach ($declared as $stats) {
            array_push($all, ...$stats);
        }
        foreach (self::legacyBundleStats($legacyValues) as $slug => $legacy) {
            if (!isset($declared[$slug]) && $this->bundles?->isActive($slug)) {
                array_push($all, ...$legacy);
            }
        }
        usort($all, static fn(DashboardStat $a, DashboardStat $b): int => [$a->order, $a->key] <=> [$b->order, $b->key]);

        $cards = [];
        foreach ($all as $stat) {
            if (isset($cards[$stat->key]) || ($stat->storeFeature !== null && !$feat($stat->storeFeature))) {
                continue;
            }
            try {
                $value = ($stat->value)($context);
            } catch (\Throwable $e) {
                try {
                    Log::warning('bundle_extension_failed', ['point' => 'dashboardStats', 'key' => $stat->key, 'error' => $e->getMessage()]);
                } catch (\Throwable) {
                }
                continue;
            }
            $cards[$stat->key] = ['key' => $stat->key, 'label' => __($stat->label), 'value' => $value, 'icon' => $stat->icon, 'tone' => $stat->tone];
        }
        return array_values($cards);
    }
}
