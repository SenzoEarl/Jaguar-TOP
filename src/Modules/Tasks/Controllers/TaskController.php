<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Controllers;

use App\Core\AuthenticatedUserInterface;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\AuthenticationException;
use App\Exceptions\NotFoundException;
use App\Modules\Tasks\Services\TaskService;
use App\Support\Enums\Department;

/**
 * Handles staff task creation and status transitions (supports HTML forms & JSON ApiClient).
 */
final class TaskController
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly Session $session,
        private readonly Config $config
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params): Response
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof AuthenticatedUserInterface) {
            throw new AuthenticationException('Authentication required.');
        }

        $department = Department::tryFrom((string) ($params['section'] ?? ''));
        if ($department === null) {
            throw new NotFoundException('Department not found.');
        }

        $task = $this->taskService->createTask($user, $department, $request->allPost(), $request);
        $basePath = $this->config->string('app.base_path', '');

        if ($request->expectsJson()) {
            return Response::json([
                'ok' => true,
                'task' => [
                    'id' => $task->getId(),
                    'title' => $task->getTitle(),
                    'status' => $task->getStatus()->value,
                    'statusLabel' => $task->getStatus()->label(),
                    'statusBadge' => $task->getStatus()->badgeClass(),
                    'priority' => $task->getPriority(),
                    'assignedToName' => $task->getAssignedToName(),
                    'dueDate' => $task->getDueDate(),
                ],
            ], 201);
        }

        $this->session->flash('flash_success', 'Task created successfully.');
        return Response::redirect(app_url($department->dashboardPath(), $basePath));
    }

    /**
     * @param array<string, string> $params
     */
    public function updateStatus(Request $request, array $params): Response
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof AuthenticatedUserInterface) {
            throw new AuthenticationException('Authentication required.');
        }

        $taskId = (int) ($params['id'] ?? 0);
        $status = (string) $request->post('status', '');
        $task = $this->taskService->updateTaskStatus($user, $taskId, $status, $request);

        if ($request->expectsJson()) {
            return Response::json([
                'ok' => true,
                'task' => [
                    'id' => $task->getId(),
                    'status' => $task->getStatus()->value,
                    'statusLabel' => $task->getStatus()->label(),
                    'statusBadge' => $task->getStatus()->badgeClass(),
                ],
            ]);
        }

        $this->session->flash('flash_success', 'Task status updated.');
        return Response::redirect(
            app_url($task->getDepartment()->dashboardPath(), $this->config->string('app.base_path', ''))
        );
    }
}
