<?php

declare(strict_types=1);

namespace kintai\Core;

use kintai\Core\Repositories\AppSettingsRepositoryInterface;

final class LicenseServiceProvider extends ServiceProvider
{
    public const SETTINGS_KEY = 'enabled_bundles';

    public function register(): void
    {
        $enabledFeatures = $this->loadEnabledFeatures();
        $this->container->instance(FeatureManager::class, new FeatureManager($enabledFeatures));
    }

    /**
     * Résout la liste des bundles activés, par ordre de priorité :
     * 1. Réglage Owner en base (`app_settings.enabled_bundles`, écrit depuis /admin/bundles) ;
     * 2. `config/license.php` (déploiements gérés / première installation) ;
     * 3. Défauts codés en dur.
     */
    private function loadEnabledFeatures(): array
    {
        $appSettings = $this->container->make(AppSettingsRepositoryInterface::class);
        $stored = $appSettings->get(self::SETTINGS_KEY);
        if ($stored !== null) {
            $decoded = json_decode($stored, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $path = BASE_PATH . '/config/license.php';
        if (file_exists($path)) {
            $config = require $path;
            return $config['enabled_features'] ?? [];
        }

        // Default to all core features enabled for self-hosted version if no license file.
        return ['daily-report', 'messaging', 'store-photos', 'shift-claim'];
    }
}
