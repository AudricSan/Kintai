<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core;

use kintai\Core\Container;
use kintai\Core\FeatureManager;
use PHPUnit\Framework\TestCase;

/**
 * Régression : désactiver un bundle (ex. shift-swap) ne doit jamais faire planter
 * les vues qui génèrent des route_url() vers ses routes (sidebar, bottom nav,
 * dashboards). bundle_enabled()/feat_bundle() sont le garde-fou utilisé par ces
 * vues ; ce test vérifie qu'ils reflètent bien l'état réel de FeatureManager.
 */
final class HelpersFeatBundleTest extends TestCase
{
    protected function setUp(): void
    {
        Container::getInstance()->instance(
            FeatureManager::class,
            new FeatureManager(['daily-report', 'messaging', 'timeoff', 'timeclock'])
        );
    }

    public function testBundleEnabledReflectsFeatureManagerState(): void
    {
        $this->assertTrue(bundle_enabled('timeoff'));
        $this->assertFalse(bundle_enabled('shift-swap'));
    }

    public function testFeatBundleResolvesStoreFeatureSlugToItsOwningBundle(): void
    {
        // 'swaps' (store_features) dépend du bundle 'shift-swap', désactivé ici.
        $this->assertFalse(feat_bundle('swaps'));
        // 'timeoff' est activé.
        $this->assertTrue(feat_bundle('timeoff'));
    }

    public function testFeatBundleAllowsSlugsWithNoOwningBundle(): void
    {
        // Un slug store_features sans bundle associé (ou null) n'est jamais bloqué ici :
        // c'est une fonctionnalité Core, toujours disponible.
        $this->assertTrue(feat_bundle(null));
        $this->assertTrue(feat_bundle('shifts'));
    }
}
