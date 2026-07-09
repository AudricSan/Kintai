<?php

declare(strict_types=1);

namespace kintai\Bundles\ShiftSwap\Controllers\Api;

use kintai\Core\Api\Paginator;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;

final class ShiftSwapRequestController
{
    public function __construct(
        private readonly ShiftSwapRequestRepositoryInterface $swapRequests,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** GET /api/v1/shift-swap-requests?store_id=X&requester_id=Y&status=Z&page=1&limit=20 */
    public function index(Request $request): Response
    {
        [$page, $limit] = Paginator::params($request);
        $storeId     = $request->query('store_id');
        $requesterId = $request->query('requester_id');
        $status      = $request->query('status');

        if ($storeId !== null && $status !== null) {
            $items = $this->swapRequests->findByStatus((int) $storeId, $status);
        } elseif ($storeId !== null) {
            $items = $this->swapRequests->findByStore((int) $storeId);
        } elseif ($requesterId !== null) {
            $items = $this->swapRequests->findByRequester((int) $requesterId);
        } else {
            $items = [];
        }

        return Response::json(Paginator::paginate($items, $page, $limit));
    }

    /** GET /api/v1/shift-swap-requests/{id} */
    public function show(Request $request): Response
    {
        $item = $this->swapRequests->findById((int) $request->param('id'));
        if ($item === null) {
            throw new NotFoundException('Demande d\'échange introuvable.');
        }
        return Response::json($item);
    }

    /** POST /api/v1/shift-swap-requests */
    public function store(Request $request): Response
    {
        $data  = $request->json() ?? [];
        $saved = $this->swapRequests->save($data);
        $this->auditLogger->log($request, 'shift_swap_request.created', 'shift_swap_request', resourceId: (int) ($saved['id'] ?? 0) ?: null, details: $data, storeId: isset($data['store_id']) ? (int) $data['store_id'] : null);
        return Response::json($saved, 201);
    }

    /** PUT /api/v1/shift-swap-requests/{id} */
    public function update(Request $request): Response
    {
        $id  = (int) $request->param('id');
        $old = $this->swapRequests->findById($id);
        if ($old === null) {
            throw new NotFoundException('Demande d\'échange introuvable.');
        }
        $data  = $request->json() ?? [];
        $saved = $this->swapRequests->save(array_merge($data, ['id' => $id]));
        $this->auditLogger->logUpdate($request, 'shift_swap_request.updated', 'shift_swap_request', resourceId: $id, oldData: $old, newData: $saved, extraContext: $data, storeId: isset($data['store_id']) ? (int) $data['store_id'] : null);
        return Response::json($saved);
    }

    /** DELETE /api/v1/shift-swap-requests/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->swapRequests->findById($id) === null) {
            throw new NotFoundException('Demande d\'échange introuvable.');
        }
        $this->swapRequests->delete($id);
        $this->auditLogger->log($request, 'shift_swap_request.deleted', 'shift_swap_request', resourceId: $id);
        return Response::empty();
    }
}
