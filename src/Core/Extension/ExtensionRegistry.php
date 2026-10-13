<?php

declare(strict_types=1);

namespace kintai\Core\Extension;

use kintai\Contract\Extension\NavItem;
use kintai\Core\BundleContract\Bundle;
use kintai\Core\BundleManager;
use kintai\Core\Services\Log;

/**
 * Collecte les contributions des bundles actifs aux points d'extension du Core (contrat 1.1, étape 2 du plan).
 *
 * Chaque bundle est interrogé isolément : une exception, ou une valeur qui n'est pas du type attendu, retire sa
 * contribution et l'inscrit au journal, sans casser la page (même principe que WidgetGuard pour les widgets).
 * Les résultats sont mis en cache pour la durée de la requête.
 */
final class ExtensionRegistry
{
    /** @var list<NavItem>|null */
    private ?array $navigation = null;

    public function __construct(private readonly BundleManager $bundles) {}

    /**
     * Entrées de navigation des bundles actifs, triées par section puis par ordre. Une clé déjà fournie par un
     * autre bundle est ignorée (la première gagne).
     *
     * @return list<NavItem>
     */
    public function navigation(): array
    {
        if ($this->navigation !== null) {
            return $this->navigation;
        }

        $items = [];
        $seen = [];
        foreach ($this->collect('navigation', NavItem::class) as $item) {
            if (isset($seen[$item->key])) {
                Log::warning('bundle_extension_duplicate_key', ['point' => 'navigation', 'key' => $item->key]);
                continue;
            }
            $seen[$item->key] = true;
            $items[] = $item;
        }

        usort($items, static fn(NavItem $a, NavItem $b): int =>
            [array_search($a->section, NavItem::SECTIONS, true), $a->order, $a->key]
            <=> [array_search($b->section, NavItem::SECTIONS, true), $b->order, $b->key]);

        return $this->navigation = $items;
    }

    /**
     * Slugs des bundles actifs qui déclarent au moins une entrée de navigation : pour eux, le Core n'affiche plus ses
     * entrées de repli (bundles d'avant le contrat 1.1, qui ne déclarent rien).
     *
     * @return list<string>
     */
    public function bundlesDeclaringNavigation(): array
    {
        $slugs = [];
        foreach ($this->bundles->getBundles() as $bundle) {
            if ($this->contributionsOf($bundle, 'navigation', NavItem::class) !== []) {
                $slugs[] = $bundle->getName();
            }
        }
        return $slugs;
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return list<T>
     */
    private function collect(string $point, string $type): array
    {
        $all = [];
        foreach ($this->bundles->getBundles() as $bundle) {
            array_push($all, ...$this->contributionsOf($bundle, $point, $type));
        }
        return $all;
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return list<T>
     */
    private function contributionsOf(Bundle $bundle, string $point, string $type): array
    {
        try {
            $values = $bundle->{$point}();
        } catch (\Throwable $e) {
            Log::warning('bundle_extension_failed', ['bundle' => $bundle->getName(), 'point' => $point, 'error' => $e->getMessage()]);
            return [];
        }
        if (!is_array($values)) {
            Log::warning('bundle_extension_invalid', ['bundle' => $bundle->getName(), 'point' => $point]);
            return [];
        }

        $valid = [];
        foreach ($values as $value) {
            if ($value instanceof $type) {
                $valid[] = $value;
            } else {
                Log::warning('bundle_extension_invalid', ['bundle' => $bundle->getName(), 'point' => $point]);
            }
        }
        return $valid;
    }
}
