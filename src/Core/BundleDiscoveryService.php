<?php

declare(strict_types=1);

namespace kintai\Core;

/**
 * Découvre automatiquement les bundles présents dans src/Bundles/, sans
 * registre codé en dur — un bundle tiers déposé manuellement dans ce dossier
 * (namespace `kintai\Bundles\{Nom}\{Nom}Bundle`, PSR-4) est détecté au même
 * titre qu'un bundle du dépôt d'origine. Voir config/official-bundles.php
 * pour la distinction officiel / tiers, faite ailleurs (pas ici) : ce service
 * se contente de lister ce qui existe sur le disque.
 *
 * Les instances créées ici servent uniquement à lire des métadonnées
 * (getName/getLabel/getDescription) : on les construit volontairement sans
 * passer par Bundle::__construct() (donc sans Application ni accès container)
 * pour que la découverte reste bon marché et sans effet de bord, même pour
 * un bundle tiers mal écrit. L'instanciation "réelle" avec Application a
 * lieu séparément, dans BundleManager::registerBundle(), uniquement pour un
 * bundle effectivement activé.
 */
final class BundleDiscoveryService
{
    private readonly string $bundlesDir;

    public function __construct(?string $bundlesDir = null)
    {
        $this->bundlesDir = $bundlesDir ?? BASE_PATH . '/src/Bundles';
    }

    /**
     * @return array<string, array{class: class-string<Bundle>, label: string, description: string}>
     *         Clé = slug retourné par Bundle::getName().
     */
    public function discover(): array
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
            if (!class_exists($class) || !is_subclass_of($class, Bundle::class)) {
                continue;
            }

            try {
                /** @var Bundle $instance */
                $instance = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
                $discovered[$instance->getName()] = [
                    'class'       => $class,
                    'label'       => $instance->getLabel(),
                    'description' => $instance->getDescription(),
                ];
            } catch (\Throwable) {
                // Un bundle tiers mal formé ne doit jamais faire planter le boot ou l'admin.
                continue;
            }
        }

        return $discovered;
    }
}
