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
 * Route middleware enforcing explicit minimum role permission from `config/permissions.php`.
 */
final class RequireMinLevelMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AccessControlInterface $accessControl
    ) {
    }

    /**
     * @param callable(Request): Response $next
     * @param array<string, mixed> $options Expects `min_level` (Role)
     */
    public function handle(Request $request, callable $next, array $options = []): Response
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof AuthenticatedUserInterface) {
            throw new AuthenticationException('Authentication is required.');
        }

        $minRole = $options['min_level'] ?? null;
        if (!$minRole instanceof Role || !$this->accessControl->hasMinLevel($user, $minRole)) {
            throw new AuthorizationException(
                'You do not have sufficient permission to access this section.',
                sprintf('Min-level authorization denied on %s for user #%d', $request->path(), $user->getId())
            );
        }

        return $next($request);
    }
}
