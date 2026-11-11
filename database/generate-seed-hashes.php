<?php

declare(strict_types=1);

/**
 * CLI Helper: Generate and verify Argon2id (or bcrypt fallback) password hashes
 * for the 9 DEV/TEST ONLY seed accounts in database/seed.sql.
 *
 * Usage:
 *   php database/generate-seed-hashes.php
 */

$accounts = [
    ['username' => 'user0', 'password' => 'user0@user.com', 'role' => 'Customer (L0)'],
    ['username' => 'user1', 'password' => 'user1@user.com', 'role' => 'Admin - General Admin (L1)'],
    ['username' => 'user2', 'password' => 'user2@user.com', 'role' => 'Admin - Sales (L1)'],
    ['username' => 'user3', 'password' => 'user3@user.com', 'role' => 'Admin - Care (L1)'],
    ['username' => 'user4', 'password' => 'user4@user.com', 'role' => 'Supervisor (L2)'],
    ['username' => 'user5', 'password' => 'user5@user.com', 'role' => 'Manager (L3)'],
    ['username' => 'user6', 'password' => 'user6@user.com', 'role' => 'Senior Manager (L4)'],
    ['username' => 'user7', 'password' => 'user7@user.com', 'role' => 'Director (L5)'],
    ['username' => 'user8', 'password' => 'user8@user.com', 'role' => 'Developer / Root (L6)'],
];

$algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
$options = defined('PASSWORD_ARGON2ID')
    ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]
    : ['cost' => 12];

foreach ($accounts as $account) {
    $hash = password_hash($account['password'], $algo, $options);
    if (!password_verify($account['password'], $hash)) {
        fwrite(STDERR, "Verification failed for {$account['username']}\n");
        exit(1);
    }

    echo sprintf(
        "%s | %-28s | %s\n",
        $account['username'],
        $account['role'],
        $hash
    );
}
