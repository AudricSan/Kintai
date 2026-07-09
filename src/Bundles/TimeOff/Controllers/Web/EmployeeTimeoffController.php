<?php

declare(strict_types=1);

namespace kintai\Bundles\TimeOff\Controllers\Web;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\UI\Controller\Web\HasBaseUrl;
use kintai\UI\Controller\Web\HasStoreFeatureCheck;
use kintai\UI\ViewRenderer;

final class EmployeeTimeoffController
{
    use HasBaseUrl;
    use HasStoreFeatureCheck;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly TimeoffRequestRepositoryInterface $timeoffRequests,
        private readonly StoreRepositoryInterface $stores,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function timeoff(Request $request): Response
    {
        if (($resp = $this->assertFeature($request, 'timeoff')) !== null) {
            return $resp;
        }
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $requests = $this->timeoffRequests->findByUser($userId);
        usort($requests, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        return Response::html($this->view->render('timeoff::employee-timeoff', [
            'title'    => 'Mes congés',
            'requests' => $requests,
        ], 'layout.app'));
    }

    public function storeTimeoff(Request $request): Response
    {
        if (($resp = $this->assertFeature($request, 'timeoff')) !== null) {
            return $resp;
        }
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $memberships = $this->storeUsers->findByUser($userId);
        $storeId     = $memberships ? (int) $memberships[0]['store_id'] : 0;

        $validTypes = ['vacation', 'sick', 'personal', 'unpaid', 'other'];
        $type       = $request->post('type', 'vacation');
        if (!in_array($type, $validTypes, true)) {
            $type = 'vacation';
        }
        $startDate = $request->post('start_date', '');
        $endDate   = $request->post('end_date', $startDate) ?: $startDate;
        $reason    = $request->post('reason', '') ?: null;

        $savedTimeoff = $this->timeoffRequests->save([
            'store_id'   => $storeId,
            'user_id'    => $userId,
            'type'       => $type,
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'reason'     => $reason,
            'status'     => 'pending',
        ]);

        $this->auditLogger->log($request, 'timeoff.created', 'timeoff_request', (int) ($savedTimeoff['id'] ?? 0), [
            'type' => $type, 'start' => $startDate, 'end' => $endDate,
        ], $storeId ?: null);
        return Response::redirect($this->base() . '/employee/timeoff?success=created');
    }

    public function cancelTimeoff(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $req = $this->timeoffRequests->findById((int) $request->param('id'));
        if ($req === null || (int) $req['user_id'] !== $userId) {
            throw new ForbiddenException('Demande introuvable.');
        }
        if (($req['status'] ?? '') !== 'pending') {
            return Response::redirect($this->base() . '/employee/timeoff?error=not_pending');
        }

        $cancelledReq = array_merge($req, ['status' => 'cancelled']);
        $this->timeoffRequests->save($cancelledReq);
        $this->auditLogger->logUpdate($request, 'timeoff.cancelled', 'timeoff_request', (int) $req['id'], $req, $cancelledReq, []);
        return Response::redirect($this->base() . '/employee/timeoff?success=cancelled');
    }
}
