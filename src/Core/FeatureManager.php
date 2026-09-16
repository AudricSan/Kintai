<?php

declare(strict_types=1);

namespace kintai\Core;

final class FeatureManager
{
    /**
     * Association slug de feature par-store (store_features, coché sur la fiche
     * magasin) → slug du bundle Core dont il dépend. Un slug absent d'ici est
     * une fonctionnalité Core, toujours disponible quel que soit l'état des
     * bundles. Source de vérité unique : réutilisée par AdminStoreController
     * (quelles cases proposer sur la fiche magasin) et par les vues (sidebar,
     * bottom nav, dashboards) pour ne pas générer de lien vers une route dont
     * le bundle est désactivé au niveau de l'instance.
     */
    public const STORE_FEATURE_BUNDLE_MAP = [
        'messages'      => 'messaging',
        'daily_reports' => 'daily-report',
        'photos'        => 'store-photos',
        'timeoff'       => 'timeoff',
        'swaps'         => 'shift-swap',
        'open_shifts'   => 'shift-claim',
        'timeclock'     => 'timeclock',
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
