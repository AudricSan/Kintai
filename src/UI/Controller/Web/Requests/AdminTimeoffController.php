<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\Requests;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\NotificationService;
use kintai\UI\Controller\Web\HasAdminAccess;
use kintai\UI\ViewRenderer;

final class AdminTimeoffController
{
    use HasAdminAccess;
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly TimeoffRequestRepositoryInterface $timeoffRequests,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifs,
        private readonly StoreRepositoryInterface $stores,
        private readonly UserRepositoryInterface $users,
        private readonly StoreUserRepositoryInterface $storeUsers,
    ) {}

    public function timeoff(Request $request): Response
    {
        $managedIds = $this->managedIds($request);

        $usersMap  = $this->buildUsersMap();
        $storesMap = $this->buildStoresMap($managedIds);
        $requests  = $this->filterByStore($this->timeoffRequests->findAll(), $managedIds);

        $sort = $request->query('sort') ?? 'date_desc';
        usort($requests, function ($a, $b) use ($sort, $usersMap) {
            $dateA  = ($a['start_date'] ?? '');
            $dateB  = ($b['start_date'] ?? '');
            $userA  = strtolower($usersMap[(int)($a['user_id'] ?? 0)] ?? '');
            $userB  = strtolower($usersMap[(int)($b['user_id'] ?? 0)] ?? '');
            $typeA  = $a['type'] ?? '';
            $typeB  = $b['type'] ?? '';
            $statA  = $a['status'] ?? '';
            $statB  = $b['status'] ?? '';
            return match ($sort) {
                'date_asc'    => strcmp($dateA, $dateB),
                'user_asc'    => strcmp($userA, $userB) ?: strcmp($dateA, $dateB),
                'user_desc'   => strcmp($userB, $userA) ?: strcmp($dateA, $dateB),
                'type_asc'    => strcmp($typeA, $typeB) ?: strcmp($dateA, $dateB),
                'type_desc'   => strcmp($typeB, $typeA) ?: strcmp($dateA, $dateB),
                'status_asc'  => strcmp($statA, $statB) ?: strcmp($dateA, $dateB),
                'status_desc' => strcmp($statB, $statA) ?: strcmp($dateA, $dateB),
                default       => strcmp($dateB, $dateA),
            };
        });

        return Response::html($this->view->render('requests.timeoff', [
            'title'      => 'Congés',
            'requests'   => $requests,
            'users_map'  => $usersMap,
            'stores_map' => $storesMap,
            'sort'       => $sort,
        ], 'layout.app'));
    }

    /**
     * GET /admin/timeoff/create — formulaire pour créer un congé pour un employé,
     * sans demande préalable de sa part (ex : congé imposé, arrêt maladie déclaré par un tiers, etc.).
     */
    public function createTimeoff(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        $userIds    = $managedIds !== null ? $this->memberUserIds($managedIds) : null;
        $users      = $userIds !== null
            ? array_values(array_filter($this->users->findAll(), fn($u) => in_array((int) $u['id'], $userIds, true)))
            : $this->users->findAll();

        usort($users, fn($a, $b) => strcmp(
            strtolower(trim(($a['last_name'] ?? '') . ' ' . ($a['first_name'] ?? ''))),
            strtolower(trim(($b['last_name'] ?? '') . ' ' . ($b['first_name'] ?? '')))
        ));

        return Response::html($this->view->render('requests.timeoff-form', [
            'title' => __('create_timeoff'),
            'users' => $users,
            'today' => date('Y-m-d'),
        ], 'layout.app'));
    }

    /**
     * POST /admin/timeoff/create — enregistre un congé directement au statut "approved"
     * pour qu'il apparaisse immédiatement au calendrier.
     */
    public function storeTimeoffForEmployee(Request $request): Response
    {
        $userId = (int) $request->post('user_id', 0);
        $user   = $userId > 0 ? $this->users->findById($userId) : null;
        if ($user === null) {
            throw new NotFoundException('Employé introuvable.');
        }

        $managedIds = $this->managedIds($request);
        if ($managedIds !== null && !in_array($userId, $this->memberUserIds($managedIds), true)) {
            throw new ForbiddenException('Vous n\'êtes pas gestionnaire de cet employé.');
        }

        $memberships = $this->storeUsers->findByUser($userId);
        $storeId     = $memberships ? (int) $memberships[0]['store_id'] : 0;
        $this->assertStoreAccess($request, $storeId);

        $validTypes = ['vacation', 'sick', 'personal', 'unpaid', 'other'];
        $type       = $request->post('type', 'vacation');
        if (!in_array($type, $validTypes, true)) {
            $type = 'vacation';
        }
        $startDate = $request->post('start_date', '');
        $endDate   = $request->post('end_date', $startDate) ?: $startDate;
        $reason    = $request->post('reason', '') ?: null;
        $authUser  = $request->getAttribute('auth_user');

        $saved = $this->timeoffRequests->save([
            'store_id'    => $storeId,
            'user_id'     => $userId,
            'type'        => $type,
            'start_date'  => $startDate,
            'end_date'    => $endDate,
            'reason'      => $reason,
            'status'      => 'approved',
            'reviewed_by' => $authUser['id'] ?? null,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->auditLogger->log($request, 'timeoff.created_by_admin', 'timeoff_request', (int) ($saved['id'] ?? 0), [
            'user_id' => $userId, 'type' => $type, 'start' => $startDate, 'end' => $endDate,
        ], $storeId ?: null);

        $this->notifs->notify(
            $userId,
            'timeoff_approved',
            'Un congé a été ajouté à votre planning.',
            (int) ($saved['id'] ?? 0)
        );

        return Response::redirect($this->base() . '/admin/timeoff?success=created');
    }

    public function approveTimeoff(Request $request): Response
    {
        $req = $this->timeoffRequests->findById((int) $request->param('id'));
        if ($req === null) {
            throw new NotFoundException('Demande introuvable.');
        }
        $this->assertStoreAccess($request, (int) ($req['store_id'] ?? 0));
        $this->timeoffRequests->save(array_merge($req, ['status' => 'approved']));
        $this->auditLogger->logUpdate($request, 'timeoff.approved', 'timeoff_request', (int) $req['id'], $req, array_merge($req, ['status' => 'approved']), [], (int) ($req['store_id'] ?? 0) ?: null);
        $this->notifs->notify(
            (int) $req['user_id'],
            'timeoff_approved',
            'Votre demande de congé a été approuvée.',
            (int) $req['id']
        );
        return Response::redirect($this->base() . '/admin/timeoff?success=approved');
    }

    public function refuseTimeoff(Request $request): Response
    {
        $req = $this->timeoffRequests->findById((int) $request->param('id'));
        if ($req === null) {
            throw new NotFoundException('Demande introuvable.');
        }
        $this->assertStoreAccess($request, (int) ($req['store_id'] ?? 0));
        $this->timeoffRequests->save(array_merge($req, ['status' => 'refused']));
        $this->auditLogger->logUpdate($request, 'timeoff.refused', 'timeoff_request', (int) $req['id'], $req, array_merge($req, ['status' => 'refused']), [], (int) ($req['store_id'] ?? 0) ?: null);
        $this->notifs->notify(
            (int) $req['user_id'],
            'timeoff_refused',
            'Votre demande de congé a été refusée.',
            (int) $req['id']
        );
        return Response::redirect($this->base() . '/admin/timeoff?success=refused');
    }

    /**
     * POST /admin/timeoff/{id}/delete — supprime définitivement un congé
     * (quel que soit son statut), y compris pour retirer un congé approuvé du calendrier.
     */
    public function deleteTimeoff(Request $request): Response
    {
        $req = $this->timeoffRequests->findById((int) $request->param('id'));
        if ($req === null) {
            throw new NotFoundException('Demande introuvable.');
        }
        $this->assertStoreAccess($request, (int) ($req['store_id'] ?? 0));

        $this->auditLogger->log($request, 'timeoff.deleted', 'timeoff_request', (int) $req['id'], [
            'user_id' => $req['user_id'] ?? null,
            'type'    => $req['type'] ?? null,
            'start'   => $req['start_date'] ?? null,
            'end'     => $req['end_date'] ?? null,
            'was_status' => $req['status'] ?? null,
        ], (int) ($req['store_id'] ?? 0) ?: null);

        $this->timeoffRequests->delete((int) $req['id']);

        return Response::redirect($this->base() . '/admin/timeoff?success=deleted');
    }
}
