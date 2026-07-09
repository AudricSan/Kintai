<?php

declare(strict_types=1);

namespace kintai\Bundles\TimeOff\Controllers\Api;

use kintai\Core\Api\Paginator;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;

final class TimeoffRequestController
{
    public function __construct(
        private readonly TimeoffRequestRepositoryInterface $timeoffRequests,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** GET /api/v1/timeoff-requests?store_id=X&user_id=Y&status=Z&page=1&limit=20 */
    public function index(Request $request): Response
    {
        [$page, $limit] = Paginator::params($request);
        $storeId = $request->query('store_id');
        $userId  = $request->query('user_id');
        $status  = $request->query('status');

        if ($storeId !== null && $status !== null) {
            $items = $this->timeoffRequests->findByStatus((int) $storeId, $status);
        } elseif ($storeId !== null) {
            $items = $this->timeoffRequests->findByStore((int) $storeId);
        } elseif ($userId !== null) {
            $items = $this->timeoffRequests->findByUser((int) $userId);
        } else {
            $items = [];
        }

        return Response::json(Paginator::paginate($items, $page, $limit));
    }

    /** GET /api/v1/timeoff-requests/{id} */
    public function show(Request $request): Response
    {
        $item = $this->timeoffRequests->findById((int) $request->param('id'));
        if ($item === null) {
            throw new NotFoundException('Demande de congé introuvable.');
        }
        return Response::json($item);
    }

    /** POST /api/v1/timeoff-requests */
    public function store(Request $request): Response
    {
        $data  = $request->json() ?? [];
        $saved = $this->timeoffRequests->save($data);
        $this->auditLogger->log($request, 'timeoff_request.created', 'timeoff_request', resourceId: (int) ($saved['id'] ?? 0) ?: null, details: $data, storeId: isset($data['store_id']) ? (int) $data['store_id'] : null);
        return Response::json($saved, 201);
    }

    /** PUT /api/v1/timeoff-requests/{id} */
    public function update(Request $request): Response
    {
        $id  = (int) $request->param('id');
        $old = $this->timeoffRequests->findById($id);
        if ($old === null) {
            throw new NotFoundException('Demande de congé introuvable.');
        }
        $data  = $request->json() ?? [];
        $saved = $this->timeoffRequests->save(array_merge($data, ['id' => $id]));
        $this->auditLogger->logUpdate($request, 'timeoff_request.updated', 'timeoff_request', resourceId: $id, oldData: $old, newData: $saved, extraContext: $data, storeId: isset($data['store_id']) ? (int) $data['store_id'] : null);
        return Response::json($saved);
    }

    /** DELETE /api/v1/timeoff-requests/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->timeoffRequests->findById($id) === null) {
            throw new NotFoundException('Demande de congé introuvable.');
        }
        $this->timeoffRequests->delete($id);
        $this->auditLogger->log($request, 'timeoff_request.deleted', 'timeoff_request', resourceId: $id);
        return Response::empty();
    }
}
