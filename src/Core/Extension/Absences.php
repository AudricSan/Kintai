<?php

declare(strict_types=1);

namespace kintai\Core\Extension;

use kintai\Contract\Provider\Absence;
use kintai\Core\Container;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Services\Log;

/**
 * Absences des employés, lues à travers des fournisseurs (étape 3 du contrat de bundle) :
 * - celles des fournisseurs déclarés par les bundles actifs (Bundle::absenceProviders(), contrat 1.10), additionnées ;
 * - à défaut, en repli, les congés de la table historique (TimeoffRequestRepositoryInterface), comme jusqu'ici — y
 *   compris quand le bundle timeoff est désactivé. Ce repli disparaîtra avec l'extraction de timeoff (étape 4).
 *
 * Les lignes renvoyées gardent la forme historique (id, user_id, store_id, start_date, end_date, type, status) pour
 * que les calculs du Core (planning, iCal, statistiques) restent inchangés.
 */
final class Absences
{
    public function __construct(
        private readonly ?ExtensionRegistry $registry,
        private readonly ?TimeoffRequestRepositoryInterface $legacy,
    ) {}

    /** Instance de la requête courante, avec le dépôt historique de l'appelant comme repli. */
    public static function current(?TimeoffRequestRepositoryInterface $legacy): self
    {
        try {
            $container = Container::getInstance();
            $registry = $container->has(ExtensionRegistry::class) ? $container->make(ExtensionRegistry::class) : null;
        } catch (\Throwable) {
            $registry = null;
        }
        return new self($registry, $legacy);
    }

    /** @return list<array<string, mixed>> Absences de tous statuts de l'employé. */
    public function forUser(int $userId): array
    {
        return $this->rows([$userId], null) ?? ($this->legacy?->findByUser($userId) ?? []);
    }

    /** @return list<array<string, mixed>> Absences de tous statuts du magasin. */
    public function forStore(int $storeId): array
    {
        return $this->rows(null, [$storeId]) ?? ($this->legacy?->findByStore($storeId) ?? []);
    }

    /**
     * Absences des fournisseurs déclarés, ou null si aucun bundle actif n'en déclare (repli historique).
     *
     * @return list<array<string, mixed>>|null
     */
    private function rows(?array $userIds, ?array $storeIds): ?array
    {
        $bySlug = $this->registry?->absenceProvidersByBundle() ?? [];
        if ($bySlug === []) {
            return null;
        }

        $rows = [];
        foreach ($bySlug as $slug => $providers) {
            foreach ($providers as $provider) {
                try {
                    foreach ($provider->absences($userIds, $storeIds) as $absence) {
                        if ($absence instanceof Absence) {
                            $rows[] = $absence->toArray();
                        }
                    }
                } catch (\Throwable $e) {
                    try {
                        Log::warning('bundle_extension_failed', ['bundle' => $slug, 'point' => 'absenceProviders', 'error' => $e->getMessage()]);
                    } catch (\Throwable) {
                    }
                }
            }
        }
        return $rows;
    }
}
