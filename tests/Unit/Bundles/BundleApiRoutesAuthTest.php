<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use kintai\Core\Container;
use kintai\Core\Middleware\ApiAuthMiddleware;
use kintai\Core\Router;

/**
 * Régression : les routes /api/v1/* des bundles Messaging et DailyReport
 * doivent être protégées par ApiAuthMiddleware, comme toutes les autres
 * routes /api/v1/*. Elles ne l'étaient pas avant ce correctif.
 */
final class BundleApiRoutesAuthTest extends TestCase
{
    private function loadRoutes(string $path): Router
    {
        $router = new Router();
        $container = new Container();
        require $path;

        return $router;
    }

    #[DataProvider('messagingApiRoutesProvider')]
    public function testMessagingApiRoutesRequireAuth(string $method, string $path): void
    {
        $router = $this->loadRoutes(dirname(__DIR__, 3) . '/src/Bundles/Messaging/routes.php');

        [$route] = $router->dispatch($method, $path);

        $this->assertContains(
            ApiAuthMiddleware::class,
            $route->middleware,
            "$method $path must be protected by ApiAuthMiddleware"
        );
    }

    public static function messagingApiRoutesProvider(): array
    {
        return [
            ['GET', '/api/v1/messages/threads'],
            ['POST', '/api/v1/messages/threads'],
            ['GET', '/api/v1/messages/threads/1/messages'],
            ['POST', '/api/v1/messages/threads/1/messages'],
            ['GET', '/api/v1/messages/threads/1/participants'],
            ['POST', '/api/v1/messages/threads/1/participants'],
            ['GET', '/api/v1/messages/threads/1/participants/2'],
            ['GET', '/api/v1/messages/threads/1'],
            ['DELETE', '/api/v1/messages/threads/1'],
            ['DELETE', '/api/v1/messages/1'],
        ];
    }

    #[DataProvider('dailyReportApiRoutesProvider')]
    public function testDailyReportApiRoutesRequireAuth(string $method, string $path): void
    {
        $router = $this->loadRoutes(dirname(__DIR__, 3) . '/src/Bundles/DailyReport/routes.php');

        [$route] = $router->dispatch($method, $path);

        $this->assertContains(
            ApiAuthMiddleware::class,
            $route->middleware,
            "$method $path must be protected by ApiAuthMiddleware"
        );
    }

    public static function dailyReportApiRoutesProvider(): array
    {
        return [
            ['POST', '/api/v1/daily-reports/1/submit'],
            ['POST', '/api/v1/daily-reports/1/validate'],
            ['GET', '/api/v1/daily-reports'],
            ['POST', '/api/v1/daily-reports'],
            ['GET', '/api/v1/daily-reports/1'],
            ['PUT', '/api/v1/daily-reports/1'],
            ['DELETE', '/api/v1/daily-reports/1'],
        ];
    }
}
