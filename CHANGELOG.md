# Changelog

All notable changes to **Wildcrest Lodges & Camps** are documented in this file.

## [1.0.0] - 2026-11-11

### Added
- **Core & Architecture**: Front controller in `public/index.php`, `Container` DI container, `nikic/fast-route` wrapper `Router` with route groups & middleware pipeline, `.env`-driven `Config` (`vlucas/phpdotenv`), `Validator`, `ModuleRegistry`, and central exception/error/shutdown handling with HTML + JSON negotiation.
- **RBAC & Staff Departments**: Hierarchical `Role` enum (`0..6`) and `Department` enum (`general-admin`, `sales`, `care`, `supervisor`, `manager`, `senior-manager`, `director`, `developer`), explicit permission map (`config/permissions.php`) with Level-1 cross-department read visibility and strict write isolation, declarative `NavigationRegistry` (`config/navigation.php`), and staff dashboards per role/department.
- **Database Schema & Deterministic Seeds**: Idempotent `database/schema.sql` (`utf8mb4`, 9 normalized tables including `bookings` overlap indexing and `audit_log`) and deterministic `database/seed.sql` (7 roles, 8 departments, 9 `user0`..`user8` dev accounts with Argon2id hashes, 8 properties, sample bookings, and sample staff tasks).
- **Security Controls**: `[SECURITY:PASSWORD]` Argon2id + rehash upgrade + timing mitigation; `[SECURITY:SESSION]` strict mode, timeouts, fixation regeneration, cookie teardown; `[SECURITY:BRUTE-FORCE]` DB-backed username/IP throttling + exponential backoff; `[SECURITY:CSRF]` `@csrf` synchronizer tokens + header validation; `[SECURITY:XSS]` BladeOne `{{ }}` escaping, CSP/headers middleware, and `textContent` DOM helpers; `[SECURITY:ERRORS]` generic public pages (`403`, `404`, `419`, `429`, `500`) + Monolog structured logs; non-web `storage/departments/{slug}/` upload storage with `finfo` MIME/extension verification.
- **Public & Customer Portal**: SEO-optimized home, filterable catalog (`/stays`), lodge detail with JSON-LD schema and availability checker, customer registration/login, atomic transactional reservation booking (`FOR UPDATE` + unique `idempotency_key`), and customer booking history dashboard.
- **Testing & Quality Tooling**: PHPUnit unit/integration test suites (`tests/Unit/`, `tests/Integration/`), `phpcs.xml` (PSR-12), `.eslintrc.json` (Airbnb Base), local Bootstrap 5 asset bundling, custom SVG illustrations, and full architectural/security documentation (`docs/`).
