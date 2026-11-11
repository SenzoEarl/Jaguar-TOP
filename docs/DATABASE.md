# Database Design & Operations (`database/`)

All schema DDL and seed scripts are separated cleanly into two idempotent files:
- [`database/schema.sql`](../database/schema.sql): `CREATE DATABASE/TABLE IF NOT EXISTS`, `InnoDB`, `utf8mb4` / `utf8mb4_unicode_ci`, foreign keys, composite indexes, and `CHECK` constraints.
- [`database/seed.sql`](../database/seed.sql): deterministic `INSERT ... ON DUPLICATE KEY UPDATE` records safe to re-run repeatedly without duplicating data.
- [`database/generate-seed-hashes.php`](../database/generate-seed-hashes.php): helper script used to generate and verify the deterministic Argon2id hashes embedded in `seed.sql`.

---

## 1. Schema Overview (9 Tables)

| Table | Purpose | Key Indexes & Constraints |
|---|---|---|
| `roles` | Canonical hierarchy (`level` `0..6`) matching `App\Support\Enums\Role` | `PRIMARY KEY (id)`, `uq_roles_slug`, `uq_roles_level` |
| `departments` | 8 staff workspaces (`general-admin`, `sales`, `care`, `supervisor`, `manager`, `senior-manager`, `director`, `developer`) | `PRIMARY KEY (id)`, `uq_departments_slug`, `fk_departments_role_level` -> `roles(level)` |
| `users` | Customer (`role_level = 0`, `department_id = NULL`) and staff (`role_level >= 1`) accounts | `uq_users_username`, `uq_users_email`, `fk_users_role_level`, `fk_users_department_id` (`ON DELETE SET NULL`) |
| `login_attempts` | DB-backed brute-force audit & sliding-window throttling log `[SECURITY:BRUTE-FORCE]` | `idx_login_attempts_username_time`, `idx_login_attempts_ip_time` |
| `products` | Lodges, camps, and accommodation units (`type` ENUM, `price_per_night`, `max_guests`, `amenities_json`) | `uq_products_slug`, `idx_products_type_active`, `idx_products_featured`, `chk_products_price_positive`, `chk_products_max_guests_positive` |
| `bookings` | Reservations linked to `users` and `products` with overlap prevention & idempotency guarantees | `uq_bookings_reference`, `uq_bookings_idempotency_key`, `idx_bookings_overlap (product_id, status, check_in_date, check_out_date)`, `idx_bookings_user_created`, `chk_bookings_date_range`, `chk_bookings_guests_positive`, `chk_bookings_nights_positive` |
| `tasks` | Department-scoped staff operational tasks (`todo`, `in_progress`, `blocked`, `done`) | `idx_tasks_department_status`, `idx_tasks_assigned_status`, `fk_tasks_department`, `fk_tasks_assigned_to`, `fk_tasks_created_by` |
| `files` | Metadata for department vault uploads (binaries stay outside web root in `storage/departments/{slug}/`) | `uq_files_dept_stored_name`, `idx_files_department_created`, `fk_files_department`, `fk_files_uploaded_by` |
| `audit_log` | Append-only security & business action trail (`user_id`, `action`, `entity_type`, `entity_id`, `ip_address`, `metadata_json`) | `idx_audit_action_created`, `idx_audit_user_created`, `fk_audit_user` (`ON DELETE SET NULL`) |

---

## 2. Booking Overlap Prevention & Idempotency Strategy

MariaDB / MySQL does not enforce range exclusion constraints natively in DDL alone, so double-booking protection combines **indexed SQL predicates** with **pessimistic InnoDB row locking** inside `App\Modules\Booking\Services\BookingService::createBooking()` and `App\Modules\Booking\Repositories\BookingRepository`:

1. **Idempotency Check (Pre-flight & In-Transaction)**:
   - Every booking submission carries a client/session-generated 32–64 hex `idempotency_key`.
   - Before locking/inserting — and again inside the transaction — `BookingRepository::findByIdempotencyKey()` checks for an existing row (`uq_bookings_idempotency_key`). Duplicate submissions (e.g., double-click or network retry) safely return the already-created reservation for that user instead of charging or double-booking.
2. **Product Row Serialization (`FOR UPDATE`)**:
   - Inside `$connection->transaction(...)`, `BookingRepository::lockActiveProduct()` executes `SELECT * FROM products WHERE id = :id AND is_active = 1 LIMIT 1 FOR UPDATE` (on drivers supporting row locks, e.g. MariaDB InnoDB). Concurrent booking requests for the same property serialize on that row lock.
3. **Half-Open Interval Overlap Locking (`FOR UPDATE`)**:
   - `BookingRepository::lockOverlappingBookingIds()` queries active statuses (`pending`, `confirmed`) using half-open date logic:
     ```sql
     check_in_date < :check_out AND check_out_date > :check_in
     ```
   - This rejects overlapping stays while allowing same-day turnaround (Guest A checks out `2026-12-10`, Guest B checks in `2026-12-10`).
4. **Server-Authoritative Pricing & Atomic Rollback**:
   - Total price is always computed server-side (`nights * price_per_night`) — never trusted from form fields.
   - Any validation failure, capacity excess, or conflict throws `BookingConflictException` (HTTP `409`), automatically rolling back the PDO transaction.

---

## 3. Deterministic Seed Inventory (`database/seed.sql`)

Re-running `database/seed.sql` cleanly upserts:
- **7 Roles** (`0` Customer .. `6` Developer / Root)
- **8 Departments** (`general-admin`, `sales`, `care`, `supervisor`, `manager`, `senior-manager`, `director`, `developer`)
- **9 Dev/Test User Accounts** (`user0` .. `user8`) with pre-generated Argon2id password hashes
- **8 Products** spanning all 3 types (`lodge`, `camp`, `accommodation`) with matching local SVG illustrations under `public/assets/img/products/`
- **4 Sample Bookings** (`JGS-2026-0001` .. `JGS-2026-0004`) for `user0` covering `confirmed`, `completed`, and `cancelled` states
- **12 Sample Staff Tasks** distributed across all 8 departments
