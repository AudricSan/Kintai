<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\Requests;

use kintai\Core\Auth\PermissionService;
use kintai\Core\Container;
use kintai\Core\Repositories\ShiftClaimRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\UI\Controller\Web\HasAdminAccess;
use kintai\UI\ViewRenderer;

/**
 * Page de résumé "Demandes" : agrège les demandes en attente des différents
 * types (congés, échanges de shifts, candidatures bourse aux shifts) sur une
 * seule page, avec des liens vers les pages dédiées de chaque bundle pour le
 * traitement (approuver/refuser). Ne duplique aucune logique métier.
 */
final class AdminRequestsController
{
    use HasAdminAccess;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly UserRepositoryInterface $users,
        private readonly StoreRepositoryInterface $stores,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly ShiftRepositoryInterface $shifts,
        private readonly TimeoffRequestRepositoryInterface $timeoffRequests,
        private readonly ShiftSwapRequestRepositoryInterface $swapRequests,
        private readonly PermissionService $permissions,
    ) {}

    public function index(Request $request): Response
    {
        $user       = $request->getAttribute('auth_user');
        $managedIds = $this->managedIds($request);
        $inScope    = fn(int $storeId): bool => $managedIds === null || in_array($storeId, $managedIds, true);

        $isOwner = !empty($user['is_admin']);
        $can     = fn(string $key): bool => $isOwner || $this->permissions->can($user, $key, null);

        $usersMap  = $this->buildUsersMap();
        $storesMap = $this->buildStoresMap($managedIds);

        // Congés en attente
        $timeoff = null;
        if (feat_bundle('timeoff') && $can('timeoff.view')) {
            $timeoff = array_values(array_filter(
                $this->timeoffRequests->findAll(),
                fn($r) => ($r['status'] ?? '') === 'pending' && $inScope((int) ($r['store_id'] ?? 0))
            ));
        }

        // Échanges de shifts en attente
        $swaps = null;
        if (feat_bundle('swaps') && $can('swaps.view')) {
            $swaps = array_values(array_filter(
                $this->swapRequests->findAll(),
                fn($r) => ($r['status'] ?? '') === 'pending' && $inScope((int) ($r['store_id'] ?? 0))
            ));
        }

        // Candidatures en attente sur la bourse aux shifts. ShiftClaimRepositoryInterface
        // n'est lié au container que si le bundle "shift-claim" est actif (voir
        // ShiftClaimBundle) : résolution différée, jamais en dépendance de constructeur
        // ici, sinon la page entière casserait pour tout le monde bundle désactivé.
        $claims = null;
        $container = Container::getInstance();
        if (feat_bundle('open_shifts') && $can('open_shifts.view') && $container->has(ShiftClaimRepositoryInterface::class)) {
            $shiftClaims = $container->make(ShiftClaimRepositoryInterface::class);
            $storeIds    = $managedIds ?? array_map(fn($s) => (int) $s['id'], $this->stores->findAll());
            $claims      = [];
            foreach ($storeIds as $storeId) {
                foreach ($shiftClaims->findPendingByStore($storeId) as $claim) {
                    $shift = $this->shifts->findById((int) ($claim['shift_id'] ?? 0));
                    $claims[] = [
                        'id'         => $claim['id'],
                        'user_id'    => $claim['user_id'] ?? null,
                        'store_id'   => $storeId,
                        'shift_id'   => $claim['shift_id'] ?? null,
                        'shift_date' => $shift['shift_date'] ?? null,
                        'start_time' => $shift['start_time'] ?? null,
                        'end_time'   => $shift['end_time'] ?? null,
                    ];
                }
            }
            usort($claims, fn($a, $b) => strcmp((string) $a['shift_date'], (string) $b['shift_date']));
        }

        return Response::html($this->view->render('requests.index', [
            'title'      => __('requests'),
            'timeoff'    => $timeoff,
            'swaps'      => $swaps,
            'claims'     => $claims,
            'users_map'  => $usersMap,
            'stores_map' => $storesMap,
        ], 'layout.app'));
    }
}
