<?php

declare(strict_types=1);

namespace kintai\Core\Services\BundleRegistry;

/**
 * Contenu parsé et validé d'un fichier registry.json. Un registry plus
 * récent que ce que cette version de Kintai sait lire (schema_version
 * supérieur) est rejeté proprement par fromArray() plutôt que mal interprété.
 */
final readonly class BundleRegistryListing
{
    public const SUPPORTED_SCHEMA_VERSION = 1;

    /**
     * @param BundleRegistryEntry[] $bundles
     */
    public function __construct(
        public string $name,
        public array $bundles,
    ) {
    }

    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }
        if (($data['schema_version'] ?? null) !== self::SUPPORTED_SCHEMA_VERSION) {
            return null;
        }
        if (!is_array($data['bundles'] ?? null)) {
            return null;
        }

        $bundles = [];
        foreach ($data['bundles'] as $entry) {
            $parsed = BundleRegistryEntry::fromArray($entry);
            if ($parsed !== null) {
                $bundles[] = $parsed;
            }
        }

        return new self(
            name: is_string($data['name'] ?? null) ? $data['name'] : '',
            bundles: $bundles,
        );
    }
}
