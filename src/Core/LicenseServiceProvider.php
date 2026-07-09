<?php

declare(strict_types=1);

namespace kintai\Core;

final class LicenseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $enabledFeatures = $this->loadEnabledFeatures();
        $this->container->instance(FeatureManager::class, new FeatureManager($enabledFeatures));
    }

    private function loadEnabledFeatures(): array
    {
        // For now, load from a simple config file.
        // In the future, this would check a signed license file or a database setting.
        $path = BASE_PATH . '/config/license.php';
        if (file_exists($path)) {
            $config = require $path;
            return $config['enabled_features'] ?? [];
        }

        // Default to all core features enabled for self-hosted version if no license file.
        return ['daily-report', 'messaging', 'store-photos'];
    }
}
