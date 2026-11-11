-- =============================================================================
-- Jaguar Safari - Deterministic & Idempotent Seed Data
-- =============================================================================
-- Safe to re-run repeatedly via INSERT ... ON DUPLICATE KEY UPDATE.
-- Prerequisites: import database/schema.sql first.
--
-- =============================================================================
-- WARNING: DEV / TEST ONLY ACCOUNTS
-- =============================================================================
-- The 9 seeded user accounts below (`user0` through `user8`) use predictable
-- development credentials (`userN@user.com`). They are strictly for local
-- testing and QA. REMOVE OR ROTATE ALL SEEDED CREDENTIALS BEFORE ANY
-- NON-LOCAL / STAGING / PRODUCTION DEPLOYMENT.
-- =============================================================================

USE `jaguar_safari`;

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- 1. Roles (Levels 0..6 matching App\Support\Enums\Role)
-- -----------------------------------------------------------------------------
INSERT INTO `roles` (`id`, `slug`, `name`, `level`, `description`)
VALUES
    (0, 'customer', 'Customer', 0, 'Registered guest with access to own bookings, profile, and public catalog.'),
    (1, 'admin', 'Admin', 1, 'Departmental staff across General Admin, Sales, and Care with cross-department read visibility.'),
    (2, 'supervisor', 'Supervisor', 2, 'Shift and operations supervisor with oversight over L1 departmental workflows.'),
    (3, 'manager', 'Manager', 3, 'Property and operations manager overseeing supervisors and departmental execution.'),
    (4, 'senior-manager', 'Senior Manager', 4, 'Multi-property senior manager overseeing operational standards and escalation.'),
    (5, 'director', 'Director', 5, 'Executive director with strategic portfolio oversight across all lower levels.'),
    (6, 'developer', 'Developer / Root', 6, 'Platform engineer / root administrator with full diagnostic and system access.')
ON DUPLICATE KEY UPDATE
    `slug` = VALUES(`slug`),
    `name` = VALUES(`name`),
    `level` = VALUES(`level`),
    `description` = VALUES(`description`);

-- -----------------------------------------------------------------------------
-- 2. Departments (8 canonical slugs matching storage/departments/{slug}/)
-- -----------------------------------------------------------------------------
INSERT INTO `departments` (`id`, `slug`, `name`, `role_level`, `description`, `is_active`)
VALUES
    (1, 'general-admin', 'General Admin', 1, 'Front-desk coordination, compliance records, and lodge logistics.', 1),
    (2, 'sales', 'Sales', 1, 'Reservations desk, group inquiries, and seasonal rate coordination.', 1),
    (3, 'care', 'Care', 1, 'Guest concierge, housekeeping readiness, and conservation hospitality.', 1),
    (4, 'supervisor', 'Supervisor', 2, 'Daily property supervision, shift handovers, and escalation triage.', 1),
    (5, 'manager', 'Manager', 3, 'Operational management, occupancy pacing, and resource planning.', 1),
    (6, 'senior-manager', 'Senior Manager', 4, 'Regional property leadership, SLA governance, and vendor contracts.', 1),
    (7, 'director', 'Director', 5, 'Executive governance, capital budgets, and conservation strategy.', 1),
    (8, 'developer', 'Developer / Root', 6, 'System health, security telemetry, and application maintenance.', 1)
ON DUPLICATE KEY UPDATE
    `slug` = VALUES(`slug`),
    `name` = VALUES(`name`),
    `role_level` = VALUES(`role_level`),
    `description` = VALUES(`description`),
    `is_active` = VALUES(`is_active`);

-- -----------------------------------------------------------------------------
-- 3. DEV/TEST ONLY Accounts (9 accounts: user0 .. user8)
--    Passwords follow `userN@user.com`, stored exclusively as Argon2id hashes
--    generated and verified via database/generate-seed-hashes.php.
-- -----------------------------------------------------------------------------
INSERT INTO `users` (`id`, `username`, `email`, `full_name`, `password_hash`, `role_level`, `department_id`, `is_active`)
VALUES
    (1, 'user0', 'user0@user.com', 'Alex Rivera (Customer)', '$argon2id$v=19$m=65536,t=4,p=1$LkppZ2gxamR0dkNiR1hoYQ$Ta74tmRHaqTctlErF7Zeulyeph4ov81wuYKuSU+6rVI', 0, NULL, 1),
    (2, 'user1', 'user1@user.com', 'Nadia Patel (General Admin)', '$argon2id$v=19$m=65536,t=4,p=1$alYuRlAyMVF4L0tEaHFnZQ$aQVKcIIV/w9ALjaG+MUksIfvN54GVgV3+qKvlS8JZ8g', 1, 1, 1),
    (3, 'user2', 'user2@user.com', 'Marcus Chen (Sales Admin)', '$argon2id$v=19$m=65536,t=4,p=1$WFpNSUpsaFdQWXdqMjl2Ng$SieFe1ef+1+KHZTSNwsaFPWPw9wZChUeWDIqOuJ7pVg', 1, 2, 1),
    (4, 'user3', 'user3@user.com', 'Thandi Mokoena (Care Admin)', '$argon2id$v=19$m=65536,t=4,p=1$aUdzbmxFWnRWeGwxdWtpQQ$AFaMLZZWWLvWuXWS3PyYOARMYfiKiNgoEIKiZUkzNqo', 1, 3, 1),
    (5, 'user4', 'user4@user.com', 'David Okafor (Supervisor)', '$argon2id$v=19$m=65536,t=4,p=1$eXU3MGtMamRud2Q5V2dGUQ$Nl7ltyQhFgqs5h3eY8QwnYNHd83sS8J9yt/niNFpkZk', 2, 4, 1),
    (6, 'user5', 'user5@user.com', 'Elena Vance (Manager)', '$argon2id$v=19$m=65536,t=4,p=1$dHVHaHRNTHl0UlgvVUwzRw$CAcjpDMdjiLuTTuNQWZ58p1CPHi1FdRbCNCbeBWjvYc', 3, 5, 1),
    (7, 'user6', 'user6@user.com', 'Samuel Botha (Senior Manager)', '$argon2id$v=19$m=65536,t=4,p=1$aU12aW9TZHFrS2Fic2swTA$ztkkG1CcpwquNIo3lCvJqsuCCQNf7q7RV4dnR4/y5G0', 4, 6, 1),
    (8, 'user7', 'user7@user.com', 'Clara Lindqvist (Director)', '$argon2id$v=19$m=65536,t=4,p=1$VllGQU11dXNkQS9CeHlrNg$qW0GzbfbKPtOfu2wnG6crKU4UviotbN3IOqm9VLJ1HE', 5, 7, 1),
    (9, 'user8', 'user8@user.com', 'Kiran Dev (Developer / Root)', '$argon2id$v=19$m=65536,t=4,p=1$SUt5eWxvY2cvZWlZM29VVQ$bfVHVAkmIMsOTRoCzMS2ZbNa2mwoUC42Mr0EN30jREI', 6, 8, 1)
ON DUPLICATE KEY UPDATE
    `email` = VALUES(`email`),
    `full_name` = VALUES(`full_name`),
    `password_hash` = VALUES(`password_hash`),
    `role_level` = VALUES(`role_level`),
    `department_id` = VALUES(`department_id`),
    `is_active` = VALUES(`is_active`);

-- -----------------------------------------------------------------------------
-- 4. Products (8 lodges, camps, and accommodation retreats)
-- -----------------------------------------------------------------------------
INSERT INTO `products` (
    `id`,
    `slug`,
    `name`,
    `type`,
    `location`,
    `short_summary`,
    `description`,
    `price_per_night`,
    `max_guests`,
    `bedrooms`,
    `amenities_json`,
    `image_path`,
    `image_alt`,
    `is_featured`,
    `is_active`
)
VALUES
    (
        1,
        'eagle-ridge-lodge',
        'Eagle Ridge Timber Lodge',
        'lodge',
        'Drakensberg Escarpment',
        'Panoramic stone-and-cedar ridge lodge overlooking misted highland valleys.',
        'Perched 1,800 meters above the escarpment floor, Eagle Ridge Timber Lodge combines hand-dressed basalt hearths with expansive wrap-around timber decks. Ideal for multi-generational family retreats or private guided walking safaris, the lodge includes a dedicated chef kitchen, solar-heated plunge pool, and uninterrupted sunrise views across the reserve.',
        460.00,
        8,
        4,
        '["Private Plunge Pool","Wood-Fired Hearth","Solar Off-Grid Power","Guided Trail Access","High-Speed Satellite Wi-Fi"]',
        '/assets/img/products/lodge-eagle-ridge.svg',
        'Illustration of Eagle Ridge Timber Lodge perched on a misty mountain ridge at sunrise',
        1,
        1
    ),
    (
        2,
        'riverbend-timber-lodge',
        'Riverbend Crossing Lodge',
        'lodge',
        'Lower Sabie Gorge',
        'Private riverside sanctuary framed by ancient fig trees and quiet waterholes.',
        'Set along a tranquil bend of the perennial Sabie tributary, Riverbend Crossing Lodge invites guests to watch wildlife visit the riverbank from shaded teak verandas. Three en-suite pavilions flank a central open-air dining boma, offering seamless indoor-outdoor living with nocturnal acoustic privacy.',
        520.00,
        6,
        3,
        '["Riverfront Deck","Outdoor Boma Dining","Private Wildlife Hide","Air Conditioning","Daily Housekeeping"]',
        '/assets/img/products/lodge-riverbend.svg',
        'Illustration of Riverbend Crossing Lodge nestled beside a calm winding river',
        1,
        1
    ),
    (
        3,
        'baobab-cliff-lodge',
        'Baobab Cliff Sanctuary',
        'lodge',
        'Limpopo Sandstone Belt',
        'Architectural sandstone cliff lodge anchored beside thousand-year-old baobabs.',
        'Carved thoughtfully into weathered red sandstone outcrops, Baobab Cliff Sanctuary pairs dramatic geological views with minimalist bush luxury. Enjoy elevated stargazing terraces, a shaded library lounge, and direct access to guided morning tracking walks led by certified field rangers.',
        610.00,
        6,
        3,
        '["Stargazing Terrace","Infinity Rim Pool","Private Ranger Guide","Wine & Espresso Bar","En-Suite Rain Showers"]',
        '/assets/img/products/lodge-baobab-cliff.svg',
        'Illustration of Baobab Cliff Sanctuary among red sandstone cliffs and baobab trees',
        0,
        1
    ),
    (
        4,
        'savanna-canvas-camp',
        'Savanna Horizon Tented Camp',
        'camp',
        'Central Plains Reserve',
        'Classic canvas expedition suites elevated on hardwood platforms over open plains.',
        'Savanna Horizon Tented Camp revives golden-age expedition craft with spacious double-canvas suites, brass lanterns, and private viewing decks raised above wildflower grasslands. Guests gather around evening firepits under zero-light-pollution skies after afternoon game drives.',
        340.00,
        4,
        2,
        '["Elevated Canvas Deck","Evening Campfire Lounge","4x4 Safari Drives","Copper Soaking Tub","Solar Lighting"]',
        '/assets/img/products/camp-savanna-tented.svg',
        'Illustration of Savanna Horizon Tented Camp on raised wooden decks under golden savanna skies',
        1,
        1
    ),
    (
        5,
        'starlight-bush-camp',
        'Starlight Acacia Bush Camp',
        'camp',
        'Kalahari Fringe',
        'Intimate four-tent bush camp designed for astrophotography and quiet desert nights.',
        'Hidden within a grove of umbrella thorn acacias, Starlight Acacia Bush Camp minimizes its footprint with leave-no-trace solar engineering and retractable sky-mesh sleeping bays. Perfect for couples and solo naturalists seeking silence, nocturnal wildlife acoustics, and telescope evenings.',
        295.00,
        2,
        1,
        '["Sky-Mesh Roof Bay","8-Inch Dobsonian Telescope","Guided Night Walks","Bush Breakfast Service","Eco Filtration Water"]',
        '/assets/img/products/camp-starlight-bush.svg',
        'Illustration of Starlight Acacia Bush Camp beneath a clear star-filled night sky',
        0,
        1
    ),
    (
        6,
        'delta-mobile-camp',
        'Delta Floodplain Mobile Camp',
        'camp',
        'Seasonal Wetlands Basin',
        'Seasonal low-impact water camp positioned along crystal reed channels.',
        'Relocated twice annually to follow natural water levels, Delta Floodplain Mobile Camp places guests steps away from tranquil papyrus channels. Mornings unfold in traditional wooden canoes before returning to shaded dining pavilions and crisp linen canvas quarters.',
        390.00,
        4,
        2,
        '["Canoe Excursions","Seasonal Water Access","Private Veranda","Farm-to-Camp Meals","Binoculars Provided"]',
        '/assets/img/products/camp-delta-mobile.svg',
        'Illustration of Delta Floodplain Mobile Camp beside reed-lined water channels',
        0,
        1
    ),
    (
        7,
        'forest-canopy-cabin',
        'Forest Canopy Cedar Cabin',
        'accommodation',
        'Indigenous Mistbelt Forest',
        'Self-contained Nordic-inspired woodland cabin tucked beneath yellowwood trees.',
        'Designed for slow restorative stays, Forest Canopy Cedar Cabin features floor-to-ceiling woodland glazing, a curated vinyl listening corner, and a cedar wood-burning stove. Step straight onto five kilometers of marked fern-gully walking trails right outside your porch.',
        225.00,
        4,
        2,
        '["Full Self-Catering Kitchen","Wood-Burning Stove","Private Forest Trail","Fiber Wi-Fi","Outdoor Cedar Tub"]',
        '/assets/img/products/acc-forest-cabin.svg',
        'Illustration of Forest Canopy Cedar Cabin surrounded by tall mistbelt evergreen trees',
        1,
        1
    ),
    (
        8,
        'lakeview-stone-suite',
        'Lakeview Stone Courtyard Suite',
        'accommodation',
        'Highland Crater Lake',
        'Boutique lakeside stone suite ideal for couples, remote creatives, and weekend escapes.',
        'Offering effortless comfort near our main conservation hub, Lakeview Stone Courtyard Suite pairs warm limestone interiors with a private courtyard overlooking calm alpine waters. Includes complimentary kayak rental, workspace desk, and optional breakfast hamper delivery.',
        195.00,
        2,
        1,
        '["Courtyard Patio","Complimentary Kayaks","Ergonomic Workspace","Breakfast Hamper Option","Dedicated Parking"]',
        '/assets/img/products/acc-lakeview-suite.svg',
        'Illustration of Lakeview Stone Courtyard Suite overlooking a calm highland lake',
        0,
        1
    )
ON DUPLICATE KEY UPDATE
    `slug` = VALUES(`slug`),
    `name` = VALUES(`name`),
    `type` = VALUES(`type`),
    `location` = VALUES(`location`),
    `short_summary` = VALUES(`short_summary`),
    `description` = VALUES(`description`),
    `price_per_night` = VALUES(`price_per_night`),
    `max_guests` = VALUES(`max_guests`),
    `bedrooms` = VALUES(`bedrooms`),
    `amenities_json` = VALUES(`amenities_json`),
    `image_path` = VALUES(`image_path`),
    `image_alt` = VALUES(`image_alt`),
    `is_featured` = VALUES(`is_featured`),
    `is_active` = VALUES(`is_active`);

-- -----------------------------------------------------------------------------
-- 5. Sample Bookings (linked to user0; deterministic idempotency keys)
-- -----------------------------------------------------------------------------
INSERT INTO `bookings` (
    `id`,
    `booking_reference`,
    `idempotency_key`,
    `user_id`,
    `product_id`,
    `check_in_date`,
    `check_out_date`,
    `guests_count`,
    `nights_count`,
    `total_price`,
    `status`,
    `special_requests`
)
VALUES
    (
        1,
        'JGS-2026-0001',
        'a111111111111111111111111111111111111111111111111111111111111101',
        1,
        1,
        '2026-12-10',
        '2026-12-14',
        4,
        4,
        1840.00,
        'confirmed',
        'Early check-in requested for afternoon ridge walk.'
    ),
    (
        2,
        'JGS-2026-0002',
        'a111111111111111111111111111111111111111111111111111111111111102',
        1,
        4,
        '2027-01-18',
        '2027-01-21',
        2,
        3,
        1020.00,
        'confirmed',
        'Vegetarian dinner menu requested.'
    ),
    (
        3,
        'JGS-2026-0003',
        'a111111111111111111111111111111111111111111111111111111111111103',
        1,
        7,
        '2026-08-02',
        '2026-08-05',
        2,
        3,
        675.00,
        'completed',
        'Quiet cabin away from main trailhead.'
    ),
    (
        4,
        'JGS-2026-0004',
        'a111111111111111111111111111111111111111111111111111111111111104',
        1,
        8,
        '2026-09-12',
        '2026-09-14',
        2,
        2,
        390.00,
        'cancelled',
        'Rescheduled to winter dates.'
    )
ON DUPLICATE KEY UPDATE
    `booking_reference` = VALUES(`booking_reference`),
    `user_id` = VALUES(`user_id`),
    `product_id` = VALUES(`product_id`),
    `check_in_date` = VALUES(`check_in_date`),
    `check_out_date` = VALUES(`check_out_date`),
    `guests_count` = VALUES(`guests_count`),
    `nights_count` = VALUES(`nights_count`),
    `total_price` = VALUES(`total_price`),
    `status` = VALUES(`status`),
    `special_requests` = VALUES(`special_requests`);

-- -----------------------------------------------------------------------------
-- 6. Sample Staff Tasks (across all 8 departments for immediate dashboard demos)
-- -----------------------------------------------------------------------------
INSERT INTO `tasks` (
    `id`,
    `title`,
    `description`,
    `status`,
    `priority`,
    `department_id`,
    `assigned_to_user_id`,
    `created_by_user_id`,
    `due_date`,
    `completed_at`
)
VALUES
    (1, 'Verify December permit filings for Eagle Ridge', 'Confirm provincial conservation gate permits are stored in department files.', 'in_progress', 'high', 1, 2, 5, '2026-11-20', NULL),
    (2, 'Reconcile supplier fuel invoices', 'Cross-check generator diesel receipts for Sabie and Drakensberg sites.', 'todo', 'medium', 1, 2, 2, '2026-11-24', NULL),
    (3, 'Follow up on festive group inquiry (10 guests)', 'Prepare custom 4-night lodge quotation for corporate conservation retreat.', 'in_progress', 'high', 2, 3, 6, '2026-11-18', NULL),
    (4, 'Update Q1 seasonal rack rate sheet', 'Publish shoulder-season bundle notes for Care and General Admin review.', 'done', 'medium', 2, 3, 3, '2026-11-10', '2026-11-09 15:30:00'),
    (5, 'Prepare vegetarian menu card for JGS-2026-0002', 'Coordinate pantry dietary items for Savanna Horizon Tented Camp arrival.', 'todo', 'high', 3, 4, 4, '2026-12-01', NULL),
    (6, 'Inspect linen rotation & mosquito net inventory', 'Complete quarterly quality audit across all three camps.', 'in_progress', 'medium', 3, 4, 5, '2026-11-22', NULL),
    (7, 'Approve weekend ranger & housekeeping shift roster', 'Balance coverage across Eagle Ridge and Riverbend Crossing.', 'todo', 'high', 4, 5, 6, '2026-11-16', NULL),
    (8, 'Review guest feedback log from October stays', 'Summarize recurring maintenance items for Monday management briefing.', 'done', 'low', 4, 5, 5, '2026-11-08', '2026-11-08 11:15:00'),
    (9, 'Evaluate Q4 occupancy forecast & staffing buffer', 'Compare confirmed lodge bookings against prior year baseline.', 'in_progress', 'high', 5, 6, 7, '2026-11-25', NULL),
    (10, 'Finalize solar battery replacement tender', 'Shortlist off-grid storage vendors for Baobab Cliff Sanctuary.', 'blocked', 'urgent', 6, 7, 8, '2026-11-28', NULL),
    (11, 'Sign off 2027 conservation capital allocation', 'Approve capital budget envelopes for wetland camp boardwalk upgrades.', 'todo', 'high', 7, 8, 8, '2026-12-05', NULL),
    (12, 'Verify automated backup checksums & log rotation', 'Confirm Monolog daily logs and MariaDB dump integrity checks pass.', 'in_progress', 'medium', 8, 9, 9, '2026-11-19', NULL)
ON DUPLICATE KEY UPDATE
    `title` = VALUES(`title`),
    `description` = VALUES(`description`),
    `status` = VALUES(`status`),
    `priority` = VALUES(`priority`),
    `department_id` = VALUES(`department_id`),
    `assigned_to_user_id` = VALUES(`assigned_to_user_id`),
    `created_by_user_id` = VALUES(`created_by_user_id`),
    `due_date` = VALUES(`due_date`),
    `completed_at` = VALUES(`completed_at`);
