<?php

declare(strict_types=1);

namespace kintai\Bundles\Messaging;

use kintai\Core\Bundle;
use kintai\Core\Repositories\MessageRepositoryInterface;
use kintai\Core\Repositories\DatabaseMessageRepository;
use kintai\Core\Container;

final class MessagingBundle extends Bundle
{
    public function getName(): string
    {
        return 'messaging';
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
