<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api\V1;

use kintai\Core\Api\Paginator;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;

final class ShiftController
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shifts,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** GET /api/v1/shifts?store_id=X&user_id=Y&date=Z&page=1&limit=20 */
    public function index(Request $request): Response
    {
        [$page, $limit] = Paginator::params($request);
        $storeId = $request->query('store_id');
        $userId  = $request->query('user_id');
        $date    = $request->query('date');

        if ($storeId !== null && $date !== null) {
            $items = $this->shifts->findByDate((int) $storeId, $date);
        } elseif ($storeId !== null) {
            $items = $this->shifts->findByStore((int) $storeId);
        } elseif ($userId !== null) {
            $items = $this->shifts->findByUser((int) $userId);
        } else {
            $items = [];
        }

        return Response::json(Paginator::paginate($items, $page, $limit));
    }

    /** GET /api/v1/shifts/{id} */
    public function show(Request $request): Response
    {
        $shift = $this->shifts->findById((int) $request->param('id'));
        if ($shift === null) {
            throw new NotFoundException('Shift introuvable.');
        }
        return Response::json($shift);
    }

    /** POST /api/v1/shifts */
    public function store(Request $request): Response
    {
        $data  = $request->json() ?? [];
        $saved = $this->shifts->save($data);
        $this->auditLogger->log($request, 'shift.created', 'shift', resourceId: (int) ($saved['id'] ?? 0) ?: null, details: $data, storeId: isset($data['store_id']) ? (int) $data['store_id'] : null);
        return Response::json($saved, 201);
    }

    /** PUT /api/v1/shifts/{id} */
    public function update(Request $request): Response
    {
        $id  = (int) $request->param('id');
        $old = $this->shifts->findById($id);
        if ($old === null) {
            throw new NotFoundException('Shift introuvable.');
        }
        $data  = $request->json() ?? [];
        $saved = $this->shifts->save(array_merge($data, ['id' => $id]));
        $this->auditLogger->logUpdate($request, 'shift.updated', 'shift', resourceId: $id, oldData: $old, newData: $saved, extraContext: $data, storeId: isset($data['store_id']) ? (int) $data['store_id'] : null);
        return Response::json($saved);
    }

    /** DELETE /api/v1/shifts/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->shifts->findById($id) === null) {
            throw new NotFoundException('Shift introuvable.');
        }
        $this->shifts->delete($id);
        $this->auditLogger->log($request, 'shift.deleted', 'shift', resourceId: $id);
        return Response::empty();
    }
}
