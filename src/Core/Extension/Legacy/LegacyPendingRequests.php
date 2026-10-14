<?php

declare(strict_types=1);

namespace kintai\Core\Extension\Legacy;

use kintai\Contract\Provider\PendingRequest;
use kintai\Contract\Provider\PendingRequestsProvider;
use kintai\Core\Container;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;

/**
 * Fournisseurs de demandes en attente que le Core utilise pour les bundles qui ne déclarent pas encore les leurs
 * (Bundle::pendingRequestsProviders(), contrat 1.9) : aucun depuis l'extraction de timeoff. Ils lisent les dépôts encore
 * liés dans le Core et reproduisent les tableaux affichés jusqu'ici. Disparaîtront à l'étape 5 du plan du contrat.
 */
final class LegacyPendingRequests
{
    /** @return array<string, PendingRequestsProvider> slug du bundle => fournisseur */
    public static function forBundles(Container $container): array
    {
        return [
        ];
    }



    /** @internal Utilisé par les fournisseurs anonymes ci-dessus. */
    public static function scoped(?array $storeIds, int $storeId): bool
    {
        return $storeIds === null || in_array($storeId, $storeIds, true);
    }
}
