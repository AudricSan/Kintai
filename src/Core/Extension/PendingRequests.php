<?php

declare(strict_types=1);

namespace kintai\Core\Extension;

use kintai\Contract\Provider\PendingRequest;
use kintai\Contract\Provider\PendingRequestsProvider;
use kintai\Core\BundleManager;
use kintai\Core\Container;
use kintai\Core\Extension\Legacy\LegacyPendingRequests;
use kintai\Core\Services\WidgetGuard;

/**
 * Demandes en attente de toutes sortes, lues à travers des fournisseurs (étape 3 du contrat de bundle) :
 * - ceux déclarés par les bundles actifs (Bundle::pendingRequestsProviders(), contrat 1.9) ;
 * - en repli, ceux du Core pour timeoff et shift-swap tant qu'ils ne déclarent rien.
 *
 * Utilisé par la page « Demandes », le compteur du tableau de bord et les alertes. Chaque fournisseur est isolé : une
 * exception le retire (journalisée) sans casser la page.
 */
final class PendingRequests
{
    /** @var list<PendingRequestsProvider>|null */
    private ?array $providers = null;

    public function __construct(
        private readonly ?ExtensionRegistry $registry = null,
        private readonly ?BundleManager $bundles = null,
        private readonly ?Container $container = null,
    ) {}

    /** Instance de la requête courante, ou aucun fournisseur si le conteneur est indisponible. */
    public static function current(): self
    {
        try {
            $container = Container::getInstance();
            return new self(
                $container->has(ExtensionRegistry::class) ? $container->make(ExtensionRegistry::class) : null,
                $container->has(BundleManager::class) ? $container->make(BundleManager::class) : null,
                $container,
            );
        } catch (\Throwable) {
            return new self();
        }
    }

    /**
     * Fournisseurs : déclarés par les bundles actifs, puis historiques des bundles actifs qui ne déclarent rien ;
     * triés par ordre. Une clé déjà fournie n'est pas reprise.
     *
     * @return list<PendingRequestsProvider>
     */
    public function providers(): array
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        $declared = $this->registry?->pendingRequestsProvidersByBundle() ?? [];
        $all = array_merge(...array_values($declared) ?: [[]]);
        if ($this->container !== null) {
            foreach (LegacyPendingRequests::forBundles($this->container) as $slug => $legacy) {
                if ($this->bundles?->isActive($slug) && !isset($declared[$slug])) {
                    $all[] = $legacy;
                }
            }
        }

        $byKey = [];
        foreach ($all as $provider) {
            try {
                $byKey[$provider->key()] ??= $provider;
            } catch (\Throwable) {
            }
        }
        $providers = array_values($byKey);
        usort($providers, static fn(PendingRequestsProvider $a, PendingRequestsProvider $b): int => self::safe(fn() => $a->order(), 100) <=> self::safe(fn() => $b->order(), 100));
        return $this->providers = $providers;
    }

    /**
     * Sections visibles (permission et fonctionnalité) avec leurs demandes. Un fournisseur en échec est retiré.
     *
     * @param list<int>|null          $storeIds
     * @param callable(string): bool  $can
     * @param callable(?string): bool $feat
     * @return list<array{key: string, label: string, empty_label: string, manage_url: string, requests: list<PendingRequest>}>
     */
    public function sections(?array $storeIds, callable $can, callable $feat): array
    {
        $sections = [];
        foreach ($this->providers() as $provider) {
            $section = WidgetGuard::run('pending_requests', static function () use ($provider, $storeIds, $can, $feat): ?array {
                if (!$can($provider->permission()) || !$feat($provider->storeFeature())) {
                    return null;
                }
                $requests = array_values(array_filter($provider->pending($storeIds), static fn($r): bool => $r instanceof PendingRequest));
                return [
                    'key'         => $provider->key(),
                    'label'       => $provider->label(),
                    'empty_label' => $provider->emptyLabel(),
                    // Route absente (bundle en cours de chargement, page retirée…) : la section reste, sans lien.
                    'manage_url'  => self::safe(fn() => $provider->manageUrl(), ''),
                    'requests'    => $requests,
                ];
            }, null);
            if ($section !== null) {
                $sections[] = $section;
            }
        }
        return $sections;
    }

    /**
     * Nombre total de demandes en attente visibles.
     *
     * @param list<int>|null          $storeIds
     * @param callable(string): bool  $can
     * @param callable(?string): bool $feat
     */
    public function count(?array $storeIds, callable $can, callable $feat): int
    {
        return array_sum(array_map(static fn(array $s): int => count($s['requests']), $this->sections($storeIds, $can, $feat)));
    }

    /**
     * Demandes déposées avant $threshold (Y-m-d H:i:s), toutes sortes confondues, les plus anciennes en premier.
     *
     * @param list<int> $storeIds
     * @return list<array{type: string, label: string, id: int, store_id: int, created_at: string}>
     */
    public function olderThan(array $storeIds, string $threshold): array
    {
        $old = [];
        foreach ($this->sections($storeIds, static fn(): bool => true, static fn(): bool => true) as $section) {
            foreach ($section['requests'] as $request) {
                if ($request->createdAt !== '' && $request->createdAt <= $threshold) {
                    $old[] = ['type' => $section['key'], 'label' => $section['label'], 'id' => $request->id, 'store_id' => $request->storeId, 'created_at' => $request->createdAt];
                }
            }
        }
        usort($old, static fn(array $a, array $b): int => strcmp($a['created_at'], $b['created_at']));
        return $old;
    }

    /** L'utilisateur peut-il voir au moins une sorte de demande ? (le compteur du tableau de bord n'apparaît que dans ce cas) */
    public function hasVisible(callable $can, callable $feat): bool
    {
        foreach ($this->providers() as $provider) {
            if (self::safe(fn() => $can($provider->permission()) && $feat($provider->storeFeature()), false)) {
                return true;
            }
        }
        return false;
    }

    private static function safe(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
