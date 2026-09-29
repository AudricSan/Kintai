<?php

declare(strict_types=1);

namespace kintai\Tests\Support;

use kintai\Core\BundleContract\Bundle;
use kintai\Core\BundleManager;

/**
 * Construit un BundleManager reflétant un ensemble donné de slugs "actifs",
 * sans passer par une vraie Application (lourde, final) — voir
 * BundleManagerTest pour le détail de la technique (newInstanceWithoutConstructor
 * + injection réflexive de $bundles). À utiliser dans tout test qui simule un
 * bundle activé/désactivé/désinstallé pour du code passant par bundle_enabled()/
 * feat_bundle() (src/Core/helpers.php), qui lisent BundleManager::isActive()
 * depuis le CHANGELOG "extraction DailyReport" — pas FeatureManager::isEnabled()
 * seul, qui ne reflète que le réglage stocké, pas la présence réelle sur le disque.
 */
final class FakeBundleManagerFactory
{
    /** @param string[] $activeSlugs */
    public static function withActiveSlugs(array $activeSlugs): BundleManager
    {
        $manager = (new \ReflectionClass(BundleManager::class))->newInstanceWithoutConstructor();

        $bundles = [];
        foreach ($activeSlugs as $slug) {
            $bundles[$slug] = new class ($slug) extends Bundle {
                public function __construct(private readonly string $slug)
                {
                }

                public function getName(): string
                {
                    return $this->slug;
                }

                public function register(): void
                {
                }
            };
        }

        $bundlesProperty = new \ReflectionProperty(BundleManager::class, 'bundles');
        $bundlesProperty->setAccessible(true);
        $bundlesProperty->setValue($manager, $bundles);

        return $manager;
    }
}
