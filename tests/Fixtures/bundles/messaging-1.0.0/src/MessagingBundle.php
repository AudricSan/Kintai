<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Messaging;

use kintai\Core\BundleContract\Bundle;
use kintai\Core\Repositories\MessageRepositoryInterface;
use kintai\Core\Repositories\DatabaseMessageRepository;

final class MessagingBundle extends Bundle
{
    public function getName(): string
    {
        return 'messaging';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getLabel(): string
    {
        return __('bundle_messaging');
    }

    public function getDescription(): string
    {
        return __('bundle_messaging_desc');
    }

    public function register(): void
    {
        $this->registerServices();
        $this->loadViewsFrom($this->getPath() . '/Views', 'messaging');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
    }

    private function registerServices(): void
    {
        $container = $this->app->container();

        $container->singleton(
            MessageRepositoryInterface::class,
            fn() => new DatabaseMessageRepository()
        );
    }
}
