<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Api\V1;

use kintai\Core\Api\Paginator;
use kintai\Core\Auth\CredentialRevoker;
use kintai\Core\Auth\PasswordHasher;
use kintai\Core\Auth\PasswordPolicy;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Exceptions\ValidationException;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\PlanLimitService;

final class UserController
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogger $auditLogger,
        private readonly PlanLimitService $planLimits,
        private readonly PermissionService $permissions,
        private readonly StoreUserRepositoryInterface $storeUsers,
        // Optionnel pour les tests qui construisent le contrôleur à la main.
        private readonly ?CredentialRevoker $revoker = null,
    ) {}

    /** GET /api/v1/users?page=1&limit=20 */
    public function index(Request $request): Response
    {
        [$page, $limit] = Paginator::params($request);
        $authUser = $this->authUser($request);
        $items    = $this->users->findAll();

        // Hors portée illimitée, seuls les membres des magasins gérés (et soi-même) sont listés.
        if (!$this->permissions->hasUnrestrictedGrant($authUser, 'employees.view')) {
            $visible = [(int) ($authUser['id'] ?? 0) => true];
            foreach ($this->permissions->scopedStoreIds((int) ($authUser['id'] ?? 0), 'employees.view') as $storeId) {
                foreach ($this->storeUsers->findByStore($storeId) as $member) {
                    $visible[(int) $member['user_id']] = true;
                }
            }
            $items = array_values(array_filter($items, fn(array $u): bool => isset($visible[(int) $u['id']])));
        }

        return Response::json(Paginator::paginate(array_map([$this, 'withoutSecrets'], $items), $page, $limit));
    }

    /** GET /api/v1/users/{id} */
    public function show(Request $request): Response
    {
        $user = $this->requireUser((int) $request->param('id'));
        $this->permissions->requireUserAccess($this->authUser($request), 'employees.view', (int) $user['id'], $this->storeIdsOf((int) $user['id']));
        return Response::json($this->withoutSecrets($user));
    }

    /** POST /api/v1/users — `password` (en clair) facultatif ; sans lui, mot de passe par défaut à changer. */
    public function store(Request $request): Response
    {
        $this->planLimits->assertCanCreateEmployee();

        $data = $request->json() ?? [];
        unset($data['id'], $data['password_hash']);
        $data['password_hash'] = $this->hashFromPayload($data) ?? PasswordHasher::hash(PasswordPolicy::DEFAULT_PASSWORD);
        unset($data['password']);

        $saved = $this->users->save($data);
        $this->auditLogger->log($request, 'user.created', 'user', resourceId: (int) ($saved['id'] ?? 0) ?: null, details: $this->withoutSecrets($data));
        return Response::json($this->withoutSecrets($saved), 201);
    }

    /** PUT /api/v1/users/{id} */
    public function update(Request $request): Response
    {
        $id  = (int) $request->param('id');
        $old = $this->requireUser($id);
        $this->permissions->requireUserAccess($this->authUser($request), 'employees.update', $id, $this->storeIdsOf($id));

        $data = $request->json() ?? [];
        unset($data['id'], $data['password_hash']);
        $newHash = $this->hashFromPayload($data);
        unset($data['password']);
        if ($newHash !== null) {
            $data['password_hash'] = $newHash;
        }

        $saved = $this->users->save(array_merge($data, ['id' => $id]));

        // Nouveau mot de passe ou compte désactivé : cookies « rester connecté » et jetons d'API révoqués.
        if ($newHash !== null || (!empty($old['is_active']) && array_key_exists('is_active', $data) && empty($data['is_active']))) {
            $this->revoker?->revokeAllFor($id);
        }

        $this->auditLogger->logUpdate($request, 'user.updated', 'user', resourceId: $id, oldData: $this->withoutSecrets($old), newData: $this->withoutSecrets($saved), extraContext: $this->withoutSecrets($data));
        return Response::json($this->withoutSecrets($saved));
    }

    /** DELETE /api/v1/users/{id} */
    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        $this->requireUser($id);
        $this->permissions->requireUserAccess($this->authUser($request), 'employees.delete', $id, $this->storeIdsOf($id));

        $this->users->delete($id);
        $this->revoker?->revokeAllFor($id);
        $this->auditLogger->log($request, 'user.deleted', 'user', resourceId: $id);
        return Response::empty();
    }

    private function authUser(Request $request): array
    {
        return $request->getAttribute('auth_user') ?? [];
    }

    private function requireUser(int $id): array
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            throw new NotFoundException(__('error_user_not_found'));
        }
        return $user;
    }

    /** @return int[] */
    private function storeIdsOf(int $userId): array
    {
        return array_map(fn(array $m): int => (int) $m['store_id'], $this->storeUsers->findByUser($userId));
    }

    /** Hash du champ `password` du corps, null s'il est absent ; 422 s'il est trop court. */
    private function hashFromPayload(array $data): ?string
    {
        if (!isset($data['password']) || $data['password'] === '') {
            return null;
        }
        $password = (string) $data['password'];
        if (!PasswordPolicy::isLongEnough($password)) {
            throw new ValidationException(['password' => __('password_too_short')]);
        }
        return PasswordHasher::hash($password);
    }

    private function withoutSecrets(array $user): array
    {
        unset($user['password_hash']);
        return $user;
    }
}
