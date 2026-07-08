<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api\V1;

use kintai\Core\Api\Paginator;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;

final class UserController
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** GET /api/v1/users?page=1&limit=20 */
    public function index(Request $request): Response
    {
        [$page, $limit] = Paginator::params($request);
        return Response::json(Paginator::paginate($this->users->findAll(), $page, $limit));
    }

    /** GET /api/v1/users/{id} */
    public function show(Request $request): Response
    {
        $user = $this->users->findById((int) $request->param('id'));
        if ($user === null) {
            throw new NotFoundException('Utilisateur introuvable.');
        }
        return Response::json($user);
    }

    /** POST /api/v1/users */
    public function store(Request $request): Response
    {
        $data  = $request->json() ?? [];
        $saved = $this->users->save($data);
        $this->auditLogger->log($request, 'user.created', 'user', resourceId: (int) ($saved['id'] ?? 0) ?: null, details: $data);
        return Response::json($saved, 201);
    }

    /** PUT /api/v1/users/{id} */
    public function update(Request $request): Response
    {
        $id  = (int) $request->param('id');
        $old = $this->users->findById($id);
        if ($old === null) {
            throw new NotFoundException('Utilisateur introuvable.');
        }
        $data  = $request->json() ?? [];
        $saved = $this->users->save(array_merge($data, ['id' => $id]));
        $this->auditLogger->logUpdate($request, 'user.updated', 'user', resourceId: $id, oldData: $old, newData: $saved, extraContext: $data);
        return Response::json($saved);
    }

    /** DELETE /api/v1/users/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($this->users->findById($id) === null) {
            throw new NotFoundException('Utilisateur introuvable.');
        }
        $this->users->delete($id);
        $this->auditLogger->log($request, 'user.deleted', 'user', resourceId: $id);
        return Response::empty();
    }
}
