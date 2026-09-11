<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\UI\ViewRenderer;

/**
 * Contrôle des permissions fines du système RBAC.
 * Doit être placé après AdminMiddleware (auth_user et managed_store_ids déjà
 * attachés à la requête).
 *
 * La règle requise par route est déclarée directement sur la route elle-même
 * (paramètre permission: de Router::get/post/..., voir Route::$permission) :
 * une clé de PermissionCatalog, ou tableau ['perm' => clé, 'membership' =>
 * true] — voir le format documenté sur Route::$permission. Pour un non-Owner :
 * - accès refusé (403) si aucun de ses rôles n'accorde la clé (et, si la
 *   règle porte 'membership', si l'utilisateur n'est pas non plus membre du
 *   store ciblé — porte d'entrée grossière pour un accès en libre-service,
 *   ex. bundle DailyReport) ;
 * - sinon, managed_store_ids est resserré aux seuls stores où la clé est
 *   accordée — les contrôleurs filtrant déjà toutes leurs données par cet
 *   attribut, la portée de chaque permission s'applique sans les modifier.
 * Une route sans règle 'permission' déclarée (null) reste soumise au seul
 * filtre d'AdminMiddleware — voir tests/Unit/Core/PermissionMapsTest, qui
 * fait échouer la suite si une route sous ce middleware n'a ni permission
 * précise ni marqueur 'public' explicite.
 */
final class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly ViewRenderer $view,
        private readonly StoreUserRepositoryInterface $storeUsers,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user    = $request->getAttribute('auth_user') ?? [];
        $isOwner = !empty($user['is_admin']); // Owner (rôle système, ponté sur is_admin par AuthService)

        // Le helper de vue user_can est partagé par AuthMiddleware (toutes les
        // pages authentifiées) pour que la navigation reste identique partout ;
        // ce middleware ne s'occupe plus que du contrôle d'accès et de la
        // portée managed_store_ids.
        $rule = $request->getAttribute('route_permission');
        if ($rule === null || $rule === 'public' || $isOwner) {
            return $next($request);
        }

        $key               = is_array($rule) ? (string) $rule['perm'] : $rule;
        $requireMembership = is_array($rule) && !empty($rule['membership']);
        $storeParam        = is_array($rule) ? ($rule['store_param'] ?? 'id') : 'id';

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

        // Porte d'entrée volontairement grossière pour un accès en libre-service :
        // n'écrase pas managed_store_ids (la portée fine reste gérée par le
        // contrôleur, ex. DailyReportPermissionService + findMembership()).
        if ($requireMembership) {
            $storeId = (int) ($request->param($storeParam) ?? 0);
            if ($storeId > 0 && $this->storeUsers->findMembership($storeId, $userId) !== null) {
                return $next($request);
            }
        }

        throw new ForbiddenException('Permission requise : ' . $key);
    }
}
