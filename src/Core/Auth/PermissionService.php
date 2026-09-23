<?php

declare(strict_types=1);

namespace kintai\Core\Auth;

use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
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

    /** @var array<int, string[]> Cache des clés de permission en portée globale, par role_id. */
    private array $globalKeysCache = [];

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
            if (!$this->matchesScope($assignment, $storeId, $permissionKey)) {
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
            $roleId = (int) $assignment['role_id'];
            if (!$this->roleGrants($roleId, $permissionKey)) {
                continue;
            }
            if ($this->permissionIsGlobalOnRole($roleId, $permissionKey)) {
                // Cette permission est marquée globale sur ce rôle : elle ne doit pas
                // restreindre l'utilisateur à ce store, elle doit remonter comme
                // "portée illimitée" via can($user, $key, null) — voir PermissionMiddleware.
                continue;
            }
            $storeIds[] = (int) $assignment['scope_id'];
        }
        return array_values(array_unique($storeIds));
    }

    /**
     * Filtre $items aux seuls éléments dont le store est couvert par $permissionKey
     * pour cet utilisateur — défense en profondeur pour les endpoints API qui listent
     * une ressource par un identifiant autre que store_id (user_id, shift_id...) : dans
     * ce cas ApiPermissionMiddleware ne peut pas borner la portée en amont (le store_id
     * réel n'est connu qu'après lecture des lignes), donc le contrôleur doit refiltrer
     * lui-même après coup. Portée globale (rôle système, ou rôle custom affecté en
     * portée globale) = aucun filtrage.
     * @param array<int, array<string,mixed>> $items
     * @return array<int, array<string,mixed>>
     */
    public function restrictToScope(array $authUser, string $permissionKey, array $items, string $storeField = 'store_id'): array
    {
        $userId   = (int) ($authUser['id'] ?? 0);
        $storeIds = $this->scopedStoreIds($userId, $permissionKey);
        if ($storeIds === [] && $this->can($authUser, $permissionKey, null)) {
            return $items;
        }
        return array_values(array_filter(
            $items,
            fn(array $item): bool => in_array((int) ($item[$storeField] ?? 0), $storeIds, true)
        ));
    }

    /**
     * Stores pour lesquels l'utilisateur détient, via une affectation de portée 'store',
     * un rôle accordant N'IMPORTE QUELLE permission RBAC (peu importe laquelle) — porte
     * d'entrée grossière utilisée par PermissionMiddleware pour les routes 'public'/sans
     * permission précise déclarée (self-service, agrégats), en remplacement de l'ancien
     * AdminMiddleware séparé (fusionné en RBAC-V2). Contrairement à
     * AuthService::managedStoreIds() (qui relit l'utilisateur depuis la session PHP),
     * cette méthode opère directement sur le tableau $authUser déjà résolu par le
     * pipeline de requête — cohérent avec can()/scopedStoreIds() ci-dessus.
     * @return int[]
     */
    public function anyGrantedStoreIds(array $authUser): array
    {
        $userId = (int) ($authUser['id'] ?? 0);
        if ($userId <= 0) {
            return [];
        }
        $storeIds = [];
        foreach ($this->assignments->findByUser($userId) as $assignment) {
            if ($assignment['scope_type'] !== 'store' || $assignment['scope_id'] === null) {
                continue;
            }
            $role = $this->role((int) $assignment['role_id']);
            if ($role === null) {
                continue;
            }
            if (!empty($role['is_system']) || $this->roles->getPermissions((int) $assignment['role_id']) !== []) {
                $storeIds[] = (int) $assignment['scope_id'];
            }
        }
        return array_values(array_unique($storeIds));
    }

    /**
     * Vrai si l'utilisateur détient, via N'IMPORTE QUELLE affectation (peu importe son
     * scope_type), un rôle système, OU un rôle marquant au moins une permission en
     * portée globale (case "Toutes les boutiques" cochée sur au moins une clé de ce
     * rôle — voir permissionIsGlobalOnRole()). Complète anyGrantedStoreIds() pour
     * PermissionMiddleware : celle-ci ne collecte que les scope_id des affectations
     * scope_type='store' et ne consulte jamais getGlobalPermissionKeys(), donc ne peut
     * pas détecter qu'une affectation store-scope porte, via son rôle, une permission
     * volontairement rendue illimitée. Sans ce signal, la porte grossière des routes
     * 'public' (self-service/agrégats, et notamment /storage/{path*} qui sert tous les
     * fichiers uploadés) restait à tort bornée au store d'origine de l'affectation —
     * bug reproduit avec photos.view marquée globale sur un rôle affecté en store-scope :
     * la liste des rapports (route à permission précise) montrait tous les magasins,
     * mais leurs images (servies via /storage/{path*}, route 'public') non.
     * @param array $authUser Utilisateur authentifié (au minimum ['id' => int])
     */
    public function hasAnyGlobalPermissionGrant(array $authUser): bool
    {
        $userId = (int) ($authUser['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }
        foreach ($this->assignments->findByUser($userId) as $assignment) {
            $roleId = (int) $assignment['role_id'];
            $role   = $this->role($roleId);
            if ($role === null) {
                continue;
            }
            if (!empty($role['is_system'])) {
                return true;
            }
            if ($this->roles->getGlobalPermissionKeys($roleId) !== []) {
                return true;
            }
        }
        return false;
    }

    /**
     * Charge une ressource par id via $finder, vérifie que $permissionKey est accordée
     * sur son store RÉEL (jamais celui, optionnel, fourni par le client) — le pattern
     * "findById() + can()" que chaque contrôleur de ressource {id} devait ré-écrire à la
     * main, et dont l'omission a produit l'IDOR inter-store trouvé et corrigé le
     * 11/09/2026 dans 5 bundles (ShiftSwap, TimeOff, Timeclock, ShiftClaim, Feedback) :
     * ApiPermissionMiddleware ne peut borner la portée en amont que si le client fournit
     * lui-même store_id, ce qu'aucune route {id} n'exige. Utiliser cette méthode pour tout
     * nouveau show/update/destroy plutôt que de refaire le couple à la main.
     *
     * @param callable(int): (array|null) $finder Ex. fn(int $id) => $this->repo->findById($id)
     * @throws NotFoundException Si $finder($id) retourne null.
     * @throws ForbiddenException Si $permissionKey n'est pas accordée sur le store réel de la ressource.
     */
    public function requireOwnedResource(
        array $authUser,
        callable $finder,
        int $id,
        string $permissionKey,
        string $storeField = 'store_id',
        ?string $notFoundMessage = null,
    ): array {
        $item = $finder($id);
        if ($item === null) {
            throw new NotFoundException($notFoundMessage ?? __('error_resource_not_found'));
        }
        if (!$this->can($authUser, $permissionKey, (int) ($item[$storeField] ?? 0))) {
            throw new ForbiddenException(__('error_permission_insufficient', ['key' => $permissionKey]));
        }
        return $item;
    }

    /**
     * Identifiants des utilisateurs détenant un rôle système (Owner) en
     * portée globale — remplace le filtre historique sur la colonne legacy
     * users.is_admin (ex. la liste des responsables proposée dans les
     * formulaires de rapport de démission/salaire). Même définition que
     * AuthService::hasOwnerRole() (rôle is_system en portée globale, pas un
     * slug codé en dur) pour ne jamais diverger de ce qui fait réellement foi
     * pour l'autorisation.
     * @return int[]
     */
    public function ownerUserIds(): array
    {
        $ids = [];
        foreach ($this->assignments->findByScope('global', null) as $assignment) {
            $role = $this->role((int) $assignment['role_id']);
            if ($role !== null && !empty($role['is_system'])) {
                $ids[] = (int) $assignment['user_id'];
            }
        }
        return array_values(array_unique($ids));
    }

    private function matchesScope(array $assignment, ?int $storeId, string $permissionKey): bool
    {
        if ($storeId === null) {
            return true;
        }
        if ($assignment['scope_type'] === 'global') {
            return true;
        }
        if ($this->permissionIsGlobalOnRole((int) $assignment['role_id'], $permissionKey)) {
            return true;
        }
        return (int) ($assignment['scope_id'] ?? 0) === $storeId;
    }

    /**
     * Vrai si $permissionKey est marquée en portée 'global' sur ce rôle (case
     * "Toutes les boutiques" cochée dans l'éditeur de rôle) — indépendant de la
     * portée (scope_type/scope_id) de l'affectation qui relie l'utilisateur à
     * ce rôle : permet à un rôle store-scope (ex. Manager) d'accorder certaines
     * permissions sur toutes les boutiques (ex. shifts.view) tout en gardant
     * les autres restreintes au store de l'affectation (ex. shifts.update).
     */
    private function permissionIsGlobalOnRole(int $roleId, string $permissionKey): bool
    {
        if (!array_key_exists($roleId, $this->globalKeysCache)) {
            $this->globalKeysCache[$roleId] = $this->roles->getGlobalPermissionKeys($roleId);
        }
        return in_array($permissionKey, $this->globalKeysCache[$roleId], true);
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
