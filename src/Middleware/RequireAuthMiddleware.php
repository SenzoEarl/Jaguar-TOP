<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\AuthenticatedUserInterface;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\AuthenticationException;

/**
 * Enforces that an active, authenticated user is attached to the request.
 *
 * Unauthenticated browser requests redirect cleanly to `/account/login`;
 * JSON/AJAX callers receive HTTP 401 via Application's exception handler.
 */
final class RequireAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Session $session,
        private readonly Config $config
    ) {
    }

    /**
     * @param callable(Request): Response $next
     * @param array<string, mixed> $options
     */
    public function handle(Request $request, callable $next, array $options = []): Response
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof AuthenticatedUserInterface || !$user->isActive()) {
            if ($request->expectsJson()) {
                throw new AuthenticationException('Authentication is required for this endpoint.');
            }

            $this->session->flash('intended_url', $request->path());
            $this->session->flash('flash_warning', 'Please log in to continue.');

            return Response::redirect(
                app_url('/account/login', $this->config->string('app.base_path', ''))
            );
        }

        return $next($request);
    }
}
