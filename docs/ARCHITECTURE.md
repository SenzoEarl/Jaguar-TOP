# Architecture Overview

## 1. Architectural Style: Modular Monolith with Layered Separation

**Wildcrest Lodges & Camps** is structured as a single, self-contained **modular monolith** following strict separation of concerns (`Controller -> Service -> Repository -> PDO`) and dependency injection via `App\Core\Container`.

Every HTTP request enters through the sole web-exposed front controller [`public/index.php`](../public/index.php):

```text
HTTP Request
   -> public/index.php
   -> App\Core\Application (loads Config, Error/Shutdown handlers, Session, Logging)
   -> App\Core\Router (nikic/fast-route + Route Middleware Pipeline)
   -> Controller (validates HTTP boundary, delegates only)
   -> Service (transactions, RBAC checks via AccessControl, domain rules, AuditLogger)
   -> Repository (prepared PDO statements only via Infrastructure\Database\Connection)
   -> ViewFactory (BladeOne escaping) OR Response::json(...)
   -> Infrastructure\Security\SecurityHeaders (CSP, XFO, nosniff, Referrer-Policy, Permissions-Policy)
```

---

## 2. Core & Module Boundaries ("Build the Seams, Not the Features")

| Layer | Directory | Responsibility |
|---|---|---|
| **Front Controller & Public Assets** | `public/` | Sole document root (`index.php`, `.htaccess`, `robots.txt`, compiled CSS/JS/SVG, vendored Bootstrap). Zero application secrets or storage live here. |
| **Core Runtime** | `src/Core/` | `Application`, `Config`, `Container`, `Router`, `Request`, `Response`, `Session`, `Csrf`, `Validator`, `ModuleInterface`, `ModuleRegistry`, and cross-module contracts (`AccessControlInterface`, `CurrentUserProviderInterface`, `AuthenticatedUserInterface`). |
| **Route Middleware** | `src/Middleware/` | Declarative pipeline guards (`RequireAuthMiddleware`, `RequireGuestMiddleware`, `RequireCsrfMiddleware`, `RequireRoleMiddleware`, `RequireMinLevelMiddleware`, `RequireDepartmentMiddleware`). |
| **Exceptions** | `src/Exceptions/` | Typed hierarchy descending from `AppException`, separating internal diagnostics (`$message`, `$context`) from safe public HTTP messages and status codes. |
| **Infrastructure Adapters** | `src/Infrastructure/` | MariaDB PDO wrapper (`Connection`), rotating file logger (`LoggerFactory`), DB+Monolog forensic trail (`AuditLogger`), HTTP headers (`SecurityHeaders`), non-web uploads (`DepartmentFileStorage`), and BladeOne bridge (`ViewFactory`). |
| **Domain Modules** | `src/Modules/*` | Self-contained feature slices (`Auth`, `Catalog`, `Booking`, `Tasks`, `Files`, `Staff`), each implementing `ModuleInterface` with local `Controllers/`, `Services/`, `Repositories/`, `Models/`, and `routes.php`. |
| **Support & Registries** | `src/Support/` | PHP 8.2+ backed enums (`Role`, `Department`, `ProductType`, `BookingStatus`, `TaskStatus`, `TaskPriority`), declarative sidebar registry (`NavigationRegistry`), and stateless utilities (`helpers.php`). |
| **Views & Storage** | `resources/views/`, `storage/` | BladeOne `.blade.php` templates, file-backed sessions, Monolog logs, Blade cache, and per-department document vaults. |

### Cross-Module Decoupling Seams

- Controllers NEVER import PDO or touch repositories directly.
- Cross-cutting modules depend on interfaces (`AccessControlInterface`, `CurrentUserProviderInterface`) rather than concrete implementations.
- Extension seams for anticipated growth (`SalesModule`, `HrModule`, `ReportingModule`, payments, async notifications, caching, external search, multi-tenancy) are documented with searchable `// [FUTURE:*]` markers in code and guides (`docs/ADDING_A_MODULE.md`, `docs/ADDING_A_DEPARTMENT.md`).

---

## 3. Dependency Justifications

Only battle-tested, actively maintained packages are used where hand-rolling would invite security risk or brittle complexity:

### PHP Production (`composer.json`)

| Package | Version | Justification |
|---|---|---|
| `vlucas/phpdotenv` | `^5.6` | Battle-tested 12-factor `.env` parser supporting immutable env loading, quoted values, and safe multi-environment config without bespoke `.env` regex parsing bugs. |
| `nikic/fast-route` | `^1.3` | Industry-standard, lightweight compiled regex/radix dispatcher with native parameter validation (`{id:\d+}`). Keeps core routing ~190 LOC without pulling a heavy full-stack framework. |
| `eftec/bladeone` | `^4.10` | Standalone compiled Blade-compatible templating engine (zero Illuminate container dependencies) enforcing `{{ }}` escaping by default while compiling to `storage/cache/blade/`. |
| `monolog/monolog` | `^3.5` | PSR-3 standard logging library supporting `RotatingFileHandler`, structured context metadata (`request_id`, `user_id`, `path`), and `.env`-driven severity filtering. |

### PHP Development (`composer.json`)

| Package | Version | Justification |
|---|---|---|
| `phpunit/phpunit` | `^10.5` | Standard automated test runner for unit tests and in-memory SQLite PDO integration tests. |
| `squizlabs/php_codesniffer` | `^3.9` | Automated PSR-12 linter (`phpcs.xml`) enforcing strict code formatting across `src/`, `config/`, `public/index.php`, and `tests/`. |

### Frontend Runtime & Tooling (`package.json`)

| Package | Version | Justification |
|---|---|---|
| `bootstrap` | `^5.3.3` | Responsive mobile-first grid, form validation feedback classes, and modal/alert/sidebar styling. Copied locally into `public/assets/vendor/bootstrap/` via `npm run copy:bootstrap` so strict `CSP` (`'self'`) never relies on external CDNs. |
| `eslint` + `eslint-config-airbnb-base` + `eslint-plugin-import` | `^8.57.0` / `^15.0.0` / `^2.29.1` | Enforces Airbnb ES2022+ style, clean ES module imports, `const`/`let` discipline, and single-class-per-file clarity across `public/assets/js/`. |

---

## 4. Frontend JavaScript Architecture

Zero jQuery, zero globals, zero inline `<script>` tags:

- ES6 modules load once from `public/assets/js/main.js` via `<script type="module" ... defer>`.
- **`core/DomHelper.js`**: Safe element creation and text insertion strictly via `textContent` (never `innerHTML` for user or server strings).
- **`core/ApiClient.js`**: Single centralized `fetch()` wrapper reading CSRF meta tags (`csrf-token`, `csrf-header`), sending JSON/FormData requests, parsing error responses uniformly, and surfacing safe user feedback.
- **`core/FormValidator.js`**: Lightweight client-side preflight validation mirroring Bootstrap `.is-invalid` feedback prior to authoritative server validation.
- **`modules/*.js`**: One ES class per controller (`CatalogFilterController`, `BookingFormController`, `TaskManagerController`, `FileManagerController`), initialized cleanly on `DOMContentLoaded` only when its matching `data-*` root element exists in the active DOM.
