<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\AccessControlInterface;
use App\Core\AuthenticatedUserInterface;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\NotFoundException;
use App\Support\Enums\Department;

/**
 * Route middleware validating read or write permission for a target Department.
 *
 * Respects both higher-level visibility and the Level-1 cross-department read rule
 * centrally defined in `config/permissions.php` via AccessControl.
 */
final class RequireDepartmentMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AccessControlInterface $accessControl
    ) {
    }

    /**
     * @param callable(Request): Response $next
     * @param array<string, mixed> $options Supports `department` (Department|string) and `mode` ('read'|'write'|'auto')
     */
    public function handle(Request $request, callable $next, array $options = []): Response
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof AuthenticatedUserInterface) {
            throw new AuthenticationException('Authentication is required.');
        }

        $department = $this->resolveDepartment($request, $options);
        if ($department === null) {
            throw new NotFoundException('The requested department section does not exist.');
        }

        $mode = (string) ($options['mode'] ?? 'read');
        if ($mode === 'auto') {
            $mode = $request->isStateChanging() ? 'write' : 'read';
        }

        $allowed = $mode === 'write'
            ? $this->accessControl->canWriteDepartment($user, $department)
            : $this->accessControl->canViewDepartment($user, $department);

        if (!$allowed) {
            throw new AuthorizationException(
                'You do not have permission for this department workspace.',
                sprintf(
                    'Department %s (%s) denied on %s for user #%d',
                    $department->value,
                    $mode,
                    $request->path(),
                    $user->getId()
                )
            );
        }

        return $next($request->withAttribute('department', $department));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveDepartment(Request $request, array $options): ?Department
    {
        $explicit = $options['department'] ?? null;
        if ($explicit instanceof Department) {
            return $explicit;
        }
        if (is_string($explicit) && $explicit !== '') {
            return Department::tryFrom($explicit);
        }

        /** @var array<string, string> $routeParams */
        $routeParams = $request->getAttribute('route_params', []);
        $slug = $routeParams['department'] ?? $routeParams['section'] ?? '';
        if (!is_string($slug) || $slug === '') {
            return null;
        }

        return Department::tryFrom($slug);
    }
}
