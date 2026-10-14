<?php

declare(strict_types=1);

namespace kintai\Core\Extension\Legacy;

use kintai\Contract\Provider\PendingRequest;
use kintai\Contract\Provider\PendingRequestsProvider;
use kintai\Core\Container;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;

/**
 * Fournisseurs de demandes en attente que le Core utilise pour les bundles qui ne déclarent pas encore les leurs
 * (Bundle::pendingRequestsProviders(), contrat 1.9) : timeoff, shift-swap. Ils lisent les dépôts encore
 * liés dans le Core et reproduisent les tableaux affichés jusqu'ici. Disparaîtront à l'étape 5 du plan du contrat.
 */
final class LegacyPendingRequests
{
    /** @return array<string, PendingRequestsProvider> slug du bundle => fournisseur */
    public static function forBundles(Container $container): array
    {
        return [
            'timeoff'     => self::timeoff($container),
            'shift-swap'  => self::swaps($container),
        ];
    }

    private static function timeoff(Container $container): PendingRequestsProvider
    {
        return new class($container) extends LegacyProvider {
            public function key(): string { return 'timeoff'; }
            public function label(): string { return 'pending_timeoff'; }
            public function emptyLabel(): string { return 'no_pending_request'; }
            public function permission(): string { return 'timeoff.view'; }
            public function storeFeature(): ?string { return 'timeoff'; }
            public function order(): int { return 10; }
            public function manageUrl(): string { return route_url('admin.timeoff'); }

            public function pending(?array $storeIds): array
            {
                $requests = [];
                foreach ($this->container->make(TimeoffRequestRepositoryInterface::class)->findAll() as $r) {
                    $storeId = (int) ($r['store_id'] ?? 0);
                    if (($r['status'] ?? '') !== 'pending' || !LegacyPendingRequests::scoped($storeIds, $storeId)) {
                        continue;
                    }
                    $requests[] = new PendingRequest((int) $r['id'], $storeId, (int) ($r['user_id'] ?? 0), (string) ($r['created_at'] ?? ''), [
                        ['label' => '#', 'value' => (int) $r['id']],
                        ['label' => 'user', 'value' => (int) ($r['user_id'] ?? 0), 'style' => PendingRequest::USER],
                        ['label' => 'type', 'value' => (string) ($r['type'] ?? '—'), 'style' => PendingRequest::BADGE],
                        ['label' => 'from', 'value' => (string) ($r['start_date'] ?? '—')],
                        ['label' => 'to', 'value' => (string) ($r['end_date'] ?? '—')],
                    ]);
                }
                return $requests;
            }
        };
    }

    private static function swaps(Container $container): PendingRequestsProvider
    {
        return new class($container) extends LegacyProvider {
            public function key(): string { return 'swaps'; }
            public function label(): string { return 'pending_swaps'; }
            public function emptyLabel(): string { return 'no_pending_swap'; }
            public function permission(): string { return 'swaps.view'; }
            public function storeFeature(): ?string { return 'swaps'; }
            public function order(): int { return 20; }
            public function manageUrl(): string { return route_url('admin.swap_requests'); }

            public function pending(?array $storeIds): array
            {
                $requests = [];
                foreach ($this->container->make(ShiftSwapRequestRepositoryInterface::class)->findAll() as $r) {
                    $storeId = (int) ($r['store_id'] ?? 0);
                    if (($r['status'] ?? '') !== 'pending' || !LegacyPendingRequests::scoped($storeIds, $storeId)) {
                        continue;
                    }
                    $requests[] = new PendingRequest((int) $r['id'], $storeId, (int) ($r['requester_id'] ?? 0), (string) ($r['created_at'] ?? ''), [
                        ['label' => '#', 'value' => (int) $r['id']],
                        ['label' => 'requester', 'value' => (int) ($r['requester_id'] ?? 0), 'style' => PendingRequest::USER],
                        ['label' => 'target', 'value' => (int) ($r['target_user_id'] ?? 0), 'style' => PendingRequest::USER],
                        ['label' => 'shifts', 'value' => '#' . (int) ($r['requester_shift_id'] ?? 0)],
                    ]);
                }
                return $requests;
            }
        };
    }


    /** @internal Utilisé par les fournisseurs anonymes ci-dessus. */
    public static function scoped(?array $storeIds, int $storeId): bool
    {
        return $storeIds === null || in_array($storeId, $storeIds, true);
    }
}
