<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core;

use kintai\Core\Container;
use kintai\Core\Middleware\RateLimiterMiddleware;
use kintai\Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Régression : `/api/v1/auth/login` est un point d'entrée public (aucun token
 * requis), comme `/login` et `/forgot-password` — il doit rester protégé par
 * le même throttling anti-bruteforce qu'eux.
 */
final class RoutesTest extends TestCase
{
    public function testApiLoginRouteIsRateLimited(): void
    {
        $router    = new Router();
        $container = new Container();
        require dirname(__DIR__, 3) . '/config/routes.php';

        [$route] = $router->dispatch('POST', '/api/v1/auth/login');

        $this->assertContains(RateLimiterMiddleware::class, $route->middleware);
    }
}
