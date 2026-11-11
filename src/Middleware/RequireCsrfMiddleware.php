<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\CsrfTokenException;

/**
 * Validates CSRF synchronizer tokens on every state-changing HTTP request.
 *
 * // [SECURITY:CSRF] Checks POST form field or X-CSRF-Token header using hash_equals().
 */
final class RequireCsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Csrf $csrf
    ) {
    }

    /**
     * @param callable(Request): Response $next
     * @param array<string, mixed> $options
     */
    public function handle(Request $request, callable $next, array $options = []): Response
    {
        if (!$request->isStateChanging()) {
            return $next($request);
        }

        $submitted = $request->post($this->csrf->fieldName());
        if (!is_string($submitted) || $submitted === '') {
            $submitted = $request->header($this->csrf->headerName());
        }

        if (!$this->csrf->validate(is_string($submitted) ? $submitted : null)) {
            throw new CsrfTokenException(
                'Your security token is missing or expired. Please reload the page and try again.',
                sprintf('CSRF validation failed for %s %s', $request->method(), $request->path())
            );
        }

        return $next($request);
    }
}
