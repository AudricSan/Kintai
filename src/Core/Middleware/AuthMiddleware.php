<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Auth\AuthService;
use kintai\Core\Container;
use kintai\Core\Repositories\LanguageRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserNavPrefsRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\EmployeeStatsService;
use kintai\Core\Services\TranslationService;
use kintai\UI\ViewRenderer;

/**
 * Middleware d'authentification.
 * Redirige vers /login si l'utilisateur n'est pas connecté.
 * Attache l'utilisateur courant à la requête ET le partage avec les vues.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var AuthService $auth */
        $auth = $this->container->make(AuthService::class);

        if (!$auth->check()) {
            return Response::redirect(base_url() . '/login');
        }

        $user = $auth->user();

        // Rend l'utilisateur disponible dans la requête (pour les contrôleurs)
        $request->setAttribute('auth_user', $user);

        // Partage avec toutes les vues (pour le layout)
        $view = $this->container->make(ViewRenderer::class);
        $view->share('auth_user', $user);
        $view->share('view_mode', $_SESSION['view_mode'] ?? 'admin');
        $view->share('auth_is_manager', $auth->isManager());

        // Langue courante
        $view->share('locale', $this->container->make(TranslationService::class)->getLocale());
        $view->share('active_languages', $this->container->make(LanguageRepositoryInterface::class)->findAllActive());

        // Fonctionnalités activées pour le store de l'utilisateur (null = tout activé)
        $storeFeatures = null;
        if (empty($user['is_admin'])) {
            $storeUsers = $this->container->make(StoreUserRepositoryInterface::class);
            $memberships = $storeUsers->findByUser((int) ($user['id'] ?? 0));
            if (!empty($memberships)) {
                $storeRepo = $this->container->make(StoreRepositoryInterface::class);
                // Aucune ligne en base = jamais configuré → tout activé (null)
                $features = $storeRepo->getFeatures((int) $memberships[0]['store_id']);
                $storeFeatures = $features !== [] ? $features : null;
            }
        }
        $view->share('store_features', $storeFeatures);

        // Statistiques du mois (widget salaire estimé, barre latérale vue employé) —
        // partagées sur toutes les pages, pas seulement celles qui le calculaient explicitement.
        if (empty($user['is_admin'])) {
            $employeeStats = $this->container->make(EmployeeStatsService::class);
            $month = (string) ($request->query('month') ?? '');
            $view->share('employee_month_stats', $employeeStats->calculate((int) ($user['id'] ?? 0), $month));
        }

        // Préférences de navigation (tous les utilisateurs)
        $navPrefs = $this->container->make(UserNavPrefsRepositoryInterface::class);
        $uid = (int) ($user['id'] ?? 0);
        $view->share('user_nav_hidden', $navPrefs->getHidden($uid));
        $view->share('user_nav_section_order', $navPrefs->getSectionOrder($uid));
        $view->share('user_bottom_nav_items', $navPrefs->getBottomNavItems($uid));

        return $next($request);
    }

}
