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
 * Invariants des maps de permissions (config/permissions.php et
 * config/api-permissions.php) : toute clé référencée doit exister dans
 * PermissionCatalog, sinon la route serait silencieusement inaccessible
 * pour tout non-Owner.
 */
final class PermissionMapsTest extends TestCase
{
    private function webMap(): array
    {
        return require dirname(__DIR__, 3) . '/config/permissions.php';
    }

    private function apiMap(): array
    {
        return require dirname(__DIR__, 3) . '/config/api-permissions.php';
    }

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

    /**
     * Routes sous PermissionMiddleware volontairement absentes de
     * config/permissions.php (voir le docblock de ce fichier pour le détail
     * de chaque cas) : pages Owner explicitement gardées par requireOwner(),
     * préférences personnelles en libre-service, ou tableau de bord agrégeant
     * plusieurs permissions déjà vérifiées individuellement dans le contrôleur.
     */
    private const WEB_ROUTE_EXEMPTIONS = [
        'home',
        'admin.owner_settings', 'admin.owner_settings.save',
        'admin.nav_settings', 'admin.nav_settings.save',
        'admin.requests',
        'admin.mail_test', 'admin.mail_test.send',
        'admin.backup', 'admin.backup.download', 'admin.backup.create',
        'admin.backup.restore', 'admin.backup.delete', 'admin.backup.delete_all',
        'admin.reset.prepare', 'admin.reset.execute',
        'admin.update', 'admin.update.apply', 'admin.update.stream',
        'admin.update.migrate', 'admin.update.channel',
        'admin.languages', 'admin.languages.store', 'admin.languages.set_default',
        'admin.languages.toggle_active', 'admin.languages.delete',
        'admin.languages.edit', 'admin.languages.edit.save', 'admin.languages.edit.delete',
        'admin.bundles', 'admin.bundles.save',
        'admin.roles', 'admin.roles.create', 'admin.roles.store',
        'admin.roles.edit', 'admin.roles.update', 'admin.roles.delete',
    ];

    /**
     * Routes sous ApiPermissionMiddleware volontairement absentes de
     * config/api-permissions.php (voir le docblock de ce fichier) : opérations
     * du porteur du token (auth.*) et notifications strictement limitées à
     * l'utilisateur du token par leur contrôleur.
     */
    private const API_ROUTE_EXEMPTIONS = [
        'api.v1.auth.logout', 'api.v1.auth.me',
        'api.v1.auth.tokens', 'api.v1.auth.tokens.revoke',
        'api.v1.notifications.index', 'api.v1.notifications.show',
        'api.v1.notifications.read', 'api.v1.notifications.read_all',
        'api.v1.notifications.destroy',
    ];

    /**
     * Régression : une route passée dans le groupe PermissionMiddleware mais
     * absente de config/permissions.php (et de WEB_ROUTE_EXEMPTIONS ci-dessus)
     * redevient silencieusement soumise au seul filtre grossier
     * d'AdminMiddleware (voir PermissionMiddleware::rule() — c'est ainsi que
     * 'admin.photos.image.rotate' et plusieurs routes *_download/toggle/preview
     * ajoutées après coup avaient été oubliées).
     */
    public function testEveryPermissionMiddlewareRouteIsMapped(): void
    {
        $map = $this->webMap();
        foreach ($this->allRoutes() as $route) {
            if (!in_array(PermissionMiddleware::class, $route->middleware, true)) {
                continue;
            }
            $this->assertNotNull($route->name, 'Route sans nom sous PermissionMiddleware : ' . $route->pattern);
            if (in_array($route->name, self::WEB_ROUTE_EXEMPTIONS, true)) {
                continue;
            }
            $this->assertArrayHasKey(
                $route->name,
                $map,
                "Route '{$route->name}' passe par PermissionMiddleware mais n'a pas d'entrée dans config/permissions.php " .
                '(ni dans WEB_ROUTE_EXEMPTIONS si absence volontaire).'
            );
        }
    }

    public function testEveryApiPermissionMiddlewareRouteIsMapped(): void
    {
        $map = $this->apiMap();
        foreach ($this->allRoutes() as $route) {
            if (!in_array(ApiPermissionMiddleware::class, $route->middleware, true)) {
                continue;
            }
            $this->assertNotNull($route->name, 'Route sans nom sous ApiPermissionMiddleware : ' . $route->pattern);
            if (in_array($route->name, self::API_ROUTE_EXEMPTIONS, true)) {
                continue;
            }
            $this->assertArrayHasKey(
                $route->name,
                $map,
                "Route '{$route->name}' passe par ApiPermissionMiddleware mais n'a pas d'entrée dans config/api-permissions.php " .
                '(ni dans API_ROUTE_EXEMPTIONS si absence volontaire).'
            );
        }
    }

    public function testWebMapOnlyReferencesCatalogKeys(): void
    {
        foreach ($this->webMap() as $route => $rule) {
            if (is_array($rule)) {
                $this->assertArrayHasKey('perm', $rule, "Route $route : une règle tableau doit avoir une entrée 'perm'.");
                $key = $rule['perm'];
                foreach (array_keys($rule) as $option) {
                    $this->assertContains($option, ['perm', 'membership', 'store_param'], "Route $route : option de règle inconnue '$option'.");
                }
            } else {
                $this->assertIsString($rule);
                $key = $rule;
            }
            $this->assertTrue(
                PermissionCatalog::exists($key),
                "Route $route : la clé '$key' n'existe pas dans PermissionCatalog."
            );
        }
    }

    public function testApiMapOnlyReferencesCatalogKeysAndValidRuleShapes(): void
    {
        foreach ($this->apiMap() as $route => $rule) {
            if (is_array($rule)) {
                $this->assertArrayHasKey('perm', $rule, "Route $route : une règle tableau doit avoir une entrée 'perm'.");
                $key = $rule['perm'];
                foreach (array_keys($rule) as $option) {
                    $this->assertContains($option, ['perm', 'self', 'membership'], "Route $route : option de règle inconnue '$option'.");
                }
            } else {
                $this->assertIsString($rule);
                $key = $rule;
            }
            $this->assertTrue(
                PermissionCatalog::exists($key),
                "Route $route : la clé '$key' n'existe pas dans PermissionCatalog."
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
