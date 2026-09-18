<?php

declare(strict_types=1);

namespace kintai\Core\Services\BundleRegistry;

/**
 * Une entrée d'un fichier de listing de registry (registry.json) : un bundle
 * disponible, pas encore forcément installé. Voir BundleRegistryListing pour
 * le format complet du fichier.
 */
final readonly class BundleRegistryEntry
{
    /**
     * @param string[] $versions Versions disponibles, la plus récente en premier.
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $description,
        public string $repositoryUrl,
        public array $versions,
    ) {
    }

    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }
        if (!isset($data['slug'], $data['repository_url']) || !is_string($data['slug']) || !is_string($data['repository_url'])) {
            return null;
        }
        if ($data['slug'] === '' || $data['repository_url'] === '') {
            return null;
        }

        $versions = $data['versions'] ?? [];
        if (!is_array($versions)) {
            $versions = [];
        }

        return new self(
            slug: $data['slug'],
            name: is_string($data['name'] ?? null) ? $data['name'] : $data['slug'],
            description: is_string($data['description'] ?? null) ? $data['description'] : '',
            repositoryUrl: $data['repository_url'],
            versions: array_values(array_map('strval', $versions)),
        );
    }
}
