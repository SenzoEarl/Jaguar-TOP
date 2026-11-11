<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;

/**
 * Applies defensive HTTP security headers centrally on every outgoing response.
 *
 * // [SECURITY:XSS] Enforces Content-Security-Policy, X-Content-Type-Options,
 * // X-Frame-Options, Referrer-Policy, Permissions-Policy, and staff noindex tags.
 */
final class SecurityHeaders
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function apply(Request $request, Response $response): Response
    {
        $headers = [
            'Content-Security-Policy' => $this->config->string('security.headers.csp_policy'),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => $this->config->string(
                'security.headers.referrer_policy',
                'strict-origin-when-cross-origin'
            ),
            'Permissions-Policy' => $this->config->string(
                'security.headers.permissions_policy',
                'camera=(), microphone=()'
            ),
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'X-Request-Id' => $request->requestId(),
        ];

        // Prevent search engines from indexing internal staff or customer account routes.
        $path = $request->path();
        if (str_starts_with($path, '/staff') || str_starts_with($path, '/account')) {
            $headers['X-Robots-Tag'] = 'noindex, nofollow';
        }

        return $response->withHeaders($headers);
    }
}
