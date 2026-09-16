<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Auth\AuthService;
use kintai\Core\Container;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Services\DailyReportPermissionService;
use kintai\UI\ViewRenderer;

/**
 * Calcule, pour les utilisateurs staff (non admin/manager), la liste des stores
 * pour lesquels ils sont autorisés à créer des rapports journaliers.
 * Partage `daily_report_staff_stores` avec toutes les vues pour afficher l'onglet nav.
 * Pour les admins/managers, la variable est partagée vide (ils ont déjà leur propre lien).
 */
final class DailyReportNavMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Container $container) {}

    public function handle(Request $request, Closure $next): Response
    {
        $auth = $this->container->make(AuthService::class);

        if (!$auth->check()) {
            return $next($request);
        }

        $user = $auth->user();
        $view = $this->container->make(ViewRenderer::class);

        // Admins et managers ont toujours leur propre lien "rapports journaliers"
        // dans le menu admin/manager (nav système/statistiques) : ce tableau ne
        // sert qu'à la nav employé, jamais affichée pour ces rôles.
        if (!empty($user['is_admin'])) {
            $view->share('managed_store_ids', null);
            $view->share('daily_report_staff_stores', []);
            return $next($request);
        }

        if ($auth->isManager()) {
            $view->share('managed_store_ids', $auth->managedStoreIds());
            $view->share('daily_report_staff_stores', []);
            return $next($request);
        }

        $storeUsers  = $this->container->make(StoreUserRepositoryInterface::class);
        $stores      = $this->container->make(StoreRepositoryInterface::class);
        $permissions = $this->container->make(DailyReportPermissionService::class);

        $memberships = $storeUsers->findByUser((int) $user['id']);
        $accessible  = [];

        foreach ($memberships as $membership) {
            $store = $stores->findById((int) $membership['store_id']);
            if ($store !== null && $permissions->canCreate($user, $store, $membership)) {
                $accessible[] = $store;
            }
        }

        $view->share('daily_report_staff_stores', $accessible);

        return $next($request);
    }
}
