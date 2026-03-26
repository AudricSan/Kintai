<?php

declare(strict_types=1);

namespace kintai\Core\Middleware;

use Closure;
use kintai\Core\Request;
use kintai\Core\Response;

/**
 * Middleware global : démarre la session PHP si elle n'est pas déjà active.
 * Doit être enregistré en premier dans la liste des middlewares globaux.
 */
final class SessionMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $next($request);
    }
}
