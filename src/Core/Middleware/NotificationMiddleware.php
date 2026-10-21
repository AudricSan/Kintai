<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Auth\AuthService;
use kintai\Core\Container;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\VapidKeyStore;
use kintai\Core\Repositories\NotificationRepositoryInterface;
use kintai\UI\ViewRenderer;

/**
 * Injecte dans toutes les vues :
 *  - $unread_notifications_count  : entier pour le badge
 *  - $notifications_dropdown      : les 10 dernières (toutes) pour le dropdown cloche
 *  - $notifications_last_id       : curseur de départ du polling (id de la plus récente)
 *
 * Les toasts des notifications reçues entre deux pages sont gérés par notifications.js (dernier id vu, mémorisé
 * dans le navigateur), plus par un horodatage de session : celui-ci, tronqué à la seconde et réécrit à chaque
 * requête (poll compris), faisait perdre ou doubler des notifications.
 *
 * Sans effet sur les requêtes AJAX/JSON (dont le poll de la cloche) : elles ne rendent pas la topbar, inutile d'y
 * refaire ces requêtes à chaque appel.
 */
final class NotificationMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Container $container) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->wantsJson()) {
            return $next($request);
        }

        $auth = $this->container->make(AuthService::class);
        $view = $this->container->make(ViewRenderer::class);

        $dropdown = [];
        $count    = 0;

        if ($auth->check()) {
            $user   = $auth->user();
            $userId = (int) $user['id'];

            try {
                $repo = $this->container->make(NotificationRepositoryInterface::class);

                $count    = $repo->countUnread($userId);
                $dropdown = $repo->findByUser($userId, 10);
            } catch (\Throwable) {
                // Table pas encore créée : tout à zéro
            }
        }

        $view->share('unread_notifications_count', $count);
        $view->share('notifications_dropdown', $dropdown);
        $view->share('notifications_last_id', (int) ($dropdown[0]['id'] ?? 0));

        // Config publique du web push natif : la seule clé VAPID publique (la privée ne quitte jamais le serveur).
        // Vide quand le web push n'est pas opérationnel (clés absentes ou extension PHP manquante), pour que push.js
        // ne charge rien et que le profil l'indique.
        $pushWebConfig = [];
        if ($auth->check()) {
            try {
                $keys = $this->container->make(VapidKeyStore::class);
                if ($keys->isAvailable()) {
                    $pushWebConfig = ['vapid_key' => (string) $keys->publicKey()];
                }
            } catch (\Throwable) {
                // Réglages indisponibles : pas de web push sur cette requête.
            }
        }
        $view->share('push_web_config', $pushWebConfig);

        return $next($request);
    }
}
