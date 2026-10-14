<?php

declare(strict_types=1);

namespace kintai\Core\Extension;

use kintai\Contract\Provider\Swap;
use kintai\Core\Container;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Services\Log;

/**
 * Demandes d'échange de shifts, lues à travers des fournisseurs (contrat 1.18) :
 * - celles des fournisseurs déclarés par les bundles actifs (Bundle::swapProviders()), réunies ;
 * - à défaut, en repli, la table historique des échanges (ShiftSwapRequestRepositoryInterface), comme jusqu'ici — y
 *   compris quand le bundle shift-swap est désactivé. Ce repli disparaîtra avec l'extraction de shift-swap (étape 4) ;
 *   sans fournisseur, il n'y aura alors plus d'échanges à compter.
 *
 * Les lignes renvoyées gardent la forme historique (id, store_id, requester_id, target_user_id, created_at, status).
 */
final class Swaps
{
    public function __construct(
        private readonly ?ExtensionRegistry $registry,
        private readonly ?ShiftSwapRequestRepositoryInterface $legacy,
    ) {}

    /** Instance de la requête courante, avec le dépôt historique de l'appelant comme repli. */
    public static function current(?ShiftSwapRequestRepositoryInterface $legacy): self
    {
        try {
            $container = Container::getInstance();
            $registry = $container->has(ExtensionRegistry::class) ? $container->make(ExtensionRegistry::class) : null;
        } catch (\Throwable) {
            $registry = null;
        }
        return new self($registry, $legacy);
    }

    /** @return list<array<string, mixed>> Toutes les demandes d'échange du magasin. */
    public function forStore(int $storeId): array
    {
        $bySlug = $this->registry?->swapProvidersByBundle() ?? [];
        if ($bySlug === []) {
            return $this->legacy?->findByStore($storeId) ?? [];
        }

        $rows = [];
        foreach ($bySlug as $slug => $providers) {
            foreach ($providers as $provider) {
                try {
                    foreach ($provider->swaps([$storeId]) as $swap) {
                        if ($swap instanceof Swap) {
                            $rows[] = $swap->toArray();
                        }
                    }
                } catch (\Throwable $e) {
                    try {
                        Log::warning('bundle_provider_failed', ['bundle' => $slug, 'provider' => 'swaps', 'error' => $e->getMessage()]);
                    } catch (\Throwable) {
                    }
                }
            }
        }
        return $rows;
    }
}
