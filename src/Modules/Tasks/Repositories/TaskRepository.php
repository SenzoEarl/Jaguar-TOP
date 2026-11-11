<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Repositories;

use App\Infrastructure\Database\Connection;
use App\Modules\Tasks\Models\Task;
use App\Support\Enums\Department;
use App\Support\Enums\TaskStatus;

/**
 * PDO repository for staff department tasks.
 */
final class TaskRepository
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * @return list<Task>
     */
    public function findByDepartment(
        Department $department,
        ?int $assignedToUserId = null,
        int $limit = 15
    ): array {
        $sql = 'SELECT t.*, d.slug AS department_slug, u.full_name AS assigned_to_name
                FROM tasks t
                INNER JOIN departments d ON d.id = t.department_id
                INNER JOIN users u ON u.id = t.assigned_to_user_id
                WHERE d.slug = :dept_slug';
        $params = ['dept_slug' => $department->value];

        if ($assignedToUserId !== null) {
            $sql .= ' AND t.assigned_to_user_id = :assigned_user_id';
            $params['assigned_user_id'] = $assignedToUserId;
        }

        $sql .= " ORDER BY FIELD(t.status, 'in_progress', 'todo', 'blocked', 'done'),
                  t.due_date IS NULL ASC, t.due_date ASC, t.id DESC
                  LIMIT :limit";
        $params['limit'] = max(1, $limit);

        $rows = $this->connection->fetchAll($sql, $params);
        return array_map(
            static fn (array $row): Task => Task::fromRow($row),
            $rows
        );
    }

    public function findById(int $id): ?Task
    {
        $row = $this->connection->fetchOne(
            'SELECT t.*, d.slug AS department_slug, u.full_name AS assigned_to_name
             FROM tasks t
             INNER JOIN departments d ON d.id = t.department_id
             INNER JOIN users u ON u.id = t.assigned_to_user_id
             WHERE t.id = :id
             LIMIT 1',
            ['id' => $id]
        );

        return $row !== null ? Task::fromRow($row) : null;
    }

    public function resolveDepartmentId(Department $department): ?int
    {
        $row = $this->connection->fetchOne(
            'SELECT id FROM departments WHERE slug = :slug LIMIT 1',
            ['slug' => $department->value]
        );

        return $row !== null ? (int) $row['id'] : null;
    }

    public function create(
        string $title,
        ?string $description,
        string $priority,
        int $departmentId,
        int $assignedToUserId,
        int $createdByUserId,
        ?string $dueDate
    ): Task {
        $this->connection->execute(
            'INSERT INTO tasks (
                title,
                description,
                status,
                priority,
                department_id,
                assigned_to_user_id,
                created_by_user_id,
                due_date
            ) VALUES (
                :title,
                :description,
                :status,
                :priority,
                :department_id,
                :assigned_to_user_id,
                :created_by_user_id,
                :due_date
            )',
            [
                'title' => $title,
                'description' => $description,
                'status' => TaskStatus::Todo->value,
                'priority' => $priority,
                'department_id' => $departmentId,
                'assigned_to_user_id' => $assignedToUserId,
                'created_by_user_id' => $createdByUserId,
                'due_date' => $dueDate,
            ]
        );

        $id = $this->connection->lastInsertId();
        $created = $this->findById($id);
        if ($created === null) {
            throw new \RuntimeException('Created task could not be reloaded.');
        }

        return $created;
    }

    public function updateStatus(int $taskId, TaskStatus $status): void
    {
        $completedAt = $status === TaskStatus::Done ? date('Y-m-d H:i:s') : null;

        $this->connection->execute(
            'UPDATE tasks
             SET status = :status,
                 completed_at = :completed_at
             WHERE id = :id',
            [
                'status' => $status->value,
                'completed_at' => $completedAt,
                'id' => $taskId,
            ]
        );
    }

    public function countOpenByDepartment(Department $department): int
    {
        $row = $this->connection->fetchOne(
            'SELECT COUNT(*) AS total
             FROM tasks t
             INNER JOIN departments d ON d.id = t.department_id
             WHERE d.slug = :slug AND t.status != :done_status',
            [
                'slug' => $department->value,
                'done_status' => TaskStatus::Done->value,
            ]
        );

        return (int) ($row['total'] ?? 0);
    }
}
