<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\AccessControlInterface;
use App\Core\AuthenticatedUserInterface;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Redirects already-authenticated users away from guest-only pages (login/register).
 */
final class RequireGuestMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Session $session,
        private readonly AccessControlInterface $accessControl,
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
        if ($user instanceof AuthenticatedUserInterface && $user->isActive()) {
            $landing = $this->accessControl->defaultLandingPath($user);
            return Response::redirect(
                app_url($landing, $this->config->string('app.base_path', ''))
            );
        }

        return $next($request);
    }
}
