<?php

declare(strict_types=1);

namespace kintai\Core\Auth;

use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;

/**
 * Service d'authentification basé sur les sessions PHP natives.
 * Stocke uniquement l'ID utilisateur en session ; recharge depuis la DB à chaque requête.
 */
final class AuthService
{
    private const SESSION_KEY = 'auth_user_id';

    /** Rôles par-store considérés comme "gestionnaire" (accès panel admin restreint). */
    public const MANAGER_ROLES = ['admin', 'manager'];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly StoreRepositoryInterface $stores,
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

        $_SESSION[self::SESSION_KEY] = $user['id'];
        return true;
    }

    /** Retourne l'utilisateur connecté (rechargé depuis la DB), ou null. */
    public function user(): ?array
    {
        $id = $_SESSION[self::SESSION_KEY] ?? null;
        if ($id === null) {
            return null;
        }
        return $this->users->findById((int) $id);
    }

    /** L'utilisateur est-il connecté ? */
    public function check(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]);
    }

    /** L'utilisateur connecté est-il admin global ? */
    public function isAdmin(): bool
    {
        return (bool) ($this->user()['is_admin'] ?? false);
    }

    /**
     * Retourne les IDs des stores que l'utilisateur gère (rôle admin ou manager).
     * Retourne un tableau vide si admin global (pas de restriction) ou non connecté.
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

        $memberships = $this->storeUsers->findByUser((int) $user['id']);
        return array_values(array_map(
            fn($m) => (int) $m['store_id'],
            array_filter($memberships, fn($m) => in_array($m['role'] ?? '', self::MANAGER_ROLES, true))
        ));
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
}
