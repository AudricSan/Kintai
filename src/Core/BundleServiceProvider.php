<?php

declare(strict_types=1);

namespace kintai\Core;

final class BundleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $bundleManager = $this->container->make(BundleManager::class);
        $featureManager = $this->container->make(FeatureManager::class);
        $availableBundles = $this->loadBundlesConfig();

        foreach ($availableBundles as $featureKey => $bundleClass) {
            if ($featureManager->isEnabled($featureKey)) {
                $bundleManager->registerBundle($bundleClass);
            }
        }
    }

    private function loadBundlesConfig(): array
    {
        $path = BASE_PATH . '/config/bundles.php';
        if (file_exists($path)) {
            return require $path;
        }

        return [];
    }
}
