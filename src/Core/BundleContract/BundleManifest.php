<?php

declare(strict_types=1);

namespace kintai\Core\BundleContract;

use kintai\Contract\Contract;
use kintai\Contract\VersionConstraint;

/**
 * Contenu parsé et validé du bundle.json d'un bundle installé dynamiquement
 * (voir docs/creating-a-bundle.md pour le format complet). Fait partie du
 * contrat stable au même titre que Bundle : un auteur de bundle s'engage sur
 * ces champs, ils ne changent pas sans annonce au CHANGELOG.
 */
final readonly class BundleManifest
{
    /** Racine PSR-4 du code du bundle, relative à la racine de son dépôt/archive. */
    private const SOURCE_ROOT = 'src';

    /**
     * @param array<string, string> $requiresBundles Slug de bundle => contrainte de version (informatif pour l'instant).
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $version,
        public string $description,
        public string $namespace,
        public string $entryClass,
        public string $kintaiCoreMin,
        public string $kintaiCoreMax,
        public array $requiresBundles,
        // Contrainte semver sur la version du contrat (« kintai_contract » : « ^1.0 »), ou null si le bundle ne la
        // déclare pas encore : on retombe alors sur kintai_core.min/max (bundles antérieurs au contrat versionné).
        public ?string $contractConstraint = null,
    ) {
    }

    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }

        foreach (['slug', 'name', 'version', 'namespace', 'entry_class'] as $required) {
            if (!isset($data[$required]) || !is_string($data[$required]) || $data[$required] === '') {
                return null;
            }
        }

        $core = is_array($data['kintai_core'] ?? null) ? $data['kintai_core'] : [];
        $requiresBundles = is_array($data['requires_bundles'] ?? null) ? $data['requires_bundles'] : [];

        return new self(
            slug: $data['slug'],
            name: $data['name'],
            version: $data['version'],
            description: is_string($data['description'] ?? null) ? $data['description'] : '',
            namespace: $data['namespace'],
            entryClass: $data['entry_class'],
            kintaiCoreMin: is_string($core['min'] ?? null) ? $core['min'] : '0.0.0',
            kintaiCoreMax: is_string($core['max'] ?? null) ? $core['max'] : '999.999.999',
            requiresBundles: array_map('strval', $requiresBundles),
            contractConstraint: is_string($data['kintai_contract'] ?? null) && trim($data['kintai_contract']) !== ''
                ? trim($data['kintai_contract'])
                : null,
        );
    }

    /**
     * Raison pour laquelle ce bundle ne peut pas tourner sur ce Core, ou null s'il est compatible. La contrainte
     * de contrat prime ; sans elle, l'intervalle kintai_core.min/max s'applique (ancien format).
     */
    public function incompatibilityReason(string $coreVersion, string $contractVersion = Contract::VERSION): ?string
    {
        if ($this->contractConstraint !== null) {
            $constraint = VersionConstraint::parse($this->contractConstraint);
            if ($constraint === null) {
                return __('bundle_contract_constraint_invalid', ['constraint' => $this->contractConstraint]);
            }
            return $constraint->allows($contractVersion)
                ? null
                : __('bundle_contract_incompatible', ['required' => $this->contractConstraint, 'provided' => $contractVersion]);
        }

        return $this->isCompatibleWithCore($coreVersion)
            ? null
            : __('bundle_core_incompatible', ['min' => $this->kintaiCoreMin, 'max' => $this->kintaiCoreMax, 'current' => $coreVersion]);
    }

    public function isCompatibleWithCore(string $coreVersion): bool
    {
        return version_compare($coreVersion, $this->kintaiCoreMin, '>=')
            && version_compare($coreVersion, $this->kintaiCoreMax, '<=');
    }

    /**
     * Chemin du fichier PHP correspondant à une classe de ce bundle sous
     * $bundleRootDir (racine où bundle.json lui-même se trouve), ou null si
     * la classe n'appartient pas au namespace déclaré par ce manifeste.
     */
    public function classFilePath(string $bundleRootDir, string $class): ?string
    {
        $prefix = rtrim($this->namespace, '\\') . '\\';
        if (!str_starts_with($class, $prefix)) {
            return null;
        }

        $relative = substr($class, strlen($prefix));
        return rtrim($bundleRootDir, '/') . '/' . self::SOURCE_ROOT . '/' . str_replace('\\', '/', $relative) . '.php';
    }
}
