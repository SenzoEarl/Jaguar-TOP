<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\AccessControlInterface;
use App\Core\AuthenticatedUserInterface;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Support\Enums\Role;

/**
 * Route middleware requiring an exact Role (or one of an explicit list of Roles).
 */
final class RequireRoleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AccessControlInterface $accessControl
    ) {
    }

    /**
     * @param callable(Request): Response $next
     * @param array<string, mixed> $options Expects `role` (Role|list<Role>)
     */
    public function handle(Request $request, callable $next, array $options = []): Response
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof AuthenticatedUserInterface) {
            throw new AuthenticationException('Authentication is required.');
        }

        $target = $options['role'] ?? null;
        $allowedRoles = is_array($target) ? $target : ($target !== null ? [$target] : []);

        $authorized = false;
        foreach ($allowedRoles as $role) {
            if ($role instanceof Role && $this->accessControl->hasRole($user, $role)) {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            throw new AuthorizationException(
                'You do not have the required role to view this page.',
                sprintf('Role authorization denied on %s for user #%d', $request->path(), $user->getId())
            );
        }

        return $next($request);
    }
}
