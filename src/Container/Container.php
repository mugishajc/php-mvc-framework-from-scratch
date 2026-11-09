<?php

declare(strict_types=1);

namespace Framework\Container;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * A small dependency injection container with constructor autowiring.
 *
 * Bindings map an id (usually a class or interface name) to a factory
 * closure or a concrete class name. Classes that are not bound explicitly
 * are built by reflecting on their constructor.
 */
final class Container
{
    /** @var array<string, array{concrete: Closure|string, shared: bool}> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, true> */
    private array $resolving = [];

    public function bind(string $id, Closure|string|null $concrete = null, bool $shared = false): void
    {
        unset($this->instances[$id]);
        $this->bindings[$id] = ['concrete' => $concrete ?? $id, 'shared' => $shared];
    }

    public function singleton(string $id, Closure|string|null $concrete = null): void
    {
        $this->bind($id, $concrete, true);
    }

    public function instance(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances)
            || isset($this->bindings[$id])
            || class_exists($id);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->resolving[$id])) {
            $chain = implode(' -> ', array_keys($this->resolving)) . ' -> ' . $id;
            throw new ContainerException("Circular dependency detected: $chain");
        }

        $this->resolving[$id] = true;

        try {
            $binding = $this->bindings[$id] ?? null;
            $concrete = $binding['concrete'] ?? $id;

            if ($concrete instanceof Closure) {
                $object = $concrete($this);
            } elseif ($concrete !== $id) {
                $object = $this->get($concrete);
            } else {
                $object = $this->build($concrete);
            }
        } finally {
            unset($this->resolving[$id]);
        }

        if ($binding !== null && $binding['shared']) {
            $this->instances[$id] = $object;
        }

        return $object;
    }

    /**
     * Instantiates a class, resolving constructor arguments from the container.
     *
     * @param array<string, mixed> $parameters values keyed by parameter name
     */
    public function build(string $class, array $parameters = []): object
    {
        if (!class_exists($class)) {
            throw new ContainerException("Cannot resolve [$class]: class does not exist and nothing is bound.");
        }

        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new ContainerException("Cannot instantiate [$class]; bind a concrete implementation to it.");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        return $reflection->newInstanceArgs($this->resolveArguments($constructor, $parameters));
    }

    /**
     * Calls a closure, a [class, method] pair or an invokable class,
     * injecting arguments by name first and then by type.
     *
     * @param Closure|array{0: class-string|object, 1: string}|string $callable
     * @param array<string, mixed> $parameters
     */
    public function call(Closure|array|string $callable, array $parameters = []): mixed
    {
        if (is_array($callable)) {
            [$target, $method] = $callable;
            $object = is_string($target) ? $this->get($target) : $target;
            $reflection = new ReflectionMethod($object, $method);

            return $reflection->invokeArgs($object, $this->resolveArguments($reflection, $parameters));
        }

        if (is_string($callable) && class_exists($callable)) {
            $object = $this->get($callable);
            $reflection = new ReflectionMethod($object, '__invoke');

            return $reflection->invokeArgs($object, $this->resolveArguments($reflection, $parameters));
        }

        try {
            $reflection = new ReflectionFunction($callable);
        } catch (ReflectionException $e) {
            throw new ContainerException("Value is not callable: " . (is_string($callable) ? $callable : 'closure'), 0, $e);
        }

        return $reflection->invokeArgs($this->resolveArguments($reflection, $parameters));
    }

    /**
     * @param array<string, mixed> $parameters
     * @return list<mixed>
     */
    private function resolveArguments(ReflectionFunctionAbstract $function, array $parameters): array
    {
        $arguments = [];

        foreach ($function->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

            if (array_key_exists($name, $parameters)) {
                $arguments[] = $this->coerce($parameters[$name], $type);
                continue;
            }

            if ($typeName !== null && !$type->isBuiltin()) {
                if (array_key_exists($typeName, $parameters)) {
                    $arguments[] = $parameters[$typeName];
                    continue;
                }

                if ($this->has($typeName) || !$parameter->isDefaultValueAvailable()) {
                    $arguments[] = $this->get($typeName);
                    continue;
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            if ($type !== null && $type->allowsNull()) {
                $arguments[] = null;
                continue;
            }

            $owner = $function instanceof ReflectionMethod
                ? $function->getDeclaringClass()->getName() . '::' . $function->getName()
                : $function->getName();

            throw new ContainerException("Unable to resolve parameter \$$name of $owner.");
        }

        return $arguments;
    }

    /**
     * Route parameters always arrive as strings; cast them for scalar type hints.
     */
    private function coerce(mixed $value, ?\ReflectionType $type): mixed
    {
        if (!is_string($value) || !$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $value,
            default => $value,
        };
    }
}
