<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * Explicit dependency-injection container.
 *
 * Modules register factories and singletons here during bootstrap so controllers,
 * services, and repositories remain decoupled and easy to test in isolation.
 */
final class Container
{
    /** @var array<string, callable(self): mixed> */
    private array $factories = [];

    /** @var array<string, bool> */
    private array $sharedFlags = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * Register a transient factory invoked on every `get()`.
     *
     * @param callable(self): mixed $factory
     */
    public function bind(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        $this->sharedFlags[$id] = false;
        unset($this->instances[$id]);
    }

    /**
     * Register a shared singleton factory cached for the request lifecycle.
     *
     * @param callable(self): mixed $factory
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        $this->sharedFlags[$id] = true;
        unset($this->instances[$id]);
    }

    /**
     * Register a pre-built object or value instance.
     */
    public function instance(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
        $this->sharedFlags[$id] = true;
    }

    /**
     * Check whether an identifier has been registered.
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances) || array_key_exists($id, $this->factories);
    }

    /**
     * Resolve a service or value from the container.
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!array_key_exists($id, $this->factories)) {
            throw new InvalidArgumentException(sprintf('Service "%s" is not registered in the container.', $id));
        }

        $resolved = ($this->factories[$id])($this);
        if (($this->sharedFlags[$id] ?? false) === true) {
            $this->instances[$id] = $resolved;
        }

        return $resolved;
    }
}
