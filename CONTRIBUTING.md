# Contributing Guidelines

Thank you for contributing to **Jaguar Safari**. All contributions must preserve security boundaries, strict typing, and consistent coding standards.

## Development Workflow

1. Copy `.env.example` to `.env` for local testing and keep real credentials out of Git (`.gitignore` excludes `.env`).
2. Import `database/schema.sql` and `database/seed.sql` into a clean MariaDB 10.11+ database before manual end-to-end verification.
3. Keep business logic modular under `src/Modules/` using `Controller -> Service -> Repository` boundaries — avoid placing SQL in controllers or ad-hoc role checks outside `AccessControl`.

## Coding Standards

### PHP (`phpcs.xml`)
- Strictly follow **PSR-12** with `declare(strict_types=1);` in every PHP file (`<?php`, optional file docblock, `declare(strict_types=1);`).
- Use explicit property types, parameter types, return types, `readonly` properties where sensible, and backed PHP enums for fixed domain sets.
- Always bind inputs with PDO prepared statements via `App\Infrastructure\Database\Connection`.
- Run PHPCS + PHPUnit before committing:
  ```bash
  composer check
  ```

### JavaScript (`.eslintrc.json`)
- Strictly follow **Airbnb Base** (`airbnb-base`), ES modules (`export class ...`), one class per file, `const`/`let` only, zero jQuery, zero globals.
- Never insert untrusted user/server strings via `innerHTML`; use `DomHelper` / `textContent`.
- All API calls must go through `ApiClient` so CSRF headers and error normalization remain centralized.
- Run ESLint before committing:
  ```bash
  npm run lint:js
  ```

## Security & Testing Rules

- Any change affecting auth, sessions, CSRF, XSS headers/escaping, error handling, or brute-force throttling must preserve or update its corresponding `// [SECURITY:*]` banner and `docs/SECURITY.md` documentation.
- Add unit or integration test coverage under `tests/Unit/` or `tests/Integration/` for bug fixes and new domain rules.
- Never commit `storage/logs/*.log`, `storage/cache/blade/*.php`, uploaded documents inside `storage/departments/*` (except `.htaccess`/`.gitkeep`), or `.env`.
