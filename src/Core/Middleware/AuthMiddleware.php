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
            return Response::redirect($this->base() . '/login');
        }

        $user = $auth->user();

        // Rend l'utilisateur disponible dans la requête (pour les contrôleurs)
        $request->setAttribute('auth_user', $user);

        // Partage avec toutes les vues (pour le layout)
        $view = $this->container->make(ViewRenderer::class);
        $view->share('auth_user', $user);
        $view->share('view_mode', $_SESSION['view_mode'] ?? 'admin');
        $view->share('auth_is_manager', $auth->isManager());

        return $next($request);
    }

    private function base(): string
    {
        $sn   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base = rtrim(str_replace('\\', '/', dirname($sn)), '/');
        return ($base === '.' || $base === '/') ? '' : $base;
    }
}
