<?php

declare(strict_types=1);

namespace kintai\Core;

use kintai\Core\BundleContract\Bundle as ContractBundle;
use kintai\Core\BundleContract\BundleManifest;
use kintai\Core\Services\Log;

/**
 * Découvre les bundles disponibles depuis deux sources, fusionnées en une
 * seule liste :
 *  - le monorepo legacy (src/Bundles/*, convention de nom de classe) ;
 *  - les bundles installés dynamiquement (storage/bundles/, un par slug
 *    listé dans installed.json, chacun avec son propre bundle.json).
 *
 * Un bundle tiers déposé manuellement dans src/Bundles/ (namespace
 * `kintai\Bundles\{Nom}\{Nom}Bundle`, PSR-4) est détecté au même titre qu'un
 * bundle du dépôt d'origine. Voir config/official-bundles.php pour la
 * distinction officiel / tiers, faite ailleurs (pas ici) : ce service se
 * contente de lister ce qui existe.
 *
 * Les instances créées ici servent uniquement à lire des métadonnées
 * (getName/getLabel/getDescription/getVersion) : on les construit
 * volontairement sans passer par Bundle::__construct() (donc sans
 * Application ni accès container) pour que la découverte reste bon marché et
 * sans effet de bord, même pour un bundle tiers mal écrit. L'instanciation
 * "réelle" avec Application a lieu séparément, dans
 * BundleManager::registerBundle(), uniquement pour un bundle effectivement
 * activé.
 */
final class BundleDiscoveryService
{
    private readonly string $bundlesDir;
    private readonly InstalledBundleManifestStore $installedStore;
    private readonly string $installedBundlesDir;

    public function __construct(
        ?string $bundlesDir = null,
        ?InstalledBundleManifestStore $installedStore = null,
        ?string $installedBundlesDir = null,
    ) {
        $this->bundlesDir = $bundlesDir ?? BASE_PATH . '/src/Bundles';
        $this->installedStore = $installedStore ?? new InstalledBundleManifestStore();
        $this->installedBundlesDir = $installedBundlesDir ?? storage_path('bundles');
    }

    /**
     * @return array<string, array{class: class-string<ContractBundle>, label: string, description: string, version: string}>
     *         Clé = slug retourné par Bundle::getName().
     */
    public function discover(): array
    {
        $discovered = $this->discoverLegacy();

        foreach ($this->discoverInstalled() as $slug => $meta) {
            if (isset($discovered[$slug])) {
                // Ne devrait jamais arriver en usage normal (un slug legacy migré
                // vers l'installation dynamique doit d'abord être retiré de
                // src/Bundles/) : le legacy gagne plutôt que de risquer de charger
                // deux implémentations différentes du même slug.
                Log::warning('bundle_discovery_slug_collision', ['slug' => $slug]);
                continue;
            }
            $discovered[$slug] = $meta;
        }

        return $discovered;
    }

    /**
     * @return array<string, array{class: class-string<ContractBundle>, label: string, description: string, version: string}>
     */
    private function discoverLegacy(): array
    {
        $discovered = [];

        if (!is_dir($this->bundlesDir)) {
            return $discovered;
        }

        $entries = scandir($this->bundlesDir);
        if ($entries === false) {
            return $discovered;
        }

        foreach ($entries as $dirName) {
            if ($dirName === '.' || $dirName === '..') {
                continue;
            }
            if (!is_dir($this->bundlesDir . '/' . $dirName)) {
                continue;
            }

            $class = "kintai\\Bundles\\{$dirName}\\{$dirName}Bundle";
            if (!class_exists($class) || !is_subclass_of($class, ContractBundle::class)) {
                continue;
            }

            try {
                /** @var ContractBundle $instance */
                $instance = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
                $discovered[$instance->getName()] = [
                    'class'       => $class,
                    'label'       => $instance->getLabel(),
                    'description' => $instance->getDescription(),
                    'version'     => $instance->getVersion(),
                ];
            } catch (\Throwable) {
                // Un bundle tiers mal formé ne doit jamais faire planter le boot ou l'admin.
                continue;
            }
        }

        return $discovered;
    }

    /**
     * @return array<string, array{class: class-string<ContractBundle>, label: string, description: string, version: string}>
     */
    private function discoverInstalled(): array
    {
        $discovered = [];

        foreach ($this->installedStore->all() as $slug => $info) {
            $version = $info['active_version'] ?? null;
            if (!is_string($version) || $version === '') {
                continue;
            }

            $bundleRoot = $this->installedBundlesDir . '/' . $slug . '/' . $version;
            $manifestPath = $bundleRoot . '/bundle.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            $data = json_decode((string) file_get_contents($manifestPath), true);
            $manifest = BundleManifest::fromArray($data);
            if ($manifest === null || $manifest->slug !== $slug) {
                continue;
            }

            if (!class_exists($manifest->entryClass) || !is_subclass_of($manifest->entryClass, ContractBundle::class)) {
                continue;
            }

            try {
                /** @var ContractBundle $instance */
                $instance = (new \ReflectionClass($manifest->entryClass))->newInstanceWithoutConstructor();
                $discovered[$slug] = [
                    'class'       => $manifest->entryClass,
                    'label'       => $instance->getLabel(),
                    'description' => $instance->getDescription(),
                    'version'     => $manifest->version,
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return $discovered;
    }
}
