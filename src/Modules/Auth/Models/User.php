<?php

declare(strict_types=1);

namespace App\Modules\Auth\Models;

use App\Core\AuthenticatedUserInterface;
use App\Support\Enums\Department;
use App\Support\Enums\Role;

/**
 * Authenticated User domain entity hydrated from MariaDB `users` & `departments`.
 */
final class User implements AuthenticatedUserInterface
{
    public function __construct(
        private readonly int $id,
        private readonly string $username,
        private readonly string $email,
        private readonly string $fullName,
        private readonly string $passwordHash,
        private readonly Role $role,
        private readonly ?Department $department,
        private readonly ?int $departmentId,
        private readonly bool $isActive,
        private readonly ?string $lastLoginAt,
        private readonly string $createdAt
    ) {
    }

    /**
     * Hydrate a User entity from a PDO associative row joined with `departments`.
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $role = Role::from((int) $row['role_level']);
        $deptSlug = isset($row['department_slug']) && is_string($row['department_slug'])
            ? $row['department_slug']
            : null;

        return new self(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['email'],
            (string) $row['full_name'],
            (string) $row['password_hash'],
            $role,
            $deptSlug !== null ? Department::tryFrom($deptSlug) : null,
            isset($row['department_id']) ? (int) $row['department_id'] : null,
            (bool) $row['is_active'],
            isset($row['last_login_at']) ? (string) $row['last_login_at'] : null,
            (string) ($row['created_at'] ?? '')
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function getDepartment(): ?Department
    {
        return $this->department;
    }

    public function getDepartmentId(): ?int
    {
        return $this->departmentId;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getLastLoginAt(): ?string
    {
        return $this->lastLoginAt;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}
