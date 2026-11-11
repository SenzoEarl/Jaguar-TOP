# Adding a New Domain Module

Wildcrest Lodges & Camps uses a zero-magic module registration pattern designed around `App\Core\ModuleInterface` and `App\Core\ModuleRegistry` so new business capabilities (such as CRM/Sales, HR scheduling, or Executive Reporting) slot into the application without editing core routing, authentication, CSRF, or error-handling code.

Look for `// [FUTURE:SALES-MODULE]`, `// [FUTURE:HR-MODULE]`, and `// [FUTURE:MANAGER-REPORTING]` comments in the codebase to see pre-built extension points.

---

## Step-by-Step Checklist

### 1. Create the Module Folder Structure

Create a self-contained PSR-4 namespace under `src/Modules/<ModuleName>/`:

```text
src/Modules/Reporting/
  ReportingModule.php
  routes.php
  Controllers/
    ReportingController.php
  Models/
    ReportSnapshot.php
  Repositories/
    ReportingRepository.php
  Services/
    ReportingService.php
```

Every PHP file must start with `<?php`, optional file docblock, `declare(strict_types=1);`, strict parameter/return types, and follow PSR-12.

### 2. Implement `App\Core\ModuleInterface`

Create `src/Modules/Reporting/ReportingModule.php`:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Reporting;

use App\Core\AccessControlInterface;
use App\Core\Container;
use App\Core\ModuleInterface;
use App\Core\Router;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\View\ViewFactory;

final class ReportingModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Reporting';
    }

    public function registerServices(Container $container): void
    {
        $container->singleton(
            ReportingRepository::class,
            static fn (Container $c): ReportingRepository => new ReportingRepository(
                $c->get(Connection::class)
            )
        );

        $container->singleton(
            ReportingService::class,
            static fn (Container $c): ReportingService => new ReportingService(
                $c->get(ReportingRepository::class),
                $c->get(AccessControlInterface::class)
            )
        );

        $container->singleton(
            ReportingController::class,
            static fn (Container $c): ReportingController => new ReportingController(
                $c->get(ViewFactory::class),
                $c->get(ReportingService::class)
            )
        );
    }

    public function registerRoutes(Router $router): void
    {
        $register = require __DIR__ . '/routes.php';
        $register($router);
    }
}
```

### 3. Declare Protected Routes in `routes.php`

Always use core middleware (`RequireAuthMiddleware`, `RequireCsrfMiddleware`, `RequireMinLevelMiddleware`, `RequireDepartmentMiddleware`, `RequireRoleMiddleware`) rather than ad-hoc permission checks inside controllers:

```php
<?php

declare(strict_types=1);

use App\Core\Router;
use App\Middleware\RequireAuthMiddleware;
use App\Middleware\RequireMinLevelMiddleware;
use App\Modules\Reporting\Controllers\ReportingController;
use App\Support\Enums\Role;

return static function (Router $router): void {
    $router->group('/staff/reports', [
        RequireAuthMiddleware::class,
        [RequireMinLevelMiddleware::class, ['minRole' => Role::Manager->slug()]],
    ], static function (Router $group): void {
        $group->get('/', [ReportingController::class, 'index']);
    });
};
```

### 4. Register the Module in `public/index.php`

Append `new ReportingModule()` to the module array passed into `new Application($rootPath, [...])`:

```php
$application = new Application($rootPath, [
    new AuthModule(),
    new CatalogModule(),
    new BookingModule(),
    new TasksModule(),
    new FilesModule(),
    new StaffModule(),
    new ReportingModule(),
]);
```

### 5. Optional: Add Capabilities & Navigation Links

1. Add any new capability keys in `config/permissions.php` under `'capabilities'`.
2. Add sidebar entries in `config/navigation.php` guarded by `'capability'` or `'department'`.
3. Add Blade templates under `resources/views/<module>/` extending `layouts.staff` or `layouts.public`, using `{{ }}` escaping and `@csrf` on every form.
4. If frontend interactivity is needed, add an ES6 controller class in `public/assets/js/modules/` and mount it in `public/assets/js/main.js`.

### 6. Verify Before Merging

```bash
composer check
npm run lint:js
```
