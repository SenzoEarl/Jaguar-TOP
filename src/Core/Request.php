<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable HTTP Request abstraction.
 *
 * This is the ONLY class in the application permitted to read PHP superglobals
 * ($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER) and `php://input`.
 */
final class Request
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $headers
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly string $rawPath,
        private readonly array $query,
        private readonly array $post,
        private readonly array $headers,
        private readonly array $cookies,
        private readonly array $files,
        private readonly array $server,
        private readonly string $requestId
    ) {
    }

    /**
     * Construct a Request instance from PHP's incoming SAPI superglobals.
     */
    public static function fromGlobals(string $basePath = ''): self
    {
        // phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable
        $server = $_SERVER;
        $query = $_GET;
        $post = $_POST;
        $cookies = $_COOKIE;
        $files = $_FILES;
        // phpcs:enable

        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $requestUri = (string) ($server['REQUEST_URI'] ?? '/');
        $rawPath = (string) (parse_url($requestUri, PHP_URL_PATH) ?: '/');
        $rawPath = '/' . ltrim($rawPath, '/');

        $normalizedBase = rtrim($basePath, '/');
        $path = $rawPath;
        if ($normalizedBase !== '' && str_starts_with($rawPath, $normalizedBase)) {
            $stripped = substr($rawPath, strlen($normalizedBase));
            $path = $stripped === '' ? '/' : '/' . ltrim($stripped, '/');
        }

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $headers = self::extractHeaders($server);

        $contentType = strtolower($headers['content-type'] ?? '');
        if ($post === [] && !in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $rawBody = file_get_contents('php://input');
            if (is_string($rawBody) && trim($rawBody) !== '') {
                if (str_starts_with($contentType, 'application/json')) {
                    $decoded = json_decode($rawBody, true);
                    if (is_array($decoded)) {
                        $post = $decoded;
                    }
                } elseif (str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
                    $parsed = [];
                    parse_str($rawBody, $parsed);
                    $post = $parsed;
                }
            }
        }

        $requestId = $headers['x-request-id'] ?? bin2hex(random_bytes(8));

        return new self(
            $method,
            $path,
            $rawPath,
            $query,
            $post,
            $headers,
            $cookies,
            $files,
            $server,
            $requestId
        );
    }

    /**
     * Auto-detect subdirectory mount path from web SAPI `SCRIPT_NAME` and `REQUEST_URI`.
     *
     * Supports root vhost (`''`), direct `/.../public` subdirectory, and parent
     * subdirectory forwarding via `.htaccess` (e.g. `/TOP`).
     *
     * @param array<string, mixed>|null $serverOverride
     */
    public static function detectMountedBasePath(?array $serverOverride = null): string
    {
        // phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable
        $server = $serverOverride ?? $_SERVER;
        // phpcs:enable

        $scriptName = str_replace('\\', '/', (string) ($server['SCRIPT_NAME'] ?? '/index.php'));
        $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($scriptDir === '' || $scriptDir === '.') {
            return '';
        }

        $requestUri = (string) ($server['REQUEST_URI'] ?? '/');
        $rawPath = (string) (parse_url($requestUri, PHP_URL_PATH) ?: '/');
        $rawPath = '/' . ltrim($rawPath, '/');

        if ($rawPath === $scriptDir || str_starts_with($rawPath, $scriptDir . '/')) {
            return $scriptDir;
        }

        if (str_ends_with($scriptDir, '/public')) {
            $parentPrefix = substr($scriptDir, 0, -strlen('/public'));
            if (
                $parentPrefix !== ''
                && ($rawPath === $parentPrefix || str_starts_with($rawPath, $parentPrefix . '/'))
            ) {
                return $parentPrefix;
            }
        }

        return $scriptDir;
    }

    /**
     * Infer base application URL from validated web host and detected mount path.
     *
     * @param array<string, mixed>|null $serverOverride
     */
    public static function detectCurrentAppUrl(
        string $basePath = '',
        ?array $serverOverride = null
    ): ?string {
        // phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable
        $server = $serverOverride ?? $_SERVER;
        // phpcs:enable

        $rawHost = trim((string) ($server['HTTP_HOST'] ?? ($server['SERVER_NAME'] ?? '')));
        if ($rawHost === '' || preg_match('/^[a-zA-Z0-9.\-:\[\]]+$/', $rawHost) !== 1) {
            return null;
        }

        $httpsRaw = strtolower((string) ($server['HTTPS'] ?? ''));
        $scheme = ($httpsRaw !== '' && $httpsRaw !== 'off') ? 'https' : 'http';
        $normalizedBase = $basePath === '' ? '' : '/' . trim($basePath, '/');

        return $scheme . '://' . $rawHost . $normalizedBase;
    }

    /**
     * Deterministic factory for unit and integration tests without touching globals.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $headers
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public static function createForTest(
        string $method = 'GET',
        string $path = '/',
        array $query = [],
        array $post = [],
        array $headers = [],
        array $cookies = [],
        array $files = [],
        array $server = []
    ): self {
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $normalizedHeaders[strtolower($key)] = $value;
        }

        $cleanPath = '/' . ltrim($path, '/');
        if ($cleanPath !== '/' && str_ends_with($cleanPath, '/')) {
            $cleanPath = rtrim($cleanPath, '/');
        }

        return new self(
            strtoupper($method),
            $cleanPath,
            $cleanPath,
            $query,
            $post,
            $normalizedHeaders,
            $cookies,
            $files,
            array_merge(['REMOTE_ADDR' => '127.0.0.1'], $server),
            'test-' . bin2hex(random_bytes(4))
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isStateChanging(): bool
    {
        return in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function rawPath(): string
    {
        return $this->rawPath;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function allQuery(): array
    {
        return $this->query;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function allPost(): array
    {
        return $this->post;
    }

    /**
     * Read from POST payload first, falling back to query parameters.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }

        return $this->query[$key] ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $normalized = strtolower($name);
        return $this->headers[$normalized] ?? $default;
    }

    public function cookie(string $name, ?string $default = null): ?string
    {
        $value = $this->cookies[$name] ?? null;
        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Retrieve a single uploaded file metadata array from $_FILES shape.
     *
     * @return array<string, mixed>|null
     */
    public function file(string $key): ?array
    {
        $entry = $this->files[$key] ?? null;
        return is_array($entry) ? $entry : null;
    }

    public function ip(): string
    {
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '127.0.0.1');
        return filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : '127.0.0.1';
    }

    public function userAgent(): string
    {
        $agent = $this->header('user-agent', '');
        return mb_substr((string) $agent, 0, 255);
    }

    public function expectsJson(): bool
    {
        $accept = strtolower((string) $this->header('accept', ''));
        $requestedWith = strtolower((string) $this->header('x-requested-with', ''));

        return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;
        return $clone;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Extract normalized lowercase HTTP headers from $_SERVER.
     *
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function extractHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headerName = strtolower(str_replace('_', '-', $key));
                $headers[$headerName] = (string) $value;
            }
        }

        return $headers;
    }
}
