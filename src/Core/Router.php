<?php

declare(strict_types=1);

namespace kintai\Core;

use kintai\Core\Exceptions\MethodNotAllowedException;
use kintai\Core\Exceptions\NotFoundException;

final class Router
{
    /** @var Route[] */
    private array $routes = [];

    /** @var array<string, Route> Named routes */
    private array $named = [];

    private string $groupPrefix = '';

    /** @var string[] */
    private array $groupMiddleware = [];

    public function get(string $pattern, array $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->addRoute('GET', $pattern, $handler, $middleware, $name);
    }

    public function post(string $pattern, array $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->addRoute('POST', $pattern, $handler, $middleware, $name);
    }

    public function put(string $pattern, array $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->addRoute('PUT', $pattern, $handler, $middleware, $name);
    }

    public function patch(string $pattern, array $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->addRoute('PATCH', $pattern, $handler, $middleware, $name);
    }

    public function delete(string $pattern, array $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->addRoute('DELETE', $pattern, $handler, $middleware, $name);
    }

    /**
     * @param string $prefix URL prefix for the group
     * @param callable(Router): void $callback
     * @param string[] $middleware Middleware applied to all routes in the group
     */
    public function group(string $prefix, callable $callback, array $middleware = []): self
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix = $previousPrefix . $prefix;
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;

        return $this;
    }

    /**
     * Resolve a request to a matched Route + extracted params.
     *
     * @return array{Route, array<string, string>}
     * @throws NotFoundException|MethodNotAllowedException
     */
    public function dispatch(string $method, string $uri): array
    {
        $method = strtoupper($method);
        // Normalize: ensure leading slash, strip trailing
        $uri = '/' . trim($uri, '/');

        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route->regex, $uri, $matches)) {
                if ($route->method !== $method) {
                    $allowedMethods[] = $route->method;
                    continue;
                }

                $params = [];
                foreach ($route->paramNames as $i => $name) {
                    $params[$name] = $matches[$i + 1];
                }

                return [$route, $params];
            }
        }

        if ($allowedMethods !== []) {
            throw new MethodNotAllowedException(array_unique($allowedMethods));
        }

        throw new NotFoundException("No route matches [{$method} {$uri}].");
    }

    /**
     * Generate a URL for a named route.
     */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->named[$name])) {
            throw new \InvalidArgumentException("Route [{$name}] not defined.");
        }

        $pattern = $this->named[$name]->pattern;

        foreach ($params as $key => $value) {
            $pattern = str_replace("{{$key}}", (string) $value, $pattern);
        }

        return $pattern;
    }

    /**
     * @return Route[]
     */
    public function routes(): array
    {
        return $this->routes;
    }

    private function addRoute(string $method, string $pattern, array $handler, array $middleware, ?string $name): self
    {
        $fullPattern = $this->groupPrefix . $pattern;
        $fullMiddleware = array_merge($this->groupMiddleware, $middleware);

        // Compile pattern to regex
        $paramNames = [];
        $regex = preg_replace_callback('/\{(\w+)\}/', function ($m) use (&$paramNames) {
            $paramNames[] = $m[1];
            return '([^/]+)';
        }, $fullPattern);

        $regex = '#^' . $regex . '$#';

        $route = new Route(
            method: strtoupper($method),
            pattern: $fullPattern,
            regex: $regex,
            handler: $handler,
            paramNames: $paramNames,
            middleware: $fullMiddleware,
            name: $name,
        );

        $this->routes[] = $route;

        if ($name !== null) {
            $this->named[$name] = $route;
        }

        return $this;
    }
}
