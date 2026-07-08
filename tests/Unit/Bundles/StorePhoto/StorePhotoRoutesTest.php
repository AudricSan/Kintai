<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Bundles\StorePhoto;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use kintai\Core\Container;
use kintai\Core\Middleware\AdminMiddleware;
use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Router;

/**
 * Régression : les routes /admin/photos du bundle StorePhoto doivent rester
 * protégées par AuthMiddleware + AdminMiddleware après leur extraction du core,
 * exactement comme avant l'extraction.
 */
final class StorePhotoRoutesTest extends TestCase
{
    #[DataProvider('photosRoutesProvider')]
    public function testPhotosRoutesRequireAdminAuth(string $method, string $path): void
    {
        $router = new Router();
        $container = new Container();
        require dirname(__DIR__, 4) . '/src/Bundles/StorePhoto/routes.php';

        [$route] = $router->dispatch($method, $path);

        $this->assertContains(AuthMiddleware::class, $route->middleware, "$method $path must require auth");
        $this->assertContains(AdminMiddleware::class, $route->middleware, "$method $path must require admin");
    }

    public static function photosRoutesProvider(): array
    {
        return [
            ['GET', '/admin/photos'],
            ['GET', '/admin/photos/settings'],
            ['POST', '/admin/photos/settings'],
            ['GET', '/admin/photos/create'],
            ['POST', '/admin/photos/create'],
            ['POST', '/admin/photos/1/upload'],
            ['GET', '/admin/photos/1/2'],
            ['POST', '/admin/photos/1/2/delete'],
        ];
    }
}
