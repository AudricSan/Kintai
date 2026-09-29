<?php

declare(strict_types=1);

namespace kintai\Core\Services\BundleRegistry;

use kintai\Core\Repositories\BundleRegistryRepositoryInterface;

/**
 * Agrège les listings de tous les registries configurés en un seul
 * catalogue pour l'écran d'installation (à venir dans une prochaine
 * itération, une fois BundleInstallerService disponible).
 */
final class BundleCatalogService
{
    public function __construct(
        private readonly BundleRegistryRepositoryInterface $registries,
        private readonly BundleRegistryClient $client,
    ) {
    }

    /**
     * @return BundleCatalogEntry[]
     */
    public function listAvailableBundles(): array
    {
        $catalog = [];
        foreach ($this->registries->all() as $registry) {
            $listing = $this->client->fetchListing($registry['url']);
            if ($listing === null) {
                continue;
            }
            foreach ($listing->bundles as $entry) {
                $catalog[] = new BundleCatalogEntry($entry, $registry['name'], $registry['url']);
            }
        }
        return $catalog;
    }

    public function isOfficial(string $slug): bool
    {
        $path = BASE_PATH . '/config/official-bundles.php';
        $officialSlugs = file_exists($path) ? (require $path) : [];
        return in_array($slug, $officialSlugs, true);
    }
}
