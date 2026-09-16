<?php

declare(strict_types=1);

namespace kintai\Core;

final class BundleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $bundleManager = $this->container->make(BundleManager::class);
        $featureManager = $this->container->make(FeatureManager::class);
        $discovery = new BundleDiscoveryService();

        foreach ($discovery->discover() as $slug => $meta) {
            if ($featureManager->isEnabled($slug)) {
                $bundleManager->registerBundle($meta['class']);
            }
        }
    }
}
