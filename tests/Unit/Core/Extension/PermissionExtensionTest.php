<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core\Extension;

use kintai\Core\Auth\PermissionCatalog;
use kintai\Core\BundleContract\Bundle;
use kintai\Core\BundleManager;
use kintai\Core\Container;
use kintai\Core\Extension\ExtensionRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Point d'extension « permissions » (étape 2d du contrat de bundle) : catégories déclarées par les bundles actifs,
 * protection des catégories du Core, repli historique pour les bundles qui ne déclarent rien.
 */
final class PermissionExtensionTest extends TestCase
{
    private ?Container $previous = null;

    protected function setUp(): void
    {
        $prop = new \ReflectionProperty(Container::class, 'instance');
        $this->previous = $prop->getValue();
        $prop->setValue(null, null);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Container::class, 'instance'))->setValue(null, $this->previous);
    }

    /** @param array<string, mixed> $active slug => permissions déclarées (array, ou \Throwable levée) */
    private function registry(array $active): ExtensionRegistry
    {
        $bundles = [];
        foreach ($active as $slug => $declared) {
            $bundle = new class extends Bundle {
                public string $slug = '';
                public mixed $declared = [];

                public function __construct() {}

                public function getName(): string
                {
                    return $this->slug;
                }

                public function register(): void {}

                public function permissions(): array
                {
                    if ($this->declared instanceof \Throwable) {
                        throw $this->declared;
                    }
                    return $this->declared;
                }
            };
            $bundle->slug = $slug;
            $bundle->declared = $declared;
            $bundles['B' . $slug] = $bundle;
        }
        $manager = (new \ReflectionClass(BundleManager::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($manager, 'bundles'))->setValue($manager, $bundles);

        $registry = new ExtensionRegistry($manager);
        Container::getInstance()->instance(BundleManager::class, $manager);
        Container::getInstance()->instance(ExtensionRegistry::class, $registry);
        return $registry;
    }

    public function testDeclaredCategoriesAreCollected(): void
    {
        $registry = $this->registry(['acme' => ['acme_tasks' => ['view', 'edit', 'view']]]);

        $this->assertSame(['acme_tasks' => ['view', 'edit']], $registry->permissions(PermissionCatalog::CORE_CATEGORIES));
        $this->assertSame(['acme'], $registry->bundlesDeclaringPermissions());
    }

    public function testCoreDuplicateAndMalformedCategoriesAreIgnored(): void
    {
        $registry = $this->registry([
            'acme'  => ['shifts' => ['delete_all'], 'acme_ok' => ['view'], 'Bad-Name' => ['view'], 'acme_empty' => [], 'acme_bad' => ['DROP TABLE']],
            'other' => ['acme_ok' => ['steal']],
            'boom'  => new \RuntimeException('kaput'),
        ]);

        $this->assertSame(['acme_ok' => ['view']], $registry->permissions(PermissionCatalog::CORE_CATEGORIES));
    }

    public function testCatalogMergesCoreDeclaredAndLegacyFallback(): void
    {
        // timeoff ne déclare rien : repli sur ses catégories historiques ; shift-swap déclare : les siennes remplacent
        // l'historique ; daily-report est inactif : sa catégorie disparaît.
        $this->registry(['timeoff' => [], 'shift-swap' => ['shift_swaps_v2' => ['view']]]);

        $categories = PermissionCatalog::forActiveBundles();

        foreach (PermissionCatalog::CORE_CATEGORIES as $core) {
            $this->assertArrayHasKey($core, $categories);
        }
        $this->assertArrayHasKey('shift_swaps_v2', $categories);
        foreach (PermissionCatalog::CATEGORY_BUNDLES as $category => $bundle) {
            if ($bundle === 'timeoff') {
                $this->assertArrayHasKey($category, $categories);
            } else {
                $this->assertArrayNotHasKey($category, $categories, $category);
            }
        }
    }

    /** Une catégorie déclarée garde sa place historique ; une nouvelle catégorie vient après. */
    public function testCategoriesKeepTheHistoricalOrder(): void
    {
        $this->registry([
            'acme'     => ['acme_tasks' => ['view']],
            'daily-report' => ['daily_reports' => ['view']],
            'timeoff'  => [],
            'messaging' => ['messaging' => ['view', 'send', 'delete']],
        ]);

        $this->assertSame(
            ['employees', 'shifts', 'stores', 'payroll', 'timeoff', 'daily_reports', 'messaging', 'acme_tasks'],
            array_keys(PermissionCatalog::forActiveBundles())
        );
    }

    public function testCoreCategoriesHaveNoOwningBundle(): void
    {
        foreach (PermissionCatalog::CORE_CATEGORIES as $core) {
            $this->assertArrayHasKey($core, PermissionCatalog::CATEGORIES);
            $this->assertArrayNotHasKey($core, PermissionCatalog::CATEGORY_BUNDLES);
        }
        $this->assertSame(
            PermissionCatalog::CORE_CATEGORIES,
            array_values(array_diff(array_keys(PermissionCatalog::CATEGORIES), array_keys(PermissionCatalog::CATEGORY_BUNDLES)))
        );
    }
}
