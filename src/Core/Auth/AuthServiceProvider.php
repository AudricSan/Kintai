<?php

declare(strict_types=1);

namespace kintai\Core\Auth;

use kintai\Core\ServiceProvider;
use kintai\Core\Container;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;

final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(
            AuthService::class,
            fn(Container $c) => new AuthService(
                $c->make(UserRepositoryInterface::class),
                $c->make(StoreUserRepositoryInterface::class),
                $c->make(StoreRepositoryInterface::class),
            )
        );
    }
}
