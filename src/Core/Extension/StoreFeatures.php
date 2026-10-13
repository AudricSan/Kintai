<?php

declare(strict_types=1);

namespace kintai\Core\Extension;

use kintai\Contract\Extension\StoreFeature;
use kintai\Core\BundleManager;
use kintai\Core\Container;

/**
 * Fonctionnalités activables par magasin (fiche magasin, table store_features), construites à partir de données :
 * - celles du Core (planning) ;
 * - celles déclarées par les bundles actifs (point d'extension Bundle::storeFeatures(), contrat 1.3) ;
 * - en repli, celles historiques d'un bundle actif qui ne déclare encore rien (bundles d'avant le contrat 1.3). Ce
 *   repli disparaîtra quand tous les bundles déclareront leurs fonctionnalités (étape 5 du plan du contrat).
 */
final class StoreFeatures
{
    public function __construct(
        private readonly ?ExtensionRegistry $registry = null,
        private readonly ?BundleManager $bundles = null,
    ) {}

    /** Instance de la requête courante (registre des extensions et bundles actifs), ou Core seul si indisponible. */
    public static function current(): self
    {
        try {
            $container = Container::getInstance();
            return new self(
                $container->has(ExtensionRegistry::class) ? $container->make(ExtensionRegistry::class) : null,
                $container->has(BundleManager::class) ? $container->make(BundleManager::class) : null,
            );
        } catch (\Throwable) {
            return new self();
        }
    }

    /** @return list<StoreFeature> */
    public static function coreFeatures(): array
    {
        return [new StoreFeature('shifts', 'feature_shifts', 'feature_shifts_desc', '📅', 10)];
    }

    /**
     * Fonctionnalités historiques des bundles officiels, par slug de bundle : proposées tant que le bundle est actif
     * et ne déclare pas les siennes.
     *
     * @return array<string, list<StoreFeature>>
     */
    public static function legacyBundleFeatures(): array
    {
        $f = static fn(string $key, string $icon, int $order): StoreFeature =>
            new StoreFeature($key, 'feature_' . $key, 'feature_' . $key . '_desc', $icon, $order);

        return [
            'timeclock'    => [$f('timeclock', '🕐', 20)],
            'timeoff'      => [$f('timeoff', '🏖', 30)],
            'shift-swap'   => [$f('swaps', '🔄', 40)],
            'shift-claim'  => [$f('open_shifts', '📋', 50)],
            'messaging'    => [$f('messages', '💬', 60)],
            'daily-report' => [$f('daily_reports', '📊', 70)],
            'store-photos' => [$f('photos', '📷', 80)],
            'notebook'     => [$f('notes', '📝', 90)],
        ];
    }

    /**
     * Fonctionnalités proposées sur la fiche magasin : Core, bundles actifs déclarants, repli des bundles actifs non
     * déclarants ; triées par ordre. Une clé déjà présente n'est pas reprise.
     *
     * @return list<StoreFeature>
     */
    public function available(): array
    {
        $features = self::coreFeatures();
        $declaring = $this->registry?->bundlesDeclaringStoreFeatures() ?? [];
        foreach (self::legacyBundleFeatures() as $slug => $legacy) {
            if ($this->bundles?->isActive($slug) && !in_array($slug, $declaring, true)) {
                array_push($features, ...$legacy);
            }
        }
        array_push($features, ...($this->registry?->storeFeatures() ?? []));

        $unique = [];
        foreach ($features as $feature) {
            $unique[$feature->key] ??= $feature;
        }
        $unique = array_values($unique);
        usort($unique, static fn(StoreFeature $a, StoreFeature $b): int => [$a->order, $a->key] <=> [$b->order, $b->key]);
        return $unique;
    }

    /** @return list<string> */
    public function availableKeys(): array
    {
        return array_map(static fn(StoreFeature $f): string => $f->key, $this->available());
    }

    /**
     * Une fonctionnalité de bundle est-elle utilisable sur cette instance ? true si elle est proposée (Core ou bundle
     * actif) ou si la clé est inconnue (pas une fonctionnalité de bundle) ; false pour une fonctionnalité historique
     * d'un bundle inactif. À combiner avec l'activation par magasin, qui ne couvre pas ce niveau.
     */
    public function isAvailable(string $key): bool
    {
        if (in_array($key, $this->availableKeys(), true)) {
            return true;
        }
        foreach (self::legacyBundleFeatures() as $legacy) {
            foreach ($legacy as $feature) {
                if ($feature->key === $key) {
                    return false;
                }
            }
        }
        return true;
    }
}
