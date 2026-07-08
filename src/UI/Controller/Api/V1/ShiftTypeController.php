<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api\V1;

use kintai\Core\Api\Paginator;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;

final class ShiftTypeController
{
    public function __construct(
        private readonly ShiftTypeRepositoryInterface $shiftTypes,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** GET /api/v1/shift-types?store_id=X&page=1&limit=20 */
    public function index(Request $request): Response
    {
        [$page, $limit] = Paginator::params($request);
        $storeId = $request->query('store_id');

        $items = $storeId !== null
            ? $this->shiftTypes->findByStore((int) $storeId)
            : $this->shiftTypes->findAll();

        return Response::json(Paginator::paginate($items, $page, $limit));
    }

    /** GET /api/v1/shift-types/{id} */
    public function show(Request $request): Response
    {
        $type = $this->shiftTypes->findById((int) $request->param('id'));
        if ($type === null) {
            throw new NotFoundException('Type de shift introuvable.');
        }
        return Response::json($type);
    }

    /** POST /api/v1/shift-types */
    public function store(Request $request): Response
    {
        $data  = $request->json() ?? [];
        $saved = $this->shiftTypes->save($data);
        $this->auditLogger->log($request, 'shift_type.created', 'shift_type', resourceId: (int) ($saved['id'] ?? 0) ?: null, details: $data, storeId: isset($data['store_id']) ? (int) $data['store_id'] : null);
        return Response::json($saved, 201);
    }

    /** PUT /api/v1/shift-types/{id} */
    public function update(Request $request): Response
    {
        $id  = (int) $request->param('id');
        $old = $this->shiftTypes->findById($id);
        if ($old === null) {
            throw new NotFoundException('Type de shift introuvable.');
        }
        $data  = $request->json() ?? [];
        $saved = $this->shiftTypes->save(array_merge($data, ['id' => $id]));
        $this->auditLogger->logUpdate($request, 'shift_type.updated', 'shift_type', resourceId: $id, oldData: $old, newData: $saved, extraContext: $data, storeId: isset($data['store_id']) ? (int) $data['store_id'] : null);
        return Response::json($saved);
    }

    /** DELETE /api/v1/shift-types/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->shiftTypes->findById($id) === null) {
            throw new NotFoundException('Type de shift introuvable.');
        }
        $this->shiftTypes->delete($id);
        $this->auditLogger->log($request, 'shift_type.deleted', 'shift_type', resourceId: $id);
        return Response::empty();
    }
}
