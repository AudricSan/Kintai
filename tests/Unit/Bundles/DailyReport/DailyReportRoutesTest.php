<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\DailyReport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use kintai\Core\Container;
use kintai\Core\Middleware\AdminMiddleware;
use kintai\Core\Middleware\ApiAuthMiddleware;
use kintai\Core\Middleware\ApiPermissionMiddleware;
use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Middleware\PermissionMiddleware;
use kintai\Core\Router;

/**
 * Régression : les routes du bundle DailyReport doivent rester protégées par
 * PermissionMiddleware/ApiPermissionMiddleware après leur ajout au système
 * déclaratif de permissions, SANS gagner AdminMiddleware sur le groupe en
 * libre-service (ça bloquerait tout employé qui n'est manager d'aucun store,
 * qui doit pouvoir créer/soumettre son propre rapport).
 */
final class DailyReportRoutesTest extends TestCase
{
    private function loadRoutes(): Router
    {
        $router = new Router();
        $container = new Container();
        require dirname(__DIR__, 4) . '/src/Bundles/DailyReport/routes.php';

        return $router;
    }

    #[DataProvider('selfServiceRoutesProvider')]
    public function testSelfServiceRoutesAreAuthenticatedAndPermissionCheckedButNotAdminOnly(string $method, string $path): void
    {
        [$route] = $this->loadRoutes()->dispatch($method, $path);

        $this->assertContains(AuthMiddleware::class, $route->middleware);
        $this->assertContains(PermissionMiddleware::class, $route->middleware);
        $this->assertNotContains(
            AdminMiddleware::class,
            $route->middleware,
            "$path ne doit pas exiger AdminMiddleware : un simple employé doit pouvoir soumettre son propre rapport."
        );
    }

    public static function selfServiceRoutesProvider(): array
    {
        return [
            ['GET', '/admin/stores/1/daily-reports'],
            ['GET', '/admin/stores/1/daily-reports/create'],
            ['POST', '/admin/stores/1/daily-reports/create'],
            ['GET', '/admin/stores/1/daily-reports/10'],
            ['GET', '/admin/stores/1/daily-reports/10/edit'],
            ['POST', '/admin/stores/1/daily-reports/10/edit'],
            ['POST', '/admin/stores/1/daily-reports/10/submit'],
            ['POST', '/admin/stores/1/daily-reports/10/validate'],
            ['POST', '/admin/stores/1/daily-reports/10/delete'],
        ];
    }

    public function testIndexAllRouteRequiresAdminAndPermission(): void
    {
        [$route] = $this->loadRoutes()->dispatch('GET', '/admin/daily-reports');

        $this->assertContains(AuthMiddleware::class, $route->middleware);
        $this->assertContains(AdminMiddleware::class, $route->middleware);
        $this->assertContains(PermissionMiddleware::class, $route->middleware);
    }

    #[DataProvider('apiRoutesProvider')]
    public function testApiRoutesRequireTokenAuthAndPermission(string $method, string $path): void
    {
        [$route] = $this->loadRoutes()->dispatch($method, $path);

        $this->assertContains(ApiAuthMiddleware::class, $route->middleware);
        $this->assertContains(ApiPermissionMiddleware::class, $route->middleware);
    }

    public static function apiRoutesProvider(): array
    {
        return [
            ['GET', '/api/v1/daily-reports'],
            ['POST', '/api/v1/daily-reports'],
            ['GET', '/api/v1/daily-reports/10'],
            ['PUT', '/api/v1/daily-reports/10'],
            ['DELETE', '/api/v1/daily-reports/10'],
            ['POST', '/api/v1/daily-reports/10/submit'],
            ['POST', '/api/v1/daily-reports/10/validate'],
        ];
    }
}
