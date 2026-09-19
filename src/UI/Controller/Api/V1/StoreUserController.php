<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api\V1;

use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;

final class StoreUserController
{
    public function __construct(
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly StoreRepositoryInterface $stores,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** GET /api/v1/stores/{store_id}/members */
    public function index(Request $request): Response
    {
        $storeId = (int) $request->param('store_id');
        $this->requireStore($storeId);
        return Response::json($this->storeUsers->findByStore($storeId));
    }

    /** GET /api/v1/stores/{store_id}/members/{id} */
    public function show(Request $request): Response
    {
        $storeId = (int) $request->param('store_id');
        $id      = (int) $request->param('id');
        $this->requireStore($storeId);

        $member = $this->storeUsers->findById($id);
        if ($member === null || (int) $member['store_id'] !== $storeId) {
            throw new NotFoundException(__('error_member_not_found'));
        }

        return Response::json($member);
    }

    /** POST /api/v1/stores/{store_id}/members */
    public function store(Request $request): Response
    {
        $storeId = (int) $request->param('store_id');
        $this->requireStore($storeId);
        $data   = array_merge($this->stripLegacyRoleFields($request->json() ?? []), ['store_id' => $storeId]);
        $saved  = $this->storeUsers->save($data);
        $this->auditLogger->log($request, 'store_user.created', 'store_user', resourceId: (int) ($saved['id'] ?? 0) ?: null, details: $data, storeId: $storeId);
        return Response::json($saved, 201);
    }

    /** PUT /api/v1/stores/{store_id}/members/{id} */
    public function update(Request $request): Response
    {
        $storeId = (int) $request->param('store_id');
        $id      = (int) $request->param('id');
        $this->requireStore($storeId);

        $existing = $this->storeUsers->findById($id);
        if ($existing === null || (int) $existing['store_id'] !== $storeId) {
            throw new NotFoundException(__('error_member_not_found'));
        }

        $data = $this->stripLegacyRoleFields($request->json() ?? []);
        return Response::json($this->storeUsers->save(array_merge($existing, $data, ['id' => $id, 'store_id' => $storeId])));
    }

    /**
     * Le rôle réel d'un membre se gère exclusivement via /admin/roles et
     * role_assignments (RoleAssignmentSyncService) — jamais par cette API
     * générique de membership, pour ne pas désynchroniser l'autorisation
     * réelle d'une valeur postée ici sans passer par le RBAC dynamique.
     */
    private function stripLegacyRoleFields(array $data): array
    {
        unset($data['role'], $data['is_manager']);
        return $data;
    }

    /** DELETE /api/v1/stores/{store_id}/members/{id} */
    public function destroy(Request $request): Response
    {
        $storeId = (int) $request->param('store_id');
        $id      = (int) $request->param('id');
        $this->requireStore($storeId);

        $existing = $this->storeUsers->findById($id);
        if ($existing === null || (int) $existing['store_id'] !== $storeId) {
            throw new NotFoundException(__('error_member_not_found'));
        }

        $this->storeUsers->delete($id);
        $this->auditLogger->log($request, 'store_user.deleted', 'store_user', resourceId: $id, storeId: $storeId);
        return Response::empty();
    }

    private function requireStore(int $id): void
    {
        if ($this->stores->findById($id) === null) {
            throw new NotFoundException(__('error_store_not_found'));
        }
    }
}
