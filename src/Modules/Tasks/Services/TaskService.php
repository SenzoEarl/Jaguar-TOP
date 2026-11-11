<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Services;

use App\Core\AccessControlInterface;
use App\Core\AuthenticatedUserInterface;
use App\Core\Config;
use App\Core\Request;
use App\Core\Validator;
use App\Exceptions\AuthorizationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Infrastructure\Logging\AuditLogger;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Repositories\TaskRepository;
use App\Support\Enums\Department;
use App\Support\Enums\TaskStatus;

/**
 * Staff Task business service enforcing department-level read/write RBAC.
 */
final class TaskService
{
    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly UserRepository $users,
        private readonly AccessControlInterface $accessControl,
        private readonly Validator $validator,
        private readonly AuditLogger $auditLogger,
        private readonly Config $config
    ) {
    }

    /**
     * @return list<Task>
     */
    public function listDepartmentTasks(
        AuthenticatedUserInterface $actor,
        Department $department,
        ?int $onlyAssignedToUserId = null
    ): array {
        if (!$this->accessControl->canViewDepartment($actor, $department)) {
            throw new AuthorizationException('You do not have permission to view tasks for this department.');
        }

        $limit = max(1, $this->config->int('app.staff_tasks_per_page', 15));
        return $this->tasks->findByDepartment($department, $onlyAssignedToUserId, $limit);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createTask(
        AuthenticatedUserInterface $actor,
        Department $department,
        array $input,
        Request $request
    ): Task {
        if (!$this->accessControl->canWriteDepartment($actor, $department)) {
            throw new AuthorizationException(
                'This workspace is read-only for your role. Task creation is restricted to your authorized department.'
            );
        }

        $data = $this->validator->validate($input, [
            'title' => 'required|string|min:3|max:160',
            'description' => 'nullable|string|max:2000',
            'priority' => 'required|in:low,medium,high,urgent',
            'assigned_to_user_id' => 'required|int|min:1',
            'due_date' => 'nullable|date',
        ]);

        $assignee = $this->users->findById((int) $data['assigned_to_user_id']);
        if ($assignee === null || !$assignee->isActive() || !$assignee->getRole()->isStaff()) {
            throw new ValidationException([
                'assigned_to_user_id' => ['Tasks can only be assigned to active staff members.'],
            ]);
        }

        $departmentId = $this->tasks->resolveDepartmentId($department);
        if ($departmentId === null) {
            throw new NotFoundException('Target department could not be resolved.');
        }

        $task = $this->tasks->create(
            (string) $data['title'],
            isset($data['description']) && $data['description'] !== '' ? (string) $data['description'] : null,
            (string) $data['priority'],
            $departmentId,
            $assignee->getId(),
            $actor->getId(),
            isset($data['due_date']) && $data['due_date'] !== '' ? (string) $data['due_date'] : null
        );

        $this->auditLogger->record(
            'task.created',
            $actor->getId(),
            'tasks',
            $task->getId(),
            $request,
            ['department' => $department->value, 'assigned_to' => $assignee->getId()]
        );

        return $task;
    }

    public function updateTaskStatus(
        AuthenticatedUserInterface $actor,
        int $taskId,
        string $rawStatus,
        Request $request
    ): Task {
        $status = TaskStatus::tryFrom(trim($rawStatus));
        if ($status === null) {
            throw new ValidationException([
                'status' => ['Invalid task status selected.'],
            ]);
        }

        $task = $this->tasks->findById($taskId);
        if ($task === null) {
            throw new NotFoundException('Task not found.');
        }

        if (!$this->accessControl->canWriteDepartment($actor, $task->getDepartment())) {
            throw new AuthorizationException(
                'You have read-only access to this department and cannot change task statuses.'
            );
        }

        $this->tasks->updateStatus($task->getId(), $status);
        $updated = $this->tasks->findById($task->getId()) ?? $task;

        $this->auditLogger->record(
            'task.status_updated',
            $actor->getId(),
            'tasks',
            $task->getId(),
            $request,
            ['department' => $task->getDepartment()->value, 'status' => $status->value]
        );

        return $updated;
    }
}
