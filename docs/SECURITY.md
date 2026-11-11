# Security Architecture & Verification Map

All critical security controls in **Wildcrest Lodges & Camps** are visibly tagged with searchable `[SECURITY:*]` banner comments for rapid security audits and code reviews.

> [!CAUTION]
> **CRITICAL PRE-PRODUCTION WARNING — DEV/TEST SEED ACCOUNTS (`user0` .. `user8`)**
>
> `database/seed.sql` inserts **9 development accounts** (`user0` through `user8`) with predictable credentials (`userN@user.com`).
> **You MUST delete, disable, or rotate all passwords for these accounts before deploying to staging, shared QA, or production environments.** Never import `database/seed.sql` unmodified on an Internet-facing host.

---

## 1. `[SECURITY:*]` Control Matrix

| Tag | Control Summary | Primary Implementation Locations |
|---|---|---|
| `[SECURITY:PASSWORD]` | Argon2id (`PASSWORD_ARGON2ID`, fallback `PASSWORD_BCRYPT` configured via `.env`), constant-time `password_verify()` against a dummy Argon2id hash when a username is not found, automatic `password_needs_rehash()` upgrades on login, and generic login failure messaging (`Invalid username or password.`). | `src/Modules/Auth/Services/AuthService.php`, `config/security.php`, `.env.example` |
| `[SECURITY:SESSION]` | `session.use_strict_mode=1`, `use_only_cookies=1`, `use_trans_sid=0`, `HttpOnly`, `.env`-controlled `Secure` & `SameSite`, non-web `SESSION_SAVE_PATH` (`storage/sessions/`), idle + absolute timeouts enforced every request, `session_regenerate_id(true)` on login, and full server+cookie destruction on logout. | `src/Core/Session.php`, `src/Modules/Auth/Services/AuthService.php`, `config/session.php`, `.env.example` |
| `[SECURITY:BRUTE-FORCE]` | DB-backed `login_attempts` sliding window (`AUTH_LOCKOUT_SECONDS`, `AUTH_MAX_ATTEMPTS_PER_USER`, `AUTH_MAX_ATTEMPTS_PER_IP`), exponential backoff (`AUTH_BACKOFF_BASE_SECONDS`..`AUTH_BACKOFF_MAX_SECONDS`), zero username enumeration, custom HTTP `429` + `Retry-After` response via `RateLimitException`, logged + audited lockout events. | `database/schema.sql`, `src/Modules/Auth/Repositories/LoginAttemptRepository.php`, `src/Modules/Auth/Services/RateLimiterService.php`, `src/Exceptions/RateLimitException.php`, `resources/views/errors/429.blade.php`, `config/security.php`, `.env.example` |
| `[SECURITY:CSRF]` | Per-session CSPRNG (`random_bytes(32)`) token verified via `hash_equals()` in `RequireCsrfMiddleware` on POST/mutating requests (`_csrf_token` field or `X-CSRF-Token` header), `@csrf` Blade directive, meta tags in `layouts/{public,staff}.blade.php` for `ApiClient.js`, rotated on login transitions, custom `419` page on mismatch. | `src/Core/Csrf.php`, `src/Middleware/RequireCsrfMiddleware.php`, `src/Exceptions/CsrfTokenException.php`, `src/Infrastructure/View/ViewFactory.php`, `src/Modules/Auth/Services/AuthService.php`, `resources/views/layouts/public.blade.php`, `resources/views/layouts/staff.blade.php`, `public/assets/js/core/ApiClient.js`, `config/security.php`, `.env.example` |
| `[SECURITY:XSS]` | BladeOne `{{ }}` HTML entity escaping by default (plus canonical `e()` helper), `{!! !!}` banned except **one** documented JSON-LD exemption in `public/catalog-detail.blade.php` using `JSON_HEX_*` escaping; frontend JS constructs DOM nodes via `DomHelper` / `textContent` (never `innerHTML` for user/API data); central `SecurityHeaders` enforces `Content-Security-Policy` (`'self'`, `frame-ancestors 'none'`, `object-src 'none'`), `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, and `X-Robots-Tag: noindex, nofollow` on `/staff/*` & `/account/*`. | `src/Infrastructure/View/ViewFactory.php`, `src/Support/helpers.php`, `src/Infrastructure/Security/SecurityHeaders.php`, `resources/views/layouts/public.blade.php`, `resources/views/layouts/staff.blade.php`, `resources/views/public/catalog-detail.blade.php`, `public/assets/js/core/DomHelper.js`, `public/assets/js/modules/TaskManagerController.js`, `public/assets/js/modules/FileManagerController.js`, `config/security.php`, `.env.example` |
| `[SECURITY:ERRORS]` | `Application` registers global `set_exception_handler`, `set_error_handler` (warnings/notices promoted to `ErrorException`), and `register_shutdown_function`. Separates internal diagnostic message/context (written to Monolog rotating logs with `request_id`, `user_id`, `path`, `ip`) from generic public HTTP responses (`403`, `404`, `419`, `429`, `500` Blade templates or safe JSON payloads). `display_errors=0` outside debug/local CLI checks; `DatabaseException` guarantees raw `PDOException` SQL/paths never leak to clients. | `src/Core/Application.php`, `src/Exceptions/AppException.php`, `src/Exceptions/DatabaseException.php`, `src/Infrastructure/Logging/LoggerFactory.php`, `resources/views/errors/403.blade.php`, `resources/views/errors/429.blade.php`, `resources/views/errors/500.blade.php`, `config/security.php`, `.env.example` |

---

## 2. Authorization & Role-Based Access Control (RBAC)

- **Deny by Default**: Every permission is evaluated centrally by [`App\Modules\Auth\Services\AccessControl`](../src/Modules/Auth/Services/AccessControl.php) against [`config/permissions.php`](../config/permissions.php). Controllers contain **zero** ad-hoc `$level > ...` conditionals.
- **Middleware Guards**: Routes enforce access declaratively via `RequireAuthMiddleware`, `RequireRoleMiddleware`, `RequireMinLevelMiddleware`, and `RequireDepartmentMiddleware`. Unauthenticated users are redirected to `/account/login`; authenticated users lacking permission receive HTTP `403` (`AuthorizationException`).
- **Level-1 Cross-Department Collaboration**: Level-1 staff (`general-admin`, `sales`, `care`) share explicit **read-only** access across each other's Level-1 dashboards, task lists, and department file listings while keeping **write** mutations (`POST`, `PATCH`, uploads) isolated strictly to their own department.

---

## 3. Input Validation, SQL Injection Defense & Audit Logging

- **Central Input Validation**: All untrusted form, query, and JSON payloads pass through [`App\Core\Validator`](../src/Core/Validator.php) with allowlisted rules (`required`, `string`, `email`, `integer`, `min`, `max`, `in`, `date`, `after_field`, `after_or_equal_today`, `username`, `slug`, `same`). Invalid redirects sanitize `Referer` paths (`Application::safeBackTarget()`) to block open redirects.
- **Prepared Statements Everywhere**: `App\Infrastructure\Database\Connection` enforces `PDO::ERRMODE_EXCEPTION`, `PDO::ATTR_EMULATE_PREPARES => false`, explicit typed parameter bindings (`PARAM_INT`, `PARAM_STR`, `PARAM_NULL`), and transaction rollback guards.
- **Forensic Audit Trail**: [`App\Infrastructure\Logging\AuditLogger`](../src/Infrastructure/Logging/AuditLogger.php) records sensitive events (registrations, logins, lockouts, logouts, bookings, booking conflicts, task creates/updates, file uploads/downloads, and unhandled authorization failures) to both `audit_log` in MariaDB and the Monolog file channel.

---

## 4. Non-Web Department File Storage & Upload Controls

Handled by [`App\Infrastructure\Storage\DepartmentFileStorage`](../src/Infrastructure/Storage/DepartmentFileStorage.php) and [`App\Modules\Files\Services\FileService`](../src/Modules/Files/Services/FileService.php):

1. **Isolated Non-Web Location**: Stored strictly outside `public/` under `storage/departments/{departmentSlug}/`. Each folder includes a defense-in-depth deny-all `.htaccess` alongside `docs/nginx.conf.example` rules.
2. **RBAC-Gated Streaming**: Files can ONLY be listed or downloaded via `FileController` after verifying `canViewDepartment()` / `canWriteDepartment()`.
3. **Upload Hardening**:
   - Enforces `.env` byte ceiling (`UPLOAD_MAX_BYTES`), non-empty checks, and PHP upload error validation.
   - Verifies extension allowlist (`UPLOAD_ALLOWED_EXTENSIONS`) AND binary content MIME detection via PHP's `finfo(FILEINFO_MIME_TYPE)` (`UPLOAD_ALLOWED_MIMES`), plus extension-to-MIME compatibility verification — client-sent `Content-Type` headers are ignored.
   - Replaces filenames on disk with CSPRNG hex (`bin2hex(random_bytes(16)).ext`), sets `0640` permissions, computes SHA-256 checksums, and keeps sanitized `original_name` solely as database metadata.
   - Enforces `basename()`, strict hex-filename regex, and canonical `realpath()` containment inside the target department folder during downloads (`X-Content-Type-Options: nosniff`, sanitized `Content-Disposition: attachment`).
