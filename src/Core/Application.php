<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\AppException;
use App\Exceptions\AuthenticationException;
use App\Exceptions\RateLimitException;
use App\Exceptions\ValidationException;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Logging\AuditLogger;
use App\Infrastructure\Logging\LoggerFactory;
use App\Infrastructure\Security\SecurityHeaders;
use App\Infrastructure\Storage\DepartmentFileStorage;
use App\Infrastructure\View\ViewFactory;
use App\Middleware\RequireAuthMiddleware;
use App\Middleware\RequireCsrfMiddleware;
use App\Middleware\RequireDepartmentMiddleware;
use App\Middleware\RequireGuestMiddleware;
use App\Middleware\RequireMinLevelMiddleware;
use App\Middleware\RequireRoleMiddleware;
use App\Support\Navigation\NavigationRegistry;
use ErrorException;
use Monolog\Logger;
use Throwable;

/**
 * Application front-controller kernel.
 *
 * Registers core infrastructure services, boots domain modules, enforces strict
 * PHP error-to-exception conversion, and catches all uncaught Throwables in one
 * centralized handler so neither stack traces nor SQL ever leak to clients.
 *
 * // [SECURITY:ERRORS] Central exception, error, and fatal shutdown handling.
 */
final class Application
{
    private readonly Config $config;
    private readonly Container $container;
    private readonly ModuleRegistry $modules;
    private ?Logger $logger = null;

    /**
     * @param list<ModuleInterface> $moduleList
     */
    public function __construct(
        string $rootPath,
        array $moduleList = [],
        bool $autoDetectWebMount = false
    ) {
        $config = Config::loadFromRoot($rootPath);
        if ($autoDetectWebMount && PHP_SAPI !== 'cli') {
            $detectedBase = Request::detectMountedBasePath();
            $detectedUrl = Request::detectCurrentAppUrl($detectedBase);
            $config = $config->withWebRuntimeDefaults($detectedBase, $detectedUrl);
        }
        $this->config = $config;
        date_default_timezone_set($this->config->string('app.timezone', 'UTC'));

        $this->container = new Container();
        $this->modules = new ModuleRegistry();

        foreach ($moduleList as $module) {
            $this->modules->register($module);
        }

        $this->configureErrorHandling();
        $this->registerCoreServices();
        $this->modules->bootServices($this->container);
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function config(): Config
    {
        return $this->config;
    }

    /**
     * Execute the full HTTP request lifecycle and return a hardened Response.
     */
    public function handle(Request $request): Response
    {
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        $session->start();

        /** @var SecurityHeaders $securityHeaders */
        $securityHeaders = $this->container->get(SecurityHeaders::class);

        if ($this->container->has(CurrentUserProviderInterface::class)) {
            /** @var CurrentUserProviderInterface $userProvider */
            $userProvider = $this->container->get(CurrentUserProviderInterface::class);
            $request = $request->withAttribute('user', $userProvider->currentUser());
        }

        try {
            $router = new Router($this->container);
            $this->modules->bootRoutes($router);

            $response = $router->dispatch($request);
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        }

        return $securityHeaders->apply($request, $response);
    }

    /**
     * Map any exception to a logged diagnostic record and a safe client response.
     *
     * // [SECURITY:ERRORS] Differentiates HTML error views from JSON API errors.
     */
    private function handleException(Request $request, Throwable $exception): Response
    {
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        $basePath = $this->config->string('app.base_path', '');

        if ($exception instanceof ValidationException) {
            if ($request->expectsJson()) {
                return Response::json([
                    'ok' => false,
                    'error' => $exception->getPublicMessage(),
                    'code' => $exception->getErrorCode(),
                    'errors' => $exception->getErrors(),
                ], 422);
            }

            $session->flash('validation_errors', $exception->getErrors());
            $session->flash('flash_error', $exception->getPublicMessage());
            $session->flashOldInput($request->allPost());

            $referer = $request->header('referer');
            $target = $this->safeBackTarget($referer, $basePath);
            return Response::redirect($target);
        }

        if ($exception instanceof AuthenticationException && !$request->expectsJson()) {
            $session->flash('intended_url', $request->path());
            $session->flash('flash_warning', $exception->getPublicMessage());
            return Response::redirect(app_url('/account/login', $basePath));
        }

        $statusCode = $exception instanceof AppException ? $exception->getHttpStatus() : 500;
        $publicMessage = $exception instanceof AppException
            ? $exception->getPublicMessage()
            : 'An unexpected server error occurred. Please try again later.';
        $errorCode = $exception instanceof AppException ? $exception->getErrorCode() : 'INTERNAL_ERROR';

        $this->logException($request, $exception, $statusCode);

        $headers = [];
        if ($exception instanceof RateLimitException) {
            $headers['Retry-After'] = (string) $exception->getRetryAfterSeconds();
        }

        if ($request->expectsJson()) {
            return Response::json([
                'ok' => false,
                'error' => $publicMessage,
                'code' => $errorCode,
                'requestId' => $request->requestId(),
            ], $statusCode, $headers);
        }

        return $this->renderErrorPage($statusCode, $publicMessage, $request->requestId(), $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    private function renderErrorPage(
        int $statusCode,
        string $publicMessage,
        string $requestId,
        array $headers = []
    ): Response {
        $templateCode = in_array($statusCode, [403, 404, 419, 429, 500], true) ? $statusCode : 500;

        try {
            /** @var ViewFactory $view */
            $view = $this->container->get(ViewFactory::class);
            $html = $view->render('errors.' . $templateCode, [
                'pageTitle' => sprintf('%d Error', $statusCode),
                'statusCode' => $statusCode,
                'message' => $publicMessage,
                'requestId' => $requestId,
            ]);
            return Response::html($html, $statusCode, $headers);
        } catch (Throwable $renderFailure) {
            $this->logger?->critical('Fallback error view failed to render.', [
                'exception' => $renderFailure::class,
                'message' => $renderFailure->getMessage(),
                'request_id' => $requestId,
            ]);

            $fallbackHtml = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>'
                . e((string) $statusCode) . '</title></head><body><h1>Error '
                . e((string) $statusCode) . '</h1><p>' . e($publicMessage) . '</p></body></html>';

            return Response::html($fallbackHtml, $statusCode, $headers);
        }
    }

    private function logException(Request $request, Throwable $exception, int $statusCode): void
    {
        if ($this->logger === null) {
            return;
        }

        $user = $request->getAttribute('user');
        $userId = $user instanceof AuthenticatedUserInterface ? $user->getId() : null;

        $context = [
            'request_id' => $request->requestId(),
            'user_id' => $userId,
            'method' => $request->method(),
            'route' => $request->path(),
            'ip' => $request->ip(),
            'status' => $statusCode,
            'exception_class' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        if ($exception instanceof AppException && $exception->getContext() !== []) {
            $context['app_context'] = $exception->getContext();
        }

        if ($statusCode >= 500) {
            $context['trace'] = $exception->getTraceAsString();
            $this->logger->error($exception->getMessage(), $context);
        } elseif ($statusCode >= 400) {
            $this->logger->warning($exception->getMessage(), $context);
        }
    }

    private function configureErrorHandling(): void
    {
        // [SECURITY:ERRORS] Never display raw PHP errors or stack traces in HTTP output.
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        error_reporting(E_ALL);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error === null) {
                return;
            }

            $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (!in_array($error['type'], $fatalTypes, true)) {
                return;
            }

            $this->logger?->critical('Fatal PHP shutdown error', [
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
            ]);

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=UTF-8');
                echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>500 Server Error</title></head>'
                    . '<body><h1>500 — Unexpected Server Error</h1>'
                    . '<p>An unexpected error occurred. Our team has been notified.</p></body></html>';
            }
        });
    }

    private function registerCoreServices(): void
    {
        $this->container->instance(Config::class, $this->config);
        $this->container->instance(ModuleRegistry::class, $this->modules);

        $this->container->singleton(Logger::class, function (): Logger {
            $logger = (new LoggerFactory($this->config))->create();
            $this->logger = $logger;
            return $logger;
        });

        // Eagerly instantiate Monolog logger so shutdown handler always has a sink.
        $this->logger = $this->container->get(Logger::class);

        $this->container->singleton(Session::class, fn (): Session => Session::fromConfig($this->config));
        $this->container->singleton(Csrf::class, fn (Container $c): Csrf => new Csrf(
            $c->get(Session::class),
            $this->config->string('security.csrf.token_name', '_csrf_token'),
            $this->config->string('security.csrf.header_name', 'X-CSRF-Token')
        ));
        $this->container->singleton(Validator::class, static fn (): Validator => new Validator());
        $this->container->singleton(Connection::class, fn (): Connection => Connection::fromConfig($this->config));
        $this->container->singleton(AuditLogger::class, fn (Container $c): AuditLogger => new AuditLogger(
            $c->get(Connection::class),
            $c->get(Logger::class)
        ));
        $this->container->singleton(
            SecurityHeaders::class,
            fn (): SecurityHeaders => new SecurityHeaders($this->config)
        );
        $this->container->singleton(
            DepartmentFileStorage::class,
            fn (): DepartmentFileStorage => DepartmentFileStorage::fromConfig($this->config)
        );
        $this->container->singleton(
            NavigationRegistry::class,
            fn (Container $c): NavigationRegistry => new NavigationRegistry(
                $this->config,
                $c->get(AccessControlInterface::class)
            )
        );
        $this->container->singleton(ViewFactory::class, fn (Container $c): ViewFactory => new ViewFactory(
            $this->config,
            $c->get(Session::class),
            $c->get(Csrf::class),
            $c->get(AccessControlInterface::class),
            $c->has(CurrentUserProviderInterface::class) ? $c->get(CurrentUserProviderInterface::class) : null
        ));

        // Core Middleware registrations
        $this->container->singleton(
            RequireAuthMiddleware::class,
            fn (Container $c): RequireAuthMiddleware => new RequireAuthMiddleware(
                $c->get(Session::class),
                $c->get(Config::class)
            )
        );
        $this->container->singleton(
            RequireGuestMiddleware::class,
            fn (Container $c): RequireGuestMiddleware => new RequireGuestMiddleware(
                $c->get(Session::class),
                $c->get(AccessControlInterface::class),
                $c->get(Config::class)
            )
        );
        $this->container->singleton(
            RequireCsrfMiddleware::class,
            fn (Container $c): RequireCsrfMiddleware => new RequireCsrfMiddleware(
                $c->get(Csrf::class)
            )
        );
        $this->container->singleton(
            RequireRoleMiddleware::class,
            fn (Container $c): RequireRoleMiddleware => new RequireRoleMiddleware(
                $c->get(AccessControlInterface::class)
            )
        );
        $this->container->singleton(
            RequireMinLevelMiddleware::class,
            fn (Container $c): RequireMinLevelMiddleware => new RequireMinLevelMiddleware(
                $c->get(AccessControlInterface::class)
            )
        );
        $this->container->singleton(
            RequireDepartmentMiddleware::class,
            fn (Container $c): RequireDepartmentMiddleware => new RequireDepartmentMiddleware(
                $c->get(AccessControlInterface::class)
            )
        );
    }

    /**
     * Prevent open-redirect abuse when bouncing back after validation failure.
     */
    private function safeBackTarget(?string $referer, string $basePath): string
    {
        if ($referer === null || $referer === '') {
            return app_url('/', $basePath);
        }

        $parsed = parse_url($referer);
        if (!is_array($parsed)) {
            return app_url('/', $basePath);
        }

        $appHost = parse_url($this->config->string('app.url', ''), PHP_URL_HOST);
        if (isset($parsed['host']) && is_string($appHost) && strcasecmp($parsed['host'], $appHost) !== 0) {
            return app_url('/', $basePath);
        }

        $path = (string) ($parsed['path'] ?? '/');
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        return $path . $query;
    }
}
