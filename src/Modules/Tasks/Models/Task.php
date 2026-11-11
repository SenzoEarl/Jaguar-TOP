<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Models;

use App\Support\Enums\Department;
use App\Support\Enums\TaskStatus;

/**
 * Domain entity representing an operational staff task.
 */
final class Task
{
    public function __construct(
        private readonly int $id,
        private readonly string $title,
        private readonly ?string $description,
        private readonly TaskStatus $status,
        private readonly string $priority,
        private readonly int $departmentId,
        private readonly Department $department,
        private readonly int $assignedToUserId,
        private readonly string $assignedToName,
        private readonly int $createdByUserId,
        private readonly ?string $dueDate,
        private readonly ?string $completedAt,
        private readonly string $createdAt
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['title'],
            isset($row['description']) && $row['description'] !== '' ? (string) $row['description'] : null,
            TaskStatus::from((string) $row['status']),
            (string) $row['priority'],
            (int) $row['department_id'],
            Department::from((string) $row['department_slug']),
            (int) $row['assigned_to_user_id'],
            (string) ($row['assigned_to_name'] ?? 'Staff Member'),
            (int) $row['created_by_user_id'],
            isset($row['due_date']) && $row['due_date'] !== '' ? (string) $row['due_date'] : null,
            isset($row['completed_at']) && $row['completed_at'] !== '' ? (string) $row['completed_at'] : null,
            (string) ($row['created_at'] ?? '')
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function getDepartmentId(): int
    {
        return $this->departmentId;
    }

    public function getDepartment(): Department
    {
        return $this->department;
    }

    public function getAssignedToUserId(): int
    {
        return $this->assignedToUserId;
    }

    public function getAssignedToName(): string
    {
        return $this->assignedToName;
    }

    public function getCreatedByUserId(): int
    {
        return $this->createdByUserId;
    }

    public function getDueDate(): ?string
    {
        return $this->dueDate;
    }

    public function getCompletedAt(): ?string
    {
        return $this->completedAt;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}
