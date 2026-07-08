<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api\V1;

use kintai\Core\Api\Paginator;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;

final class StoreController
{
    public function __construct(
        private readonly StoreRepositoryInterface $stores,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** GET /api/v1/stores?page=1&limit=20 */
    public function index(Request $request): Response
    {
        [$page, $limit] = Paginator::params($request);
        return Response::json(Paginator::paginate($this->stores->findAll(), $page, $limit));
    }

    /** GET /api/v1/stores/{id} */
    public function show(Request $request): Response
    {
        $store = $this->stores->findById((int) $request->param('id'));
        if ($store === null) {
            throw new NotFoundException('Magasin introuvable.');
        }
        return Response::json($store);
    }

    /** POST /api/v1/stores */
    public function store(Request $request): Response
    {
        $data  = $request->json() ?? [];
        $saved = $this->stores->save($data);
        $this->auditLogger->log($request, 'store.created', 'store', resourceId: (int) ($saved['id'] ?? 0) ?: null, details: $data);
        return Response::json($saved, 201);
    }

    /** PUT /api/v1/stores/{id} */
    public function update(Request $request): Response
    {
        $id  = (int) $request->param('id');
        $old = $this->stores->findById($id);
        if ($old === null) {
            throw new NotFoundException('Magasin introuvable.');
        }
        $data  = $request->json() ?? [];
        $saved = $this->stores->save(array_merge($data, ['id' => $id]));
        $this->auditLogger->logUpdate($request, 'store.updated', 'store', resourceId: $id, oldData: $old, newData: $saved, extraContext: $data);
        return Response::json($saved);
    }

    /** DELETE /api/v1/stores/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->stores->findById($id) === null) {
            throw new NotFoundException('Magasin introuvable.');
        }
        $this->stores->delete($id);
        $this->auditLogger->log($request, 'store.deleted', 'store', resourceId: $id);
        return Response::empty();
    }
}
