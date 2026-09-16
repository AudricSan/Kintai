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
}
