<?php

declare(strict_types=1);

namespace kintai\Core;

use Closure;
use ReflectionClass;
use ReflectionParameter;
use RuntimeException;

final class Container
{
    private static ?Container $instance = null;

    /** @var array<string, \Closure> */
    private array $bindings = [];

    /** @var array<string, \Closure> */
    private array $singletonDefs = [];

    /** @var array<string, object> */
    private array $instances = [];

    public static function getInstance(): Container
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function bind(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    public function singleton(string $abstract, Closure $factory): void
    {
        $this->singletonDefs[$abstract] = $factory;
    }

    public function instance(string $abstract, object $object): void
    {
        $this->instances[$abstract] = $object;
    }

    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract])
            || isset($this->singletonDefs[$abstract])
            || isset($this->bindings[$abstract]);
    }

    /**
     * @template T
     * @param class-string<T>|string $abstract
     * @return T
     */
    public function make(string $abstract): mixed
    {
        // 1. Pre-built instance
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // 2. Singleton (resolve once, cache)
        if (isset($this->singletonDefs[$abstract])) {
            $this->instances[$abstract] = ($this->singletonDefs[$abstract])($this);
            unset($this->singletonDefs[$abstract]);
            return $this->instances[$abstract];
        }

        // 3. Factory binding (new instance each call)
        if (isset($this->bindings[$abstract])) {
            return ($this->bindings[$abstract])($this);
        }

        // 4. Auto-resolve via reflection
        return $this->resolve($abstract);
    }

    private function resolve(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Cannot resolve [{$class}]: class not found.");
        }

        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Cannot resolve [{$class}]: not instantiable.");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $params = array_map(
            fn(ReflectionParameter $p) => $this->resolveParameter($p, $class),
            $constructor->getParameters()
        );

        return $reflection->newInstanceArgs($params);
    }

    private function resolveParameter(ReflectionParameter $param, string $forClass): mixed
    {
        $type = $param->getType();

        if ($type !== null && !$type->isBuiltin()) {
            return $this->make($type->getName());
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        throw new RuntimeException(
            "Cannot resolve parameter [{$param->getName()}] for [{$forClass}]."
        );
    }
}
