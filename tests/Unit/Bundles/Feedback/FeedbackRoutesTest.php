<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\Feedback;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use kintai\Core\Container;
use kintai\Core\Middleware\AdminMiddleware;
use kintai\Core\Middleware\ApiAuthMiddleware;
use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Router;

/**
 * Régression : les routes /employee/feedback*, /admin/feedbacks/* et
 * /api/v1/feedbacks/* du bundle Feedback doivent rester protégées après
 * leur extraction du core.
 */
final class FeedbackRoutesTest extends TestCase
{
    private function loadRoutes(): Router
    {
        $router = new Router();
        $container = new Container();
        require dirname(__DIR__, 4) . '/src/Bundles/Feedback/routes.php';

        return $router;
    }

    #[DataProvider('employeeRoutesProvider')]
    public function testEmployeeRoutesRequireAuth(string $method, string $path): void
    {
        [$route] = $this->loadRoutes()->dispatch($method, $path);

        $this->assertContains(AuthMiddleware::class, $route->middleware);
    }

    public static function employeeRoutesProvider(): array
    {
        return [
            ['POST', '/employee/feedback'],
            ['GET', '/employee/feedback/past-shifts'],
        ];
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
            ['GET', '/admin/feedbacks'],
            ['POST', '/admin/feedbacks/10/delete'],
        ];
    }

    #[DataProvider('apiRoutesProvider')]
    public function testApiRoutesRequireApiAuth(string $method, string $path): void
    {
        [$route] = $this->loadRoutes()->dispatch($method, $path);

        $this->assertContains(ApiAuthMiddleware::class, $route->middleware);
    }

    public static function apiRoutesProvider(): array
    {
        return [
            ['GET', '/api/v1/feedbacks'],
            ['POST', '/api/v1/feedbacks'],
            ['GET', '/api/v1/feedbacks/10'],
            ['PUT', '/api/v1/feedbacks/10'],
            ['DELETE', '/api/v1/feedbacks/10'],
        ];
    }
}
