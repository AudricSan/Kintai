<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\SalaryReport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use kintai\Core\Container;
use kintai\Core\Middleware\AdminMiddleware;
use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Router;

/**
 * Régression : les routes /admin/reports/salary et
 * /admin/stores/{id}/reports/salary/* du bundle SalaryReport doivent rester
 * protégées après leur extraction du core.
 */
final class SalaryReportRoutesTest extends TestCase
{
    private function loadRoutes(): Router
    {
        $router = new Router();
        $container = new Container();
        require dirname(__DIR__, 4) . '/src/Bundles/SalaryReport/routes.php';

        return $router;
    }

    #[DataProvider('adminRoutesProvider')]
    public function testAdminRoutesRequireAdminAuth(string $method, string $path): void
    {
        [$route] = $this->loadRoutes()->dispatch($method, $path);

        $this->assertContains(AuthMiddleware::class, $route->middleware);
        $this->assertContains(AdminMiddleware::class, $route->middleware);
    }

    public static function adminRoutesProvider(): array
    {
        return [
            ['GET', '/admin/reports/salary'],
            ['GET', '/admin/stores/1/reports/salary'],
            ['GET', '/admin/stores/1/reports/salary/create'],
            ['POST', '/admin/stores/1/reports/salary/create'],
            ['GET', '/admin/stores/1/reports/salary/10'],
            ['GET', '/admin/stores/1/reports/salary/10/edit'],
            ['POST', '/admin/stores/1/reports/salary/10/edit'],
            ['POST', '/admin/stores/1/reports/salary/10/delete'],
            ['GET', '/admin/stores/1/reports/salary/10/pdf'],
        ];
    }
}
