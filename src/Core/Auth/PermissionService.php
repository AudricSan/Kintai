<?php

declare(strict_types=1);

namespace kintai\Core\Auth;

use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;

/**
 * Moteur de vérification des permissions dynamiques (rôles/role_permissions/
 * role_assignments). Pensé pour être appelé aussi bien côté Web que côté API.
 */
final class PermissionService
{
    /** @var array<int, array|null> Cache des rôles chargés, par role_id. */
    private array $roleCache = [];

    public function __construct(
        private readonly RoleAssignmentRepositoryInterface $assignments,
        private readonly RoleRepositoryInterface $roles,
    ) {}

    /**
     * @param array $authUser Utilisateur authentifié (au minimum ['id' => int])
     * @param string $permissionKey Ex. 'employees.create' (voir PermissionCatalog)
     * @param int|null $storeId Portée à vérifier. null = n'importe quelle portée
     *                          (utile pour "cet utilisateur a-t-il X quelque part ?").
     */
    public function can(array $authUser, string $permissionKey, ?int $storeId = null): bool
    {
        $userId = (int) ($authUser['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        foreach ($this->assignments->findByUser($userId) as $assignment) {
            if (!$this->matchesScope($assignment, $storeId)) {
                continue;
            }
            if ($this->roleGrants((int) $assignment['role_id'], $permissionKey)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Stores pour lesquels l'utilisateur détient, via une affectation de
     * portée 'store', un rôle accordant cette permission. N'inclut PAS les
     * stores couverts par une affectation de portée 'global' (ex. Owner) —
     * l'appelant doit vérifier can($user, $key, null) séparément pour ce cas.
     * @return int[]
     */
    public function scopedStoreIds(int $userId, string $permissionKey): array
    {
        $storeIds = [];
        foreach ($this->assignments->findByUser($userId) as $assignment) {
            if ($assignment['scope_type'] !== 'store' || $assignment['scope_id'] === null) {
                continue;
            }
            if ($this->roleGrants((int) $assignment['role_id'], $permissionKey)) {
                $storeIds[] = (int) $assignment['scope_id'];
            }
        }
        return array_values(array_unique($storeIds));
    }

    private function matchesScope(array $assignment, ?int $storeId): bool
    {
        if ($storeId === null) {
            return true;
        }
        if ($assignment['scope_type'] === 'global') {
            return true;
        }
        return (int) ($assignment['scope_id'] ?? 0) === $storeId;
    }

    private function roleGrants(int $roleId, string $permissionKey): bool
    {
        $role = $this->role($roleId);
        if ($role === null) {
            return false;
        }
        if (!empty($role['is_system'])) {
            return true;
        }
        return in_array($permissionKey, $this->roles->getPermissions($roleId), true);
    }

    private function role(int $roleId): ?array
    {
        if (!array_key_exists($roleId, $this->roleCache)) {
            $this->roleCache[$roleId] = $this->roles->findById($roleId);
        }
        return $this->roleCache[$roleId];
    }
}
