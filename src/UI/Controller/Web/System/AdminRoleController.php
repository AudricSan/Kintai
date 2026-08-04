<?php

declare(strict_types=1);

namespace kintai\UI\Controller\Web\System;

use kintai\Core\Auth\PermissionCatalog;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\UI\Controller\Web\HasBaseUrl;
use kintai\UI\ViewRenderer;

/**
 * Gestion des rôles dynamiques (RBAC). Réservée à l'Owner (task/mermission.md,
 * phase 3) : rien ici n'est encore lu par AuthService/HasAdminAccess — créer,
 * modifier ou supprimer un rôle depuis cette page n'a aucun effet sur les
 * autorisations tant que la bascule n'a pas eu lieu.
 */
final class AdminRoleController
{
    use HasBaseUrl;

    /**
     * 'update' n'est pas réutilisable tel quel comme clé de traduction : elle
     * désigne déjà "Mises à jour" (mises à jour logicielles) ailleurs dans
     * l'app. Cette table associe chaque action du catalogue à sa propre clé.
     */
    private const ACTION_LABEL_KEYS = [
        'view'     => 'view',
        'create'   => 'create',
        'update'   => 'edit',
        'delete'   => 'delete',
        'import'   => 'perm_action_import',
        'export'   => 'perm_action_export',
        'validate' => 'perm_action_validate',
        'generate' => 'perm_action_generate',
        'approve'  => 'perm_action_approve',
        'publish'  => 'perm_action_publish',
        'submit'   => 'perm_action_submit',
    ];

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly RoleRepositoryInterface $roles,
        private readonly RoleAssignmentRepositoryInterface $assignments,
        private readonly UserRepositoryInterface $users,
        private readonly StoreRepositoryInterface $stores,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** GET /admin/roles */
    public function roles(Request $request): Response
    {
        $this->requireOwner($request);

        $roles = $this->roles->findAll();
        $counts = [];
        foreach ($roles as $role) {
            $counts[(int) $role['id']] = count($this->assignments->findByRole((int) $role['id']));
        }

        return Response::html($this->view->render('system.roles', [
            'title'             => __('roles'),
            'roles'             => $roles,
            'assignment_counts' => $counts,
            'success'           => isset($_GET['success']),
        ], 'layout.app'));
    }

    /** GET /admin/roles/create */
    public function createRole(Request $request): Response
    {
        $this->requireOwner($request);

        return Response::html($this->view->render('system.roles-form', [
            'title'                 => __('new_role'),
            'mode'                  => 'create',
            'role'                  => [],
            'permission_categories' => $this->visiblePermissionCategories(),
            'action_label_keys'     => self::ACTION_LABEL_KEYS,
            'granted_permissions'   => [],
            'holders'               => [],
        ], 'layout.app'));
    }

    /** POST /admin/roles/create */
    public function storeRole(Request $request): Response
    {
        $this->requireOwner($request);

        $name = trim($request->post('name', ''));
        $slug = $this->uniqueSlug($this->slugify($name));
        if ($name === '' || $slug === '') {
            return Response::redirect($this->base() . '/admin/roles/create?error=invalid_name');
        }

        $saved = $this->roles->save([
            'name'        => $name,
            'slug'        => $slug,
            'color'       => $request->post('color', '') ?: null,
            'description' => trim($request->post('description', '')) ?: null,
            'is_system'   => 0,
        ]);

        $permissions = $this->postedPermissions($request);
        $this->roles->savePermissions((int) $saved['id'], $permissions);

        $this->auditLogger->log($request, 'role.created', 'role', (int) $saved['id'], [
            'name'        => $name,
            'permissions' => $permissions,
        ]);
        return Response::redirect($this->base() . '/admin/roles?success=created');
    }

    /** GET /admin/roles/{id}/edit */
    public function editRole(Request $request): Response
    {
        $this->requireOwner($request);
        $role = $this->findRoleOrFail($request);

        return Response::html($this->view->render('system.roles-form', [
            'title'                 => 'Modifier ' . htmlspecialchars($role['name'] ?? ''),
            'mode'                  => 'edit',
            'role'                  => $role,
            'permission_categories' => $this->visiblePermissionCategories(),
            'action_label_keys'     => self::ACTION_LABEL_KEYS,
            'granted_permissions'   => $this->roles->getPermissions((int) $role['id']),
            'holders'               => $this->roleHolders((int) $role['id']),
        ], 'layout.app'));
    }

    /** POST /admin/roles/{id}/edit */
    public function updateRole(Request $request): Response
    {
        $this->requireOwner($request);
        $role = $this->findRoleOrFail($request);
        if (!empty($role['is_system'])) {
            throw new ForbiddenException('Le rôle Owner n\'est pas modifiable.');
        }

        $name = trim($request->post('name', $role['name'] ?? ''));
        if ($name === '') {
            return Response::redirect($this->base() . '/admin/roles/' . $role['id'] . '/edit?error=invalid_name');
        }

        $newRole = $this->roles->save([
            'id'          => $role['id'],
            'name'        => $name,
            'color'       => $request->post('color', '') ?: null,
            'description' => trim($request->post('description', '')) ?: null,
        ]);

        $oldPermissions = $this->roles->getPermissions((int) $role['id']);
        // Les catégories des bundles désactivés sont masquées du formulaire :
        // leurs clés déjà accordées sont préservées telles quelles, pour
        // réapparaître si le bundle est réactivé.
        $hiddenKeys = array_values(array_filter(
            $oldPermissions,
            fn(string $key): bool => !$this->isCategoryVisible(explode('.', $key)[0])
        ));
        $newPermissions = array_values(array_unique(array_merge($this->postedPermissions($request), $hiddenKeys)));
        $this->roles->savePermissions((int) $role['id'], $newPermissions);

        $this->auditLogger->logUpdate(
            $request,
            'role.updated',
            'role',
            (int) $role['id'],
            $role + ['permissions' => $oldPermissions],
            $newRole + ['permissions' => $newPermissions],
            [],
        );
        return Response::redirect($this->base() . '/admin/roles?success=updated');
    }

    /** POST /admin/roles/{id}/delete */
    public function deleteRole(Request $request): Response
    {
        $this->requireOwner($request);
        $role = $this->findRoleOrFail($request);

        if (!empty($role['is_system'])) {
            throw new ForbiddenException('Le rôle Owner ne peut pas être supprimé.');
        }
        if (count($this->assignments->findByRole((int) $role['id'])) > 0) {
            return Response::redirect($this->base() . '/admin/roles?error=role_in_use');
        }

        $this->roles->delete((int) $role['id']);
        $this->auditLogger->log($request, 'role.deleted', 'role', (int) $role['id'], [
            'name' => $role['name'] ?? null,
        ]);
        return Response::redirect($this->base() . '/admin/roles?success=deleted');
    }

    private function findRoleOrFail(Request $request): array
    {
        $role = $this->roles->findById((int) $request->param('id'));
        if ($role === null) {
            throw new NotFoundException('Rôle introuvable.');
        }
        return $role;
    }

    /** @return string[] Clés de permission cochées dans le formulaire. */
    private function postedPermissions(Request $request): array
    {
        $granted = [];
        foreach ($this->visiblePermissionCategories() as $category => $actions) {
            foreach ($actions as $action) {
                $key = $category . '.' . $action;
                if ($request->post('perm_' . str_replace('.', '_', $key))) {
                    $granted[] = $key;
                }
            }
        }
        return $granted;
    }

    /**
     * Catégories du catalogue dont le bundle porteur est activé (les
     * catégories Core, sans bundle associé, sont toujours visibles).
     */
    private function visiblePermissionCategories(): array
    {
        return array_filter(
            PermissionCatalog::CATEGORIES,
            fn(string $category): bool => $this->isCategoryVisible($category),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function isCategoryVisible(string $category): bool
    {
        $bundle = PermissionCatalog::CATEGORY_BUNDLES[$category] ?? null;
        return $bundle === null || bundle_enabled($bundle);
    }

    private function roleHolders(int $roleId): array
    {
        $holders = [];
        foreach ($this->assignments->findByRole($roleId) as $assignment) {
            $user = $this->users->findById((int) $assignment['user_id']);
            $scopeLabel = __('scope_global');
            if ($assignment['scope_type'] === 'store' && $assignment['scope_id'] !== null) {
                $store = $this->stores->findById((int) $assignment['scope_id']);
                $scopeLabel = $store['name'] ?? ('#' . $assignment['scope_id']);
            }
            $holders[] = [
                'assignment_id' => (int) $assignment['id'],
                'user_name'     => $user['display_name'] ?? ($user['email'] ?? ('#' . $assignment['user_id'])),
                'initials'      => $this->userInitials($user),
                'color'         => $user['color'] ?? '#6c5ce7',
                'scope_label'   => $scopeLabel,
            ];
        }
        return $holders;
    }

    /** Initiales nom+prénom (repli sur les 2 premiers caractères du nom affiché) — même règle que la colonne "Nom" de /admin/users. */
    private function userInitials(?array $user): string
    {
        $initials = mb_strtoupper(
            mb_substr((string) ($user['last_name'] ?? ''), 0, 1) . mb_substr((string) ($user['first_name'] ?? ''), 0, 1)
        );
        if ($initials === '') {
            $initials = mb_substr(strip_tags((string) ($user['display_name'] ?? '')), 0, 2);
        }
        return $initials;
    }

    private function slugify(string $name): string
    {
        $slug = mb_strtolower(trim($name));
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug) ?: $slug;
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        return trim($slug, '-');
    }

    private function uniqueSlug(string $base): string
    {
        if ($base === '') {
            return '';
        }
        $slug = $base;
        $attempt = 1;
        while ($this->roles->findBySlug($slug) !== null) {
            $attempt++;
            $slug = $base . '-' . $attempt;
        }
        return $slug;
    }

    private function requireOwner(Request $request): void
    {
        $user = $request->getAttribute('auth_user');
        if (empty($user['is_admin'])) {
            header('Location: ' . $this->base() . '/');
            exit;
        }
    }
}
