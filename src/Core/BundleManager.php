<?php

declare(strict_types=1);

namespace kintai\Core;

final class BundleManager
{
    private Application $app;
    /** @var Bundle[] */
    private array $bundles = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function registerBundle(string $bundleClass): void
    {
        if (isset($this->bundles[$bundleClass])) {
            return;
        }

        $bundle = new $bundleClass($this->app);
        $bundle->register();
        $this->bundles[$bundleClass] = $bundle;
    }

    public function boot(): void
    {
        foreach ($this->bundles as $bundle) {
            $bundle->boot();
        }
    }

    /**
     * @return Bundle[]
     */
    public function getBundles(): array
    {
        return $this->bundles;
    }

    /**
     * Un bundle est réellement actif (routes chargées, services enregistrés) s'il a
     * été à la fois découvert sur le disque ET activé via FeatureManager — voir
     * BundleServiceProvider::register(). Un slug activé par FeatureManager mais
     * absent du disque (bundle désinstallé, ou bundle pilote pas encore installé
     * depuis /admin/bundles/market) n'est PAS actif : contrairement à
     * FeatureManager::isEnabled(), qui ne reflète que le réglage stocké, ceci
     * reflète l'état réel après boot. À utiliser partout où du code suppose
     * qu'une route/vue du bundle existe réellement (ex. la modale de feedback,
     * incluse inconditionnellement par le layout).
     */
    public function isActive(string $slug): bool
    {
        foreach ($this->bundles as $bundle) {
            if ($bundle->getName() === $slug) {
                return true;
            }
        }
        return false;
    }
}
