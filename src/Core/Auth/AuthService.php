<?php

declare(strict_types=1);

namespace kintai\Core\Auth;

use kintai\Core\Repositories\RememberTokenRepositoryInterface;
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
    private const REMEMBER_COOKIE = 'kintai_remember';
    private const REMEMBER_LIFETIME_DAYS = 30;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly StoreRepositoryInterface $stores,
        private readonly RoleRepositoryInterface $roles,
        private readonly RoleAssignmentRepositoryInterface $roleAssignments,
        private readonly RememberTokenRepositoryInterface $rememberTokens,
    ) {}

    /**
     * Tente de connecter un utilisateur avec email + mot de passe.
     * Retourne true si les identifiants sont valides et l'utilisateur actif.
     */
    public function attempt(string $email, string $password, bool $remember = false): bool
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
        if ($remember) {
            $this->issueRememberToken((int) $user['id']);
        }
        return true;
    }

    /**
     * Tente de connecter un employé avec code_employé + code_magasin + mot de passe.
     * Le mot de passe par défaut est "0000".
     */
    public function attemptByCode(string $employeeCode, string $storeCode, string $password, bool $remember = false): bool
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
        if ($remember) {
            $this->issueRememberToken((int) $user['id']);
        }
        return true;
    }

    /**
     * Relit le cookie "rester connecté" (posé par issueRememberToken) et reconnecte
     * l'utilisateur s'il est valide. Fait tourner le token à chaque utilisation réussie
     * (ancien sélecteur supprimé, nouveau émis) : limite la fenêtre de rejeu si le cookie
     * a fuité, sans jamais forcer l'utilisateur à se reconnecter tant qu'il revient avant
     * expiration. $cookieValue permet de s'affranchir de $_COOKIE dans les tests.
     */
    public function attemptViaRememberCookie(?string $cookieValue = null): bool
    {
        $cookieValue ??= $_COOKIE[self::REMEMBER_COOKIE] ?? null;
        if (!$cookieValue || !str_contains($cookieValue, '.')) {
            return false;
        }

        [$selector, $validator] = explode('.', $cookieValue, 2);
        $token = $this->rememberTokens->findBySelector($selector);
        if ($token === null) {
            $this->clearRememberCookie();
            return false;
        }

        // Sélecteur valide mais mauvais validateur, ou token expiré : le cookie est
        // potentiellement volé/rejoué — on révoque par précaution plutôt que d'échouer
        // silencieusement en le laissant réutilisable.
        if (
            strtotime((string) $token['expires_at']) < time()
            || !hash_equals((string) $token['validator_hash'], hash('sha256', $validator))
        ) {
            $this->rememberTokens->deleteBySelector($selector);
            $this->clearRememberCookie();
            return false;
        }

        $user = $this->users->findById((int) $token['user_id']);
        if ($user === null || empty($user['is_active']) || !empty($user['deleted_at'])) {
            $this->rememberTokens->deleteBySelector($selector);
            $this->clearRememberCookie();
            return false;
        }

        $this->rememberTokens->deleteBySelector($selector);

        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $user['id'];
        $this->issueRememberToken((int) $user['id']);

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
            if ($this->roleGrantsManagementAccess((int) $assignment['role_id'])) {
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

    /** Déconnecte l'utilisateur courant (et révoque le "rester connecté" s'il y en a un). */
    public function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        $this->revokeRememberCookie();
    }

    /**
     * Émet un nouveau token "rester connecté" (30 jours) : sélecteur en clair (indexé,
     * sert à la recherche) + hash SHA-256 du validateur (jamais stocké en clair) — schéma
     * repris de celui déjà utilisé pour api_tokens, adapté en sélecteur/validateur pour
     * qu'une lecture de la table seule ne permette pas de rejouer un cookie volé côté DB.
     */
    private function issueRememberToken(int $userId): void
    {
        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires   = time() + self::REMEMBER_LIFETIME_DAYS * 86400;

        $this->rememberTokens->create([
            'user_id'        => $userId,
            'selector'       => $selector,
            'validator_hash' => hash('sha256', $validator),
            'expires_at'     => date('Y-m-d H:i:s', $expires),
        ]);

        $this->setRememberCookie($selector . '.' . $validator, $expires);
    }

    /** Révoque le token en base associé au cookie courant (s'il existe) et efface le cookie. */
    private function revokeRememberCookie(): void
    {
        $cookieValue = $_COOKIE[self::REMEMBER_COOKIE] ?? null;
        if ($cookieValue && str_contains($cookieValue, '.')) {
            [$selector] = explode('.', $cookieValue, 2);
            $this->rememberTokens->deleteBySelector($selector);
        }
        $this->clearRememberCookie();
    }

    private function setRememberCookie(string $value, int $expires): void
    {
        setcookie(self::REMEMBER_COOKIE, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function clearRememberCookie(): void
    {
        setcookie(self::REMEMBER_COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[self::REMEMBER_COOKIE]);
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

    /**
     * Vrai si ce rôle accorde au moins une permission de *gestion* (au-delà d'un simple
     * ".view") sur ce store — c'est le critère utilisé pour décider si un utilisateur passe
     * AdminMiddleware et accède à /admin/*. Un rôle qui n'accorde QUE des permissions .view
     * (ex. le rôle "employee" par défaut, avec seulement shifts.view pour consulter son
     * planning) ne doit jamais compter comme gestionnaire : sinon un simple employé se
     * retrouve avec accès aux pages de gestion (shifts, types de shifts, personnel...) de
     * tout /admin/*, avec seule la permission fine (PermissionMiddleware) pour limiter les
     * actions d'écriture — mais pas l'affichage des boutons/données sensibles côté vue, qui
     * suppose généralement "je suis dans /admin, donc je gère". Voir CHANGELOG.
     */
    private function roleGrantsManagementAccess(int $roleId): bool
    {
        $role = $this->roles->findById($roleId);
        if ($role === null) {
            return false;
        }
        if (!empty($role['is_system'])) {
            return true;
        }
        foreach ($this->roles->getPermissions($roleId) as $permission) {
            if (!str_ends_with($permission, '.view')) {
                return true;
            }
        }
        return false;
    }
}
