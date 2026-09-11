<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core;

use kintai\Core\Auth\PermissionCatalog;
use kintai\Core\Container;
use kintai\Core\Middleware\ApiPermissionMiddleware;
use kintai\Core\Middleware\PermissionMiddleware;
use kintai\Core\Route;
use kintai\Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Invariants de la déclaration RBAC désormais portée par chaque route
 * (Route::$permission, paramètre `permission:` de Router::get/post/...) :
 * toute route passée sous PermissionMiddleware/ApiPermissionMiddleware doit
 * déclarer soit une clé de PermissionCatalog (éventuellement enrobée dans une
 * règle ['perm' => ..., 'self'/'membership'/'store_param' => ...]), soit la
 * chaîne 'public' pour une exemption volontaire — jamais rien du tout. C'est
 * la même invariant que l'ancien PermissionMapsTest (qui comparait
 * config/permissions.php / config/api-permissions.php à deux allowlists
 * maintenues à la main), mais elle ne peut plus avoir d'angle mort : une
 * route qui n'a jamais reçu le middleware n'est de toute façon jamais
 * dispatchée par le pipeline sans contrôle — voir CHANGELOG pour l'historique
 * du bundle Messaging, resté hors RBAC pendant des mois avec l'ancien système
 * à deux fichiers séparés.
 */
final class PermissionMapsTest extends TestCase
{
    /** @return Route[] */
    private function allRoutes(): array
    {
        $root = dirname(__DIR__, 3);
        $router = new Router();
        $container = new Container();

        require $root . '/config/routes.php';
        foreach (glob($root . '/src/Bundles/*/routes.php') as $bundleRoutes) {
            require $bundleRoutes;
        }

        return $router->routes();
    }

    public function testEveryPermissionMiddlewareRouteDeclaresAPermission(): void
    {
        foreach ($this->allRoutes() as $route) {
            if (!in_array(PermissionMiddleware::class, $route->middleware, true)) {
                continue;
            }
            $this->assertNotNull($route->name, 'Route sans nom sous PermissionMiddleware : ' . $route->pattern);
            $this->assertNotNull(
                $route->permission,
                "Route '{$route->name}' passe par PermissionMiddleware mais ne déclare pas de " .
                "paramètre permission: (une clé de PermissionCatalog, ou 'public' si l'absence " .
                'est volontaire).'
            );
        }
    }

    public function testEveryApiPermissionMiddlewareRouteDeclaresAPermission(): void
    {
        foreach ($this->allRoutes() as $route) {
            if (!in_array(ApiPermissionMiddleware::class, $route->middleware, true)) {
                continue;
            }
            $this->assertNotNull($route->name, 'Route sans nom sous ApiPermissionMiddleware : ' . $route->pattern);
            $this->assertNotNull(
                $route->permission,
                "Route '{$route->name}' passe par ApiPermissionMiddleware mais ne déclare pas de " .
                "paramètre permission: (une clé de PermissionCatalog, ou 'public' si l'absence " .
                'est volontaire).'
            );
        }
    }

    public function testEveryDeclaredPermissionOnlyReferencesCatalogKeysAndValidRuleShapes(): void
    {
        foreach ($this->allRoutes() as $route) {
            $rule = $route->permission;
            if ($rule === null || $rule === 'public') {
                continue;
            }

            $isWebRoute = in_array(PermissionMiddleware::class, $route->middleware, true);
            $allowedOptions = $isWebRoute
                ? ['perm', 'membership', 'store_param']
                : ['perm', 'self', 'membership'];

            if (is_array($rule)) {
                $this->assertArrayHasKey('perm', $rule, "Route {$route->name} : une règle tableau doit avoir une entrée 'perm'.");
                $key = $rule['perm'];
                foreach (array_keys($rule) as $option) {
                    $this->assertContains($option, $allowedOptions, "Route {$route->name} : option de règle inconnue '$option'.");
                }
            } else {
                $this->assertIsString($rule);
                $key = $rule;
            }

            $this->assertTrue(
                PermissionCatalog::exists($key),
                "Route {$route->name} : la clé '$key' n'existe pas dans PermissionCatalog."
            );
        }
    }

    public function testManagerDefaultsAreAllValidCatalogKeys(): void
    {
        foreach (PermissionCatalog::MANAGER_DEFAULTS as $key) {
            $this->assertTrue(
                PermissionCatalog::exists($key),
                "MANAGER_DEFAULTS contient une clé inconnue du catalogue : '$key'."
            );
        }
    }

    public function testCategoryBundleMapOnlyReferencesCatalogCategories(): void
    {
        foreach (array_keys(PermissionCatalog::CATEGORY_BUNDLES) as $category) {
            $this->assertArrayHasKey(
                $category,
                PermissionCatalog::CATEGORIES,
                "CATEGORY_BUNDLES référence une catégorie inconnue : '$category'."
            );
        }
    }
}
