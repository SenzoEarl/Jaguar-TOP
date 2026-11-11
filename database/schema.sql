-- =============================================================================
-- Wildcrest Lodges & Camps - Relational Schema (MariaDB 10.4+ / 10.11+)
-- =============================================================================
-- Idempotent DDL: safe to re-run without destructive side effects.
-- Charset/Collation: utf8mb4 / utf8mb4_unicode_ci across database and all tables.
-- Naming convention: snake_case for all tables, columns, indexes, and constraints.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `wildcrest_lodges`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `wildcrest_lodges`;

-- -----------------------------------------------------------------------------
-- 1. Roles (maps 1:1 to App\Support\Enums\Role backed int enum, levels 0..6)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` TINYINT UNSIGNED NOT NULL,
    `slug` VARCHAR(50) NOT NULL,
    `name` VARCHAR(80) NOT NULL,
    `level` TINYINT UNSIGNED NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_slug` (`slug`),
    UNIQUE KEY `uq_roles_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Departments (maps to App\Support\Enums\Department backed string enum)
--    Level 1 has 3 departments (general-admin, sales, care) with cross-read.
--    Levels 2..6 each map to their own department directory for documents/tasks.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(60) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `role_level` TINYINT UNSIGNED NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_departments_slug` (`slug`),
    KEY `idx_departments_role_level` (`role_level`),
    CONSTRAINT `fk_departments_role_level`
        FOREIGN KEY (`role_level`) REFERENCES `roles` (`level`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Users (customers at level 0 have department_id = NULL; staff levels 1..6
--    are assigned a department matching their role_level)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(60) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `full_name` VARCHAR(120) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role_level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `department_id` INT UNSIGNED NULL DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role_level` (`role_level`),
    KEY `idx_users_department_id` (`department_id`),
    CONSTRAINT `fk_users_role_level`
        FOREIGN KEY (`role_level`) REFERENCES `roles` (`level`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT `fk_users_department_id`
        FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Login Attempts (DB-backed brute-force protection per username and per IP)
--    [SECURITY:BRUTE-FORCE] Indexed for sliding-window counts.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(60) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `was_successful` TINYINT(1) NOT NULL DEFAULT 0,
    `user_agent` VARCHAR(255) NULL DEFAULT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_login_attempts_username_time` (`username`, `attempted_at`),
    KEY `idx_login_attempts_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Products (lodges, camps, accommodation units)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(120) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `type` ENUM('lodge', 'camp', 'accommodation') NOT NULL,
    `location` VARCHAR(120) NOT NULL,
    `short_summary` VARCHAR(280) NOT NULL,
    `description` TEXT NOT NULL,
    `price_per_night` DECIMAL(10, 2) NOT NULL,
    `max_guests` SMALLINT UNSIGNED NOT NULL,
    `bedrooms` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `amenities_json` LONGTEXT NULL DEFAULT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `image_alt` VARCHAR(200) NOT NULL,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_products_slug` (`slug`),
    KEY `idx_products_type_active` (`type`, `is_active`),
    KEY `idx_products_featured` (`is_featured`, `is_active`),
    CONSTRAINT `chk_products_price_positive` CHECK (`price_per_night` > 0),
    CONSTRAINT `chk_products_max_guests_positive` CHECK (`max_guests` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Bookings
--    Overlap prevention strategy (enforced atomically in BookingService):
--      1. Start InnoDB transaction (`BEGIN`).
--      2. Lock target product row (`SELECT ... FROM products WHERE id = ? FOR UPDATE`)
--         to serialize concurrent bookings on the same lodge/camp/unit.
--      3. Lock overlapping active bookings (`status IN ('pending', 'confirmed')`)
--         where `check_in_date < :new_check_out AND check_out_date > :new_check_in`
--         using `FOR UPDATE`.
--      4. Check unique `idempotency_key` before insertion so duplicate client
--         submissions safely return the already-created booking without double-booking.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bookings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_reference` VARCHAR(32) NOT NULL,
    `idempotency_key` CHAR(64) NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `check_in_date` DATE NOT NULL,
    `check_out_date` DATE NOT NULL,
    `guests_count` SMALLINT UNSIGNED NOT NULL,
    `nights_count` SMALLINT UNSIGNED NOT NULL,
    `total_price` DECIMAL(10, 2) NOT NULL,
    `status` ENUM('pending', 'confirmed', 'cancelled', 'completed') NOT NULL DEFAULT 'confirmed',
    `special_requests` TEXT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_bookings_reference` (`booking_reference`),
    UNIQUE KEY `uq_bookings_idempotency_key` (`idempotency_key`),
    KEY `idx_bookings_overlap` (`product_id`, `status`, `check_in_date`, `check_out_date`),
    KEY `idx_bookings_user_created` (`user_id`, `created_at`),
    CONSTRAINT `fk_bookings_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT `fk_bookings_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT `chk_bookings_date_range` CHECK (`check_out_date` > `check_in_date`),
    CONSTRAINT `chk_bookings_guests_positive` CHECK (`guests_count` > 0),
    CONSTRAINT `chk_bookings_nights_positive` CHECK (`nights_count` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Staff Tasks (per-department and per-user operational tasks)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tasks` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(160) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `status` ENUM('todo', 'in_progress', 'blocked', 'done') NOT NULL DEFAULT 'todo',
    `priority` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    `department_id` INT UNSIGNED NOT NULL,
    `assigned_to_user_id` INT UNSIGNED NOT NULL,
    `created_by_user_id` INT UNSIGNED NOT NULL,
    `due_date` DATE NULL DEFAULT NULL,
    `completed_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tasks_department_status` (`department_id`, `status`),
    KEY `idx_tasks_assigned_status` (`assigned_to_user_id`, `status`),
    CONSTRAINT `fk_tasks_department`
        FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT `fk_tasks_assigned_to`
        FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT `fk_tasks_created_by`
        FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Department Files (metadata only; binaries reside in non-web storage/)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `files` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `department_id` INT UNSIGNED NOT NULL,
    `uploaded_by_user_id` INT UNSIGNED NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `stored_name` VARCHAR(120) NOT NULL,
    `mime_type` VARCHAR(127) NOT NULL,
    `file_extension` VARCHAR(20) NOT NULL,
    `size_bytes` BIGINT UNSIGNED NOT NULL,
    `checksum_sha256` CHAR(64) NOT NULL,
    `description` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_files_dept_stored_name` (`department_id`, `stored_name`),
    KEY `idx_files_department_created` (`department_id`, `created_at`),
    CONSTRAINT `fk_files_department`
        FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT `fk_files_uploaded_by`
        FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. Audit Log (append-only security & business action trail)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NULL DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(60) NULL DEFAULT NULL,
    `entity_id` VARCHAR(64) NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NULL DEFAULT NULL,
    `metadata_json` LONGTEXT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_action_created` (`action`, `created_at`),
    KEY `idx_audit_user_created` (`user_id`, `created_at`),
    CONSTRAINT `fk_audit_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
