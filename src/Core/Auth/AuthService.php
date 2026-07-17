<?php

declare(strict_types=1);

namespace kintai\Core\Auth;

use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;

/**
 * Service d'authentification basé sur les sessions PHP natives.
 * Stocke uniquement l'ID utilisateur en session ; recharge depuis la DB à chaque requête.
 *
 * isAdmin()/managedStoreIds() sont calculés à partir de role_assignments (RBAC
 * dynamique, task/mermission.md) plutôt que des colonnes historiques
 * users.is_admin/store_user.role. user() applique ce calcul directement sur
 * la clé 'is_admin' du tableau retourné : c'est le seul point de pontage
 * nécessaire pour que tout le reste de l'app (contrôleurs qui lisent
 * $request->getAttribute('auth_user')['is_admin'] directement plutôt que via
 * ce service) continue de fonctionner sans modification.
 */
final class AuthService
{
    private const SESSION_KEY = 'auth_user_id';

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly StoreRepositoryInterface $stores,
        private readonly RoleRepositoryInterface $roles,
        private readonly RoleAssignmentRepositoryInterface $roleAssignments,
    ) {}

    /**
     * Tente de connecter un utilisateur avec email + mot de passe.
     * Retourne true si les identifiants sont valides et l'utilisateur actif.
     */
    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            return false;
        }

        if (empty($user['is_active']) || !empty($user['deleted_at'])) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'] ?? '')) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $user['id'];
        return true;
    }

    /**
     * Tente de connecter un employé avec code_employé + code_magasin + mot de passe.
     * Le mot de passe par défaut est "0000".
     */
    public function attemptByCode(string $employeeCode, string $storeCode, string $password): bool
    {
        $store = $this->stores->findByCode(strtoupper(trim($storeCode)));
        if ($store === null) {
            return false;
        }

        $user = $this->users->findByEmployeeCode(trim($employeeCode));
        if ($user === null) {
            return false;
        }

        if (empty($user['is_active']) || !empty($user['deleted_at'])) {
            return false;
        }

        // Vérifier que l'utilisateur est membre de ce store
        $membership = $this->storeUsers->findMembership((int) $store['id'], (int) $user['id']);
        if ($membership === null) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'] ?? '')) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $user['id'];
        return true;
    }

    /**
     * Retourne l'utilisateur connecté (rechargé depuis la DB), ou null.
     * 'is_admin' reflète le calcul RBAC effectif (rôle système en portée
     * globale), pas la colonne brute users.is_admin.
     */
    public function user(): ?array
    {
        $id = $_SESSION[self::SESSION_KEY] ?? null;
        if ($id === null) {
            return null;
        }
        $user = $this->users->findById((int) $id);
        if ($user === null) {
            return null;
        }
        $user['is_admin'] = $this->hasOwnerRole((int) $user['id']) ? 1 : 0;
        return $user;
    }

    /** L'utilisateur est-il connecté ? */
    public function check(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]);
    }

    /** L'utilisateur connecté est-il admin global (rôle système, portée globale) ? */
    public function isAdmin(): bool
    {
        return !empty($this->user()['is_admin']);
    }

    /**
     * Retourne les IDs des stores que l'utilisateur gère (rôle accordant au
     * moins une permission sur ce store). Retourne un tableau vide si admin
     * global (pas de restriction) ou non connecté.
     * @return int[]
     */
    public function managedStoreIds(): array
    {
        $user = $this->user();
        if (!$user) {
            return [];
        }
        // Admin global → pas de restriction, on retourne [] (signifie « tous »)
        if (!empty($user['is_admin'])) {
            return [];
        }

        $storeIds = [];
        foreach ($this->roleAssignments->findByUser((int) $user['id']) as $assignment) {
            if ($assignment['scope_type'] !== 'store' || $assignment['scope_id'] === null) {
                continue;
            }
            if ($this->roleGrantsAnyPermission((int) $assignment['role_id'])) {
                $storeIds[] = (int) $assignment['scope_id'];
            }
        }
        return array_values(array_unique($storeIds));
    }

    /** L'utilisateur est-il gestionnaire d'au moins un store (ou admin global) ? */
    public function isManager(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        return !empty($this->managedStoreIds());
    }

    /** Déconnecte l'utilisateur courant. */
    public function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    private function hasOwnerRole(int $userId): bool
    {
        foreach ($this->roleAssignments->findByUser($userId) as $assignment) {
            if ($assignment['scope_type'] !== 'global') {
                continue;
            }
            $role = $this->roles->findById((int) $assignment['role_id']);
            if ($role !== null && !empty($role['is_system'])) {
                return true;
            }
        }
        return false;
    }

    private function roleGrantsAnyPermission(int $roleId): bool
    {
        $role = $this->roles->findById($roleId);
        if ($role === null) {
            return false;
        }
        if (!empty($role['is_system'])) {
            return true;
        }
        return $this->roles->getPermissions($roleId) !== [];
    }
}
