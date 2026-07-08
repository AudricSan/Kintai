<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Auth\AuthService;
use kintai\Core\Container;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Repositories\NotificationRepositoryInterface;
use kintai\UI\ViewRenderer;

/**
 * Injecte dans toutes les vues :
 *  - $unread_notifications_count  : entier pour le badge
 *  - $recent_notifications        : notifs non lues créées depuis la dernière visite (pour les toasts)
 *  - $notifications_dropdown      : les 10 dernières (toutes) pour le dropdown cloche
 *
 * Utilise $_SESSION['last_notif_check'] pour déterminer les "nouvelles" notifs.
 */
final class NotificationMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Container $container) {}

    public function handle(Request $request, Closure $next): Response
    {
        $auth = $this->container->make(AuthService::class);
        $view = $this->container->make(ViewRenderer::class);

        $recent   = [];
        $dropdown = [];
        $count    = 0;

        if ($auth->check()) {
            $user   = $auth->user();
            $userId = (int) $user['id'];

            try {
                $repo = $this->container->make(NotificationRepositoryInterface::class);

                $count    = $repo->countUnread($userId);
                $dropdown = $repo->findByUser($userId, 10);

                $since  = $_SESSION['last_notif_check'] ?? date('Y-m-d H:i:s');
                $recent = $repo->findUnreadSince($userId, $since);
            } catch (\Throwable) {
                // Table pas encore créée : tout à zéro
            }
        }

        $view->share('unread_notifications_count', $count);
        $view->share('notifications_dropdown', $dropdown);
        $view->share('recent_notifications', $recent);

        $response = $next($request);

        // Mettre à jour le timestamp après que la réponse est générée
        if ($auth->check()) {
            $_SESSION['last_notif_check'] = date('Y-m-d H:i:s');
        }

        return $response;
    }
}
