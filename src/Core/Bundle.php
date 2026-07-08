<?php

declare(strict_types=1);

namespace kintai\Core;

abstract class Bundle
{
    protected Application $app;
    protected string $path;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->path = $this->resolvePath();
    }

    /**
     * Get the bundle's unique identifier.
     */
    abstract public function getName(): string;

    /**
     * Register services, routes, or views.
     */
    abstract public function register(): void;

    /**
     * Boot the bundle after all are registered.
     */
    public function boot(): void
    {
        // Optional
    }

    /**
     * Get the base directory path of the bundle.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    protected function resolvePath(): string
    {
        $reflector = new \ReflectionClass(static::class);
        return dirname($reflector->getFileName());
    }

    /**
     * Helper to register routes from a file.
     */
    protected function loadRoutesFrom(string $path): void
    {
        $router = $this->app->router();
        $container = $this->app->container();
        require $path;
    }

    /**
     * Helper to register views with a namespace.
     */
    protected function loadViewsFrom(string $path, string $namespace): void
    {
        $viewRenderer = $this->app->container()->make(\kintai\UI\ViewRenderer::class);
        $viewRenderer->addNamespace($namespace, $path);
    }
}
