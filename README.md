# Wildcrest Lodges & Camps

A production-oriented, multi-role web application for a conservation lodge, tented camp, and wilderness accommodation business built with **PHP 8.2/8.3+ (`strict_types=1`)**, **MariaDB 10.4+/10.11+ (PDO)**, **BladeOne**, **Bootstrap 5**, and **ES2022+ OOP vanilla JavaScript**.

---

## Features at a Glance

- **Public Website & Booking Flow**: SEO-friendly home, filterable lodges/camps catalog (`/stays`), lodge detail pages with JSON-LD `LodgingBusiness` schema, real-time availability checks, customer registration & login, atomic reservation creation (InnoDB `FOR UPDATE` row locking + idempotency key deduplication), and booking history dashboard (`/account/dashboard`).
- **7-Level Role-Based Staff Portal (`0..6`)**: Explicit deny-by-default RBAC map (`config/permissions.php`) governing Customer (`L0`), Admin (`L1`: **General Admin**, **Sales**, **Care** with cross-department read-only collaboration and isolated writes), Supervisor (`L2`), Manager (`L3`), Senior Manager (`L4`), Director (`L5`), and Developer / Root (`L6`).
- **Department Tasks & Protected Document Vault**: Per-department operational task boards plus non-web file storage (`storage/departments/{slug}/`) protected by RBAC download controllers, `finfo` binary MIME inspection, and path-traversal guards.
- **Modular Monolith Seams**: Clean `Controller -> Service -> Repository` architecture implementing `ModuleInterface`, ready for new departments or domain modules without touching core code.

---

## Tech Stack & Requirements

- **PHP**: `>= 8.2` (tested on `8.2.12`, written with PHP 8.3+ idioms and `declare(strict_types=1);` in every file)
  - Required extensions: `pdo`, `pdo_mysql`, `fileinfo`, `json`, `mbstring`, `openssl` (`sodium` / Argon2id recommended; auto-falls back to bcrypt if configured)
- **Database**: MariaDB `10.4+` / `10.11+` (`utf8mb4` / `utf8mb4_unicode_ci`)
- **Tooling**: Composer `2.x`, Node.js `18+` & npm (only needed to copy Bootstrap assets / run ESLint)

---

## Local Setup Instructions

### 1. Install Dependencies & Frontend Assets

```bash
composer install
npm install
npm run copy:bootstrap
```

### 2. Configure Environment (`.env` Single Source of Truth)

```bash
cp .env.example .env
```

Edit `.env` to match your local host settings:
- Set `DB_HOST`, `DB_PORT`, `DB_DATABASE=wildcrest_lodges`, `DB_USERNAME`, `DB_PASSWORD`.
- If running under an Apache subdirectory (e.g. XAMPP `http://localhost/TOP/public`), set:
  ```dotenv
  APP_URL=http://localhost/TOP/public
  APP_BASE_PATH=/TOP/public
  ```
  If using a dedicated virtual host or PHP's built-in server targeting `public/`, leave `APP_BASE_PATH=` empty.
- For production HTTPS deployments, set `APP_ENV=production`, `APP_DEBUG=false`, `VIEW_MODE=fast`, and `SESSION_COOKIE_SECURE=true`.

### 3. Initialize Database & Seed Data

Import both SQL scripts into MariaDB (both files are safe and idempotent to re-run):

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed.sql
```

### 4. Run Locally

**Option A — PHP built-in server (quickest for local testing):**
```bash
php -S 127.0.0.1:8000 -t public
```
Then open `http://127.0.0.1:8000/`.

**Option B — Apache / XAMPP:**
- Configure a VirtualHost whose `DocumentRoot` points directly to `<project>/public` (or browse `http://localhost/TOP/public` with `APP_BASE_PATH=/TOP/public` set in `.env`).
- Ensure Apache `mod_rewrite` is enabled (`public/.htaccess` handles front-controller routing; `storage/.htaccess` and `storage/departments/*/.htaccess` enforce `Require all denied`).

**Production — Nginx + PHP-FPM:**
- Use [`docs/nginx.conf.example`](docs/nginx.conf.example) so **only** `public/` is web-accessible.

---

## Seeded Dev/Test Accounts (9 Accounts)

> [!WARNING]
> **DEV / TEST ONLY — REMOVE OR ROTATE BEFORE DEPLOYMENT**
> All 9 accounts below share predictable credentials (`userN@user.com`) stored as Argon2id hashes in [`database/seed.sql`](database/seed.sql). Delete or rotate these accounts before exposing any non-local environment.

| Username | Password | Role (Level) | Department | Default Landing Path |
|---|---|---|---|---|
| `user0` | `user0@user.com` | Customer (`0`) | — | `/account/dashboard` |
| `user1` | `user1@user.com` | Admin (`1`) | General Admin | `/staff/general-admin/dashboard` |
| `user2` | `user2@user.com` | Admin (`1`) | Sales | `/staff/sales/dashboard` |
| `user3` | `user3@user.com` | Admin (`1`) | Care | `/staff/care/dashboard` |
| `user4` | `user4@user.com` | Supervisor (`2`) | Supervisor | `/staff/supervisor/dashboard` |
| `user5` | `user5@user.com` | Manager (`3`) | Manager | `/staff/manager/dashboard` |
| `user6` | `user6@user.com` | Senior Manager (`4`) | Senior Manager | `/staff/senior-manager/dashboard` |
| `user7` | `user7@user.com` | Director (`5`) | Director | `/staff/director/dashboard` |
| `user8` | `user8@user.com` | Developer / Root (`6`) | Developer / Root | `/staff/developer/dashboard` |

---

## Automated Checks & Linting

```bash
# Run PHPCS (PSR-12) + PHPUnit unit & PDO integration suites
composer check

# Run PHPUnit alone
composer test

# Run PSR-12 linter alone
composer lint

# Run Airbnb ESLint across ES6 frontend modules
npm run lint:js
```

Note: The PHPUnit test suite executes isolated in-memory SQLite PDO schemas for `AuthSecurityTest` and `BookingTransactionTest`, so security and transactional logic can be verified safely without mutating your MariaDB database.

---

## Documentation Index

- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — Modular monolith design, request pipeline, and dependency justifications
- [`docs/DATABASE.md`](docs/DATABASE.md) — Schema overview, booking concurrency locking strategy, and seed details
- [`docs/SECURITY.md`](docs/SECURITY.md) — Complete index of `[SECURITY:*]` controls, RBAC rules, and upload hardening
- [`docs/ADDING_A_MODULE.md`](docs/ADDING_A_MODULE.md) — Adding a domain feature via `ModuleInterface`
- [`docs/ADDING_A_DEPARTMENT.md`](docs/ADDING_A_DEPARTMENT.md) — Registering a new staff department end-to-end
- [`docs/nginx.conf.example`](docs/nginx.conf.example) — Hardened production Nginx configuration
- [`CONTRIBUTING.md`](CONTRIBUTING.md) & [`CHANGELOG.md`](CHANGELOG.md)
