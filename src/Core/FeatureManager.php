<?php

declare(strict_types=1);

namespace kintai\Core;

final class FeatureManager
{
    /**
     * Association historique slug de feature par-store → slug du bundle officiel qui la porte. N'est plus lue par le
     * Core : les fonctionnalités par magasin viennent de kintai\Core\Extension\StoreFeatures (déclarations des
     * bundles, contrat 1.3, et repli historique). Conservée pour les bundles qui la lisaient encore ; disparaîtra
     * avec le repli (étape 5 du plan du contrat).
     *
     * @deprecated Utiliser kintai\Core\Extension\StoreFeatures.
     */
    public const STORE_FEATURE_BUNDLE_MAP = [
    ];

    private array $enabledFeatures = [];

    public function __construct(array $enabledFeatures = [])
    {
        $this->enabledFeatures = $enabledFeatures;
    }

    /**
     * Check if a specific feature or bundle is enabled.
     */
    public function isEnabled(string $feature): bool
    {
        return in_array($feature, $this->enabledFeatures, true);
    }

    /**
     * Enable a feature dynamically (e.g. after verifying a license).
     */
    public function enable(string $feature): void
    {
        if (!$this->isEnabled($feature)) {
            $this->enabledFeatures[] = $feature;
        }
    }

    /**
     * Get all enabled features.
     */
    public function getEnabledFeatures(): array
    {
        return $this->enabledFeatures;
    }
}
