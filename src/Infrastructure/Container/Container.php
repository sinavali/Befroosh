<?php

declare(strict_types=1);

namespace App\Infrastructure\Container;

use ReflectionClass;
use ReflectionParameter;
use RuntimeException;

/**
 * Lightweight, zero-dependency Dependency Injection Container with reflection auto-wiring.
 */
final class Container
{
    private static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setInstance(?self $container): void
    {
        self::$instance = $container;
    }

    public function bind(string $abstract, callable|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        unset($this->instances[$abstract]);
    }

    public function singleton(string $abstract, callable|string|object $concrete): void
    {
        if (is_object($concrete) && !($concrete instanceof \Closure)) {
            $this->instances[$abstract] = $concrete;
            return;
        }

        $this->bindings[$abstract] = function (self $container) use ($concrete, $abstract) {
            if ($concrete instanceof \Closure) {
                return $concrete($container);
            }
            return $container->build((string)$concrete);
        };
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->bindings[$id]) || class_exists($id);
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            $concrete = $this->bindings[$id];
            $object = $concrete instanceof \Closure ? $concrete($this) : $this->build($concrete);

            // Cache if registered as singleton closure
            return $object;
        }

        return $this->build($id);
    }

    public function build(string $concrete): object
    {
        if (!class_exists($concrete)) {
            throw new RuntimeException("Target class [{$concrete}] does not exist.");
        }

        $reflector = new ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new RuntimeException("Target [{$concrete}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters, $concrete);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * @param array<ReflectionParameter> $parameters
     * @return array<mixed>
     */
    private function resolveDependencies(array $parameters, string $targetClass): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $dependencyClass = $type->getName();
                $dependencies[] = $this->get($dependencyClass);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            throw new RuntimeException(
                "Unresolvable dependency [{$parameter->getName()}] in class [{$targetClass}]."
            );
        }

        return $dependencies;
    }
}
