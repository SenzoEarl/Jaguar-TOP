<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\AppException;
use App\Exceptions\NotFoundException;
use App\Middleware\MiddlewareInterface;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use RuntimeException;

use function FastRoute\simpleDispatcher;

/**
 * HTTP Router wrapping nikic/fast-route with route-group prefixes and middleware pipelines.
 */
final class Router
{
    /**
     * @var list<array{
     *     method: string,
     *     path: string,
     *     handler: array{0: class-string, 1: string},
     *     middleware: list<
     *         class-string<MiddlewareInterface>|array{0: class-string<MiddlewareInterface>, 1: array<string, mixed>}
     *     >
     * }>
     */
    private array $routes = [];

    private string $groupPrefix = '';

    /**
     * @var list<
     *     class-string<MiddlewareInterface>|array{0: class-string<MiddlewareInterface>, 1: array<string, mixed>}
     * >
     */
    private array $groupMiddleware = [];

    public function __construct(
        private readonly Container $container
    ) {
    }

    /**
     * @param list<
     *     class-string<MiddlewareInterface>|array{0: class-string<MiddlewareInterface>, 1: array<string, mixed>}
     * > $middleware
     * @param array{0: class-string, 1: string} $handler
     */
    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * @param list<
     *     class-string<MiddlewareInterface>|array{0: class-string<MiddlewareInterface>, 1: array<string, mixed>}
     * > $middleware
     * @param array{0: class-string, 1: string} $handler
     */
    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * @param list<
     *     class-string<MiddlewareInterface>|array{0: class-string<MiddlewareInterface>, 1: array<string, mixed>}
     * > $middleware
     * @param array{0: class-string, 1: string} $handler
     */
    public function patch(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    /**
     * Register routes sharing a URL prefix and/or middleware stack.
     *
     * @param list<
     *     class-string<MiddlewareInterface>|array{0: class-string<MiddlewareInterface>, 1: array<string, mixed>}
     * > $middleware
     */
    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix = $previousPrefix . '/' . trim($prefix, '/');
        if ($this->groupPrefix === '/') {
            $this->groupPrefix = '';
        }
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        try {
            $callback($this);
        } finally {
            $this->groupPrefix = $previousPrefix;
            $this->groupMiddleware = $previousMiddleware;
        }
    }

    /**
     * Dispatch an incoming Request through FastRoute, route middleware, and controller.
     */
    public function dispatch(Request $request): Response
    {
        $dispatcher = simpleDispatcher(function (RouteCollector $collector): void {
            foreach ($this->routes as $route) {
                $collector->addRoute($route['method'], $route['path'], $route);
            }
        });

        $routeInfo = $dispatcher->dispatch($request->method(), $request->path());

        return match ($routeInfo[0]) {
            Dispatcher::NOT_FOUND => throw new NotFoundException(
                'The requested page could not be found.',
                sprintf('No route matched [%s %s]', $request->method(), $request->path())
            ),
            Dispatcher::METHOD_NOT_ALLOWED => throw new AppException(
                405,
                'This request method is not supported for this URL.',
                sprintf(
                    'Method %s not allowed for %s (allowed: %s)',
                    $request->method(),
                    $request->path(),
                    implode(', ', $routeInfo[1])
                ),
                'METHOD_NOT_ALLOWED'
            ),
            Dispatcher::FOUND => $this->runPipeline($request, $routeInfo[1], $routeInfo[2]),
            default => throw new RuntimeException('Unexpected FastRoute dispatcher result.'),
        };
    }

    /**
     * @param list<
     *     class-string<MiddlewareInterface>|array{0: class-string<MiddlewareInterface>, 1: array<string, mixed>}
     * > $middleware
     * @param array{0: class-string, 1: string} $handler
     */
    private function addRoute(string $method, string $path, array $handler, array $middleware): void
    {
        $combinedPath = rtrim($this->groupPrefix, '/') . '/' . ltrim($path, '/');
        if ($combinedPath !== '/') {
            $combinedPath = rtrim($combinedPath, '/');
        }
        $combinedPath = $combinedPath === '' ? '/' : $combinedPath;

        $this->routes[] = [
            'method' => $method,
            'path' => $combinedPath,
            'handler' => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    /**
     * @param array{
     *     handler: array{0: class-string, 1: string},
     *     middleware: list<
     *         class-string<MiddlewareInterface>|array{0: class-string<MiddlewareInterface>, 1: array<string, mixed>}
     *     >
     * } $route
     * @param array<string, string> $params
     */
    private function runPipeline(Request $request, array $route, array $params): Response
    {
        $enrichedRequest = $request->withAttribute('route_params', $params);

        $coreHandler = function (Request $req) use ($route, $params): Response {
            [$controllerClass, $action] = $route['handler'];
            $controller = $this->container->get($controllerClass);
            if (!method_exists($controller, $action)) {
                throw new RuntimeException(
                    sprintf('Controller method %s::%s does not exist.', $controllerClass, $action)
                );
            }

            $response = $controller->$action($req, $params);
            if (!$response instanceof Response) {
                throw new RuntimeException(
                    sprintf('%s::%s must return an App\Core\Response.', $controllerClass, $action)
                );
            }

            return $response;
        };

        $pipeline = array_reduce(
            array_reverse($route['middleware']),
            function (callable $next, string|array $spec): callable {
                return function (Request $req) use ($next, $spec): Response {
                    if (is_array($spec)) {
                        [$middlewareClass, $options] = $spec;
                    } else {
                        $middlewareClass = $spec;
                        $options = [];
                    }

                    $middleware = $this->container->get($middlewareClass);
                    if (!$middleware instanceof MiddlewareInterface) {
                        throw new RuntimeException($middlewareClass . ' must implement MiddlewareInterface.');
                    }

                    return $middleware->handle($req, $next, $options);
                };
            },
            $coreHandler
        );

        return $pipeline($enrichedRequest);
    }
}
