<?php

declare(strict_types=1);

namespace App\Core;

use App\Support\Enums\Department;
use App\Support\Enums\Role;

/**
 * Minimal identity contract consumed by Middleware, ViewFactory, and AccessControl.
 *
 * Isolates Core/Infrastructure/Middleware from the concrete Auth User model.
 */
interface AuthenticatedUserInterface
{
    public function getId(): int;

    public function getUsername(): string;

    public function getEmail(): string;

    public function getFullName(): string;

    public function getRole(): Role;

    public function getDepartment(): ?Department;

    public function getDepartmentId(): ?int;

    public function isActive(): bool;
}
