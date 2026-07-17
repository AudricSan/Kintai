<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core;

use kintai\Core\Auth\PermissionCatalog;
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

    public function testWebMapOnlyReferencesCatalogKeys(): void
    {
        foreach ($this->webMap() as $route => $key) {
            $this->assertIsString($key, "La règle web de $route doit être une clé simple.");
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
                    $this->assertContains($option, ['perm', 'self'], "Route $route : option de règle inconnue '$option'.");
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
