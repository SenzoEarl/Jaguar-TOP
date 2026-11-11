<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repositories;

use App\Infrastructure\Database\Connection;
use App\Modules\Auth\Models\User;
use App\Support\Enums\Role;

/**
 * PDO repository for user account persistence and lookup.
 */
final class UserRepository
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function findById(int $id): ?User
    {
        $row = $this->connection->fetchOne(
            'SELECT u.*, d.slug AS department_slug
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.id = :id
             LIMIT 1',
            ['id' => $id]
        );

        return $row !== null ? User::fromRow($row) : null;
    }

    public function findByUsernameOrEmail(string $identifier): ?User
    {
        $normalized = strtolower(trim($identifier));
        $row = $this->connection->fetchOne(
            'SELECT u.*, d.slug AS department_slug
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE LOWER(u.username) = :username OR LOWER(u.email) = :email
             LIMIT 1',
            [
                'username' => $normalized,
                'email' => $normalized,
            ]
        );

        return $row !== null ? User::fromRow($row) : null;
    }

    public function existsByUsernameOrEmail(string $username, string $email): bool
    {
        $row = $this->connection->fetchOne(
            'SELECT 1 AS found
             FROM users
             WHERE LOWER(username) = :username OR LOWER(email) = :email
             LIMIT 1',
            [
                'username' => strtolower(trim($username)),
                'email' => strtolower(trim($email)),
            ]
        );

        return $row !== null;
    }

    public function createCustomer(
        string $username,
        string $email,
        string $fullName,
        string $passwordHash
    ): User {
        $this->connection->execute(
            'INSERT INTO users (username, email, full_name, password_hash, role_level, department_id, is_active)
             VALUES (:username, :email, :full_name, :password_hash, :role_level, NULL, 1)',
            [
                'username' => strtolower(trim($username)),
                'email' => strtolower(trim($email)),
                'full_name' => trim($fullName),
                'password_hash' => $passwordHash,
                'role_level' => Role::Customer->value,
            ]
        );

        $id = $this->connection->lastInsertId();
        $user = $this->findById($id);
        if ($user === null) {
            throw new \RuntimeException('Created customer record could not be reloaded.');
        }

        return $user;
    }

    public function updatePasswordHash(int $userId, string $newHash): void
    {
        $this->connection->execute(
            'UPDATE users SET password_hash = :hash WHERE id = :id',
            [
                'hash' => $newHash,
                'id' => $userId,
            ]
        );
    }

    public function touchLastLogin(int $userId): void
    {
        $this->connection->execute(
            'UPDATE users SET last_login_at = :logged_in_at WHERE id = :id',
            [
                'logged_in_at' => date('Y-m-d H:i:s'),
                'id' => $userId,
            ]
        );
    }

    /**
     * List active staff members (levels 1..6) for task assignment selection.
     *
     * @return list<User>
     */
    public function listActiveStaff(): array
    {
        $rows = $this->connection->fetchAll(
            'SELECT u.*, d.slug AS department_slug
             FROM users u
             LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.is_active = 1 AND u.role_level >= :min_staff
             ORDER BY u.role_level ASC, u.full_name ASC',
            ['min_staff' => Role::Admin->value]
        );

        return array_map(
            static fn (array $row): User => User::fromRow($row),
            $rows
        );
    }
}
