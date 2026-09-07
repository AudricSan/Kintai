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
        $items = array_map(fn ($t) => $this->withStoreIds($t), $items);

        return Response::json(Paginator::paginate($items, $page, $limit));
    }

    /** GET /api/v1/shift-types/{id} */
    public function show(Request $request): Response
    {
        $type = $this->shiftTypes->findById((int) $request->param('id'));
        if ($type === null) {
            throw new NotFoundException('Type de shift introuvable.');
        }
        return Response::json($this->withStoreIds($type));
    }

    /**
     * POST /api/v1/shift-types
     * `store_id` (unique, rétrocompatible) ou `store_ids` (tableau) — au moins
     * l'un des deux. `store_id` reste lu par ApiPermissionMiddleware pour
     * scoper la vérification de permission à ce store.
     */
    public function store(Request $request): Response
    {
        $data     = $request->json() ?? [];
        $storeIds = $this->extractStoreIds($data);
        unset($data['store_id'], $data['store_ids']);

        $saved = $this->shiftTypes->save($data);
        if ($storeIds !== []) {
            $this->shiftTypes->syncStores((int) $saved['id'], $storeIds);
        }

        $this->auditLogger->log($request, 'shift_type.created', 'shift_type', resourceId: (int) ($saved['id'] ?? 0) ?: null, details: $data, storeId: $storeIds[0] ?? null);
        return Response::json($this->withStoreIds($saved), 201);
    }

    /**
     * PUT /api/v1/shift-types/{id}
     * `store_id`/`store_ids` absents du corps : les affectations aux stores
     * existantes ne sont pas modifiées (mise à jour partielle).
     */
    public function update(Request $request): Response
    {
        $id  = (int) $request->param('id');
        $old = $this->shiftTypes->findById($id);
        if ($old === null) {
            throw new NotFoundException('Type de shift introuvable.');
        }
        $data     = $request->json() ?? [];
        $storeIds = $this->extractStoreIds($data);
        unset($data['store_id'], $data['store_ids']);

        $saved = $this->shiftTypes->save(array_merge($data, ['id' => $id]));
        if ($storeIds !== []) {
            $this->shiftTypes->syncStores($id, $storeIds);
        }

        $this->auditLogger->logUpdate($request, 'shift_type.updated', 'shift_type', resourceId: $id, oldData: $old, newData: $saved, extraContext: $data, storeId: $storeIds[0] ?? null);
        return Response::json($this->withStoreIds($saved));
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

    private function withStoreIds(array $type): array
    {
        $type['store_ids'] = $this->shiftTypes->getStoreIds((int) ($type['id'] ?? 0));
        return $type;
    }

    /** @return int[] */
    private function extractStoreIds(array $data): array
    {
        if (isset($data['store_ids']) && is_array($data['store_ids'])) {
            return array_values(array_unique(array_map('intval', $data['store_ids'])));
        }
        if (isset($data['store_id']) && $data['store_id'] !== '' && $data['store_id'] !== null) {
            return [(int) $data['store_id']];
        }
        return [];
    }
}
