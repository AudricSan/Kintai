<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Auth\AuthService;
use kintai\Core\Container;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\UI\ViewRenderer;

/**
 * Middleware de contrôle du rôle admin / manager.
 * Doit être placé après AuthMiddleware dans la chaîne (auth_user déjà attaché).
 *
 * - Admin global (is_admin = 1) : accès complet, managed_store_ids = null.
 * - Manager de store (rôle admin|manager dans store_user) : accès restreint à ses stores,
 *   managed_store_ids = [int, ...] injecté dans la requête et les vues.
 * - Autres : redirigé vers /employee.
 */
final class AdminMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Container $container) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->getAttribute('auth_user');

        /** @var AuthService $auth */
        $auth = $this->container->make(AuthService::class);

        // Admin global → accès complet, aucune restriction de store
        if (!empty($user['is_admin'])) {
            $this->container->make(ViewRenderer::class)->share('managed_store_ids', null);
            return $next($request);
        }

        // Vérifier si gestionnaire d'au moins un store
        $managedIds = $auth->managedStoreIds();

        if (empty($managedIds)) {
            return Response::redirect($this->base() . '/employee');
        }

        // Injecter la liste des stores gérés pour que les contrôleurs et vues puissent filtrer
        $request->setAttribute('managed_store_ids', $managedIds);
        $this->container->make(ViewRenderer::class)->share('managed_store_ids', $managedIds);

        return $next($request);
    }

    private function base(): string
    {
        $sn   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base = rtrim(str_replace('\\', '/', dirname($sn)), '/');
        return ($base === '.' || $base === '/') ? '' : $base;
    }
}
