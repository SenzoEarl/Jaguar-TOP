<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Hardened HTTP Session manager.
 *
 * // [SECURITY:SESSION] Enforces strict mode, HttpOnly, Secure, SameSite,
 * // session fixation defense via regeneration on auth/privilege changes,
 * // idle & absolute timeouts, and complete cookie/store destruction on logout.
 */
final class Session
{
    private const KEY_CREATED_AT = '_session_created_at';
    private const KEY_LAST_ACTIVITY = '_session_last_activity';
    private const KEY_FLASH = '_flash';
    private const KEY_OLD = '_old_input';

    private bool $started = false;

    /** @var array<string, mixed> Used strictly when running in in-memory test mode */
    private array $memoryStore = [];

    public function __construct(
        private readonly string $sessionName,
        private readonly string $savePath,
        private readonly int $idleLifetime,
        private readonly int $absoluteLifetime,
        private readonly bool $cookieSecure,
        private readonly bool $cookieHttpOnly,
        private readonly string $cookieSameSite,
        private readonly string $cookiePath = '/',
        private readonly string $cookieDomain = '',
        private readonly bool $useMemoryDriver = false
    ) {
    }

    /**
     * Build Session configured from `config/session.php`.
     */
    public static function fromConfig(Config $config): self
    {
        $rawPath = $config->string('session.save_path', 'storage/sessions');
        $resolvedPath = $config->resolvePath($rawPath);

        return new self(
            $config->string('session.name', 'jaguar_safari_sid'),
            $resolvedPath,
            max(60, $config->int('session.idle_lifetime', 1800)),
            max(300, $config->int('session.absolute_lifetime', 28800)),
            $config->bool('session.cookie_secure', false),
            $config->bool('session.cookie_httponly', true),
            $config->string('session.cookie_samesite', 'Lax'),
            $config->string('session.cookie_path', '/'),
            $config->string('session.cookie_domain', '')
        );
    }

    /**
     * Deterministic non-SAPI session store for PHPUnit unit tests.
     */
    public static function createInMemory(int $idleLifetime = 1800, int $absoluteLifetime = 28800): self
    {
        $session = new self(
            'test_sid',
            '',
            $idleLifetime,
            $absoluteLifetime,
            false,
            true,
            'Lax',
            '/',
            '',
            true
        );
        $session->start();
        return $session;
    }

    /**
     * Initialize the session and validate idle/absolute lifetimes.
     *
     * // [SECURITY:SESSION] Strict cookie flags and automatic expiry enforcement.
     */
    public function start(?int $currentTime = null): void
    {
        $now = $currentTime ?? time();

        if ($this->useMemoryDriver) {
            if (!$this->started) {
                $this->started = true;
                $this->memoryStore[self::KEY_CREATED_AT] = $now;
                $this->memoryStore[self::KEY_LAST_ACTIVITY] = $now;
                return;
            }
            $this->enforceTimeouts($now);
            return;
        }

        if ($this->started && session_status() === PHP_SESSION_ACTIVE) {
            $this->enforceTimeouts($now);
            return;
        }

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            if (!is_dir($this->savePath)) {
                mkdir($this->savePath, 0750, true);
            }

            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_trans_sid', '0');
            ini_set('session.cookie_httponly', $this->cookieHttpOnly ? '1' : '0');
            ini_set('session.cookie_secure', $this->cookieSecure ? '1' : '0');
            ini_set('session.cookie_samesite', $this->normalizedSameSite());
            ini_set('session.gc_maxlifetime', (string) max($this->idleLifetime, $this->absoluteLifetime));

            session_save_path($this->savePath);
            session_name($this->sessionName);
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => $this->cookiePath,
                'domain' => $this->cookieDomain,
                'secure' => $this->cookieSecure,
                'httponly' => $this->cookieHttpOnly,
                'samesite' => $this->normalizedSameSite(),
            ]);

            session_start();
        }

        $this->started = true;
        $this->enforceTimeouts($now);
    }

    /**
     * Regenerate session ID and reset session timestamps (call on login/privilege elevation).
     *
     * // [SECURITY:SESSION] Prevents session fixation attacks.
     */
    public function regenerate(): void
    {
        $now = time();
        if ($this->useMemoryDriver) {
            $this->memoryStore[self::KEY_CREATED_AT] = $now;
            $this->memoryStore[self::KEY_LAST_ACTIVITY] = $now;
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION[self::KEY_CREATED_AT] = $now;
        $_SESSION[self::KEY_LAST_ACTIVITY] = $now;
    }

    /**
     * Destroy session storage and clear the client cookie securely on logout.
     *
     * // [SECURITY:SESSION] Complete server + client session teardown.
     */
    public function destroy(): void
    {
        if ($this->useMemoryDriver) {
            $this->memoryStore = [];
            $this->started = false;
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            if (!headers_sent() && ini_get('session.use_cookies')) {
                setcookie(
                    $this->sessionName,
                    '',
                    [
                        'expires' => time() - 42000,
                        'path' => $this->cookiePath,
                        'domain' => $this->cookieDomain,
                        'secure' => $this->cookieSecure,
                        'httponly' => $this->cookieHttpOnly,
                        'samesite' => $this->normalizedSameSite(),
                    ]
                );
            }

            session_destroy();
        }

        $this->started = false;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $store = &$this->store();
        return $store[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $store = &$this->store();
        $store[$key] = $value;
    }

    public function has(string $key): bool
    {
        $store = &$this->store();
        return array_key_exists($key, $store);
    }

    public function remove(string $key): void
    {
        $store = &$this->store();
        unset($store[$key]);
    }

    public function flash(string $key, mixed $value): void
    {
        $store = &$this->store();
        if (!isset($store[self::KEY_FLASH]) || !is_array($store[self::KEY_FLASH])) {
            $store[self::KEY_FLASH] = [];
        }
        $store[self::KEY_FLASH][$key] = $value;
    }

    public function pullFlash(string $key, mixed $default = null): mixed
    {
        $store = &$this->store();
        $flash = $store[self::KEY_FLASH] ?? [];
        if (!is_array($flash) || !array_key_exists($key, $flash)) {
            return $default;
        }

        $value = $flash[$key];
        unset($store[self::KEY_FLASH][$key]);
        return $value;
    }

    /**
     * Store sanitized form values (excluding sensitive secrets) across redirect.
     *
     * @param array<string, mixed> $input
     */
    public function flashOldInput(array $input): void
    {
        $filtered = [];
        foreach ($input as $key => $value) {
            $lower = strtolower((string) $key);
            if (str_contains($lower, 'password') || str_contains($lower, 'csrf')) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $filtered[(string) $key] = (string) ($value ?? '');
            }
        }

        $this->flash(self::KEY_OLD, $filtered);
    }

    /**
     * Retrieve and clear previously flashed form input.
     *
     * @return array<string, string>
     */
    public function pullOldInput(): array
    {
        $old = $this->pullFlash(self::KEY_OLD, []);
        return is_array($old) ? $old : [];
    }

    private function enforceTimeouts(int $now): void
    {
        $store = &$this->store();
        $createdAt = isset($store[self::KEY_CREATED_AT]) ? (int) $store[self::KEY_CREATED_AT] : $now;
        $lastActivity = isset($store[self::KEY_LAST_ACTIVITY]) ? (int) $store[self::KEY_LAST_ACTIVITY] : $now;

        $idleExpired = ($now - $lastActivity) > $this->idleLifetime;
        $absoluteExpired = ($now - $createdAt) > $this->absoluteLifetime;

        if ($idleExpired || $absoluteExpired) {
            if ($this->useMemoryDriver) {
                $this->memoryStore = [
                    self::KEY_CREATED_AT => $now,
                    self::KEY_LAST_ACTIVITY => $now,
                ];
                return;
            }

            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
                session_regenerate_id(true);
            }
            $_SESSION[self::KEY_CREATED_AT] = $now;
            $_SESSION[self::KEY_LAST_ACTIVITY] = $now;
            return;
        }

        if (!isset($store[self::KEY_CREATED_AT])) {
            $store[self::KEY_CREATED_AT] = $now;
        }
        $store[self::KEY_LAST_ACTIVITY] = $now;
    }

    private function normalizedSameSite(): string
    {
        return match (ucfirst(strtolower($this->cookieSameSite))) {
            'Strict' => 'Strict',
            'None' => 'None',
            default => 'Lax',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function &store(): array
    {
        if ($this->useMemoryDriver) {
            return $this->memoryStore;
        }

        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        return $_SESSION;
    }
}
