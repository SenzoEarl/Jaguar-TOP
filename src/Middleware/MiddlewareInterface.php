<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Contract for HTTP route middleware components.
 */
interface MiddlewareInterface
{
    /**
     * @param callable(Request): Response $next
     * @param array<string, mixed> $options
     */
    public function handle(Request $request, callable $next, array $options = []): Response;
}
