<?php

declare(strict_types=1);

namespace kintai\Bundles\StorePhoto;

use kintai\Core\Bundle;
use kintai\Core\Repositories\StorePhotoRepositoryInterface;
use kintai\Core\Repositories\DatabaseStorePhotoRepository;

final class StorePhotoBundle extends Bundle
{
    public function getName(): string
    {
        return 'store-photos';
    }

    public function getLabel(): string
    {
        return __('bundle_store_photos');
    }

    public function getDescription(): string
    {
        return __('bundle_store_photos_desc');
    }

    public function register(): void
    {
        $this->registerServices();
        $this->loadViewsFrom($this->getPath() . '/Views', 'store-photos');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
    }

    private function registerServices(): void
    {
        $container = $this->app->container();

        $container->singleton(
            StorePhotoRepositoryInterface::class,
            fn() => new DatabaseStorePhotoRepository()
        );
    }
}
