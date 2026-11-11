<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Central registry that boots domain modules in deterministic order.
 *
 * // [FUTURE:SALES-MODULE] Register SalesModule::class here for full CRM/quoting.
 * // [FUTURE:HR-MODULE] Register HrModule::class here for staff scheduling/onboarding.
 * // [FUTURE:MANAGER-REPORTING] Register ReportingModule::class here for BI exports.
 */
final class ModuleRegistry
{
    /** @var list<ModuleInterface> */
    private array $modules = [];

    public function register(ModuleInterface $module): void
    {
        $this->modules[] = $module;
    }

    public function bootServices(Container $container): void
    {
        foreach ($this->modules as $module) {
            $module->registerServices($container);
        }
    }

    public function bootRoutes(Router $router): void
    {
        foreach ($this->modules as $module) {
            $module->registerRoutes($router);
        }
    }

    /**
     * @return list<string>
     */
    public function registeredModuleNames(): array
    {
        return array_map(
            static fn (ModuleInterface $module): string => $module->name(),
            $this->modules
        );
    }
}
