<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api\V1;

use kintai\Core\Api\Paginator;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\AvailabilityRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;

final class AvailabilityController
{
    public function __construct(
        private readonly AvailabilityRepositoryInterface $availabilities,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** GET /api/v1/availabilities?store_id=X&user_id=Y&page=1&limit=20 */
    public function index(Request $request): Response
    {
        [$page, $limit] = Paginator::params($request);
        $storeId = $request->query('store_id');
        $userId  = $request->query('user_id');

        if ($userId !== null) {
            $items = $this->availabilities->findByUser((int) $userId);
        } elseif ($storeId !== null) {
            $items = $this->availabilities->findByStore((int) $storeId);
        } else {
            $items = [];
        }

        return Response::json(Paginator::paginate($items, $page, $limit));
    }

    /** GET /api/v1/availabilities/{id} */
    public function show(Request $request): Response
    {
        $item = $this->availabilities->findById((int) $request->param('id'));
        if ($item === null) {
            throw new NotFoundException('Disponibilité introuvable.');
        }
        return Response::json($item);
    }

    /** POST /api/v1/availabilities */
    public function store(Request $request): Response
    {
        $data  = $request->json() ?? [];
        $saved = $this->availabilities->save($data);
        $this->auditLogger->log($request, 'availability.created', 'availability', resourceId: (int) ($saved['id'] ?? 0) ?: null, details: $data, storeId: isset($data['store_id']) ? (int) $data['store_id'] : null);
        return Response::json($saved, 201);
    }

    /** PUT /api/v1/availabilities/{id} */
    public function update(Request $request): Response
    {
        $id  = (int) $request->param('id');
        $old = $this->availabilities->findById($id);
        if ($old === null) {
            throw new NotFoundException('Disponibilité introuvable.');
        }
        $data  = $request->json() ?? [];
        $saved = $this->availabilities->save(array_merge($data, ['id' => $id]));
        $this->auditLogger->logUpdate($request, 'availability.updated', 'availability', resourceId: $id, oldData: $old, newData: $saved, extraContext: $data, storeId: isset($data['store_id']) ? (int) $data['store_id'] : null);
        return Response::json($saved);
    }

    /** DELETE /api/v1/availabilities/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->availabilities->findById($id) === null) {
            throw new NotFoundException('Disponibilité introuvable.');
        }
        $this->availabilities->delete($id);
        $this->auditLogger->log($request, 'availability.deleted', 'availability', resourceId: $id);
        return Response::empty();
    }
}
