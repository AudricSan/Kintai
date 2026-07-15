<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\UI\ViewRenderer;

/**
 * Contrôle des permissions fines du système RBAC (task/mermission.md).
 * Doit être placé après AdminMiddleware (auth_user et managed_store_ids déjà
 * attachés à la requête).
 *
 * La permission requise par route est déclarée dans config/permissions.php
 * (nom de route → clé de PermissionCatalog). Pour un non-Owner :
 * - accès refusé (403) si aucun de ses rôles n'accorde la clé ;
 * - sinon, managed_store_ids est resserré aux seuls stores où la clé est
 *   accordée — les contrôleurs filtrant déjà toutes leurs données par cet
 *   attribut, la portée de chaque permission s'applique sans les modifier.
 * Une route absente de la map reste soumise au seul filtre d'AdminMiddleware.
 */
final class PermissionMiddleware implements MiddlewareInterface
{
    /** @var array<string, string>|null */
    private static ?array $routePermissions = null;

    public function __construct(
        private readonly PermissionService $permissions,
        private readonly ViewRenderer $view,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user    = $request->getAttribute('auth_user') ?? [];
        $isOwner = !empty($user['is_admin']); // Owner (rôle système, ponté sur is_admin par AuthService)

        // Le helper de vue user_can est partagé par AuthMiddleware (toutes les
        // pages authentifiées) pour que la navigation reste identique partout ;
        // ce middleware ne s'occupe plus que du contrôle d'accès et de la
        // portée managed_store_ids.
        $key = $this->requiredPermission((string) ($request->getAttribute('route_name') ?? ''));
        if ($key === null || $isOwner) {
            return $next($request);
        }

        $userId = (int) ($user['id'] ?? 0);
        $scoped = $this->permissions->scopedStoreIds($userId, $key);
        if ($scoped !== []) {
            $request->setAttribute('managed_store_ids', $scoped);
            $this->view->share('managed_store_ids', $scoped);
            return $next($request);
        }

        // Affectation de portée globale (rôle non-système accordant la clé partout)
        if ($this->permissions->can($user, $key, null)) {
            return $next($request);
        }

        throw new ForbiddenException('Permission requise : ' . $key);
    }

    private function requiredPermission(string $routeName): ?string
    {
        if ($routeName === '') {
            return null;
        }
        if (self::$routePermissions === null) {
            $file = dirname(__DIR__, 3) . '/config/permissions.php';
            self::$routePermissions = is_file($file) ? (require $file) : [];
        }
        return self::$routePermissions[$routeName] ?? null;
    }
}
