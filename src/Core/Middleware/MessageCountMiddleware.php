<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Auth\AuthService;
use kintai\Core\Container;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Repositories\MessageRepositoryInterface;
use kintai\UI\ViewRenderer;

/**
 * Injecte le compteur de messages non-lus dans toutes les vues authentifiées.
 * Partage `$unread_messages_count` pour afficher le badge dans la sidebar.
 */
final class MessageCountMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Container $container) {}

    public function handle(Request $request, Closure $next): Response
    {
        $auth = $this->container->make(AuthService::class);

        if ($auth->check()) {
            $user  = $auth->user();
            $count = 0;
            try {
                $repo  = $this->container->make(MessageRepositoryInterface::class);
                $count = $repo->countUnread((int) $user['id']);
            } catch (\Throwable) {
                // Table pas encore créée : on affiche 0
            }
            $this->container->make(ViewRenderer::class)->share('unread_messages_count', $count);
        }

        return $next($request);
    }
}
