<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Contract implemented by every self-contained domain module under `src/Modules/`.
 *
 * Modules declare their service bindings and HTTP routes here so adding new
 * business capabilities requires zero edits to core routing or bootstrapping.
 */
interface ModuleInterface
{
    public function name(): string;

    public function registerServices(Container $container): void;

    public function registerRoutes(Router $router): void;
}
