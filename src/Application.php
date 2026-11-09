<?php

declare(strict_types=1);

namespace Framework;

use Framework\Container\Container;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Middleware\MiddlewareInterface;
use Framework\Middleware\Pipeline;
use Framework\Routing\MethodNotAllowedException;
use Framework\Routing\RouteMatch;
use Framework\Routing\RouteNotFoundException;
use Framework\Routing\Router;
use RuntimeException;
use Throwable;

/**
 * The HTTP kernel: matches the request to a route, runs global and
 * route middleware, calls the handler and turns its return value into
 * a Response.
 */
final class Application
{
    private Router $router;

    /** @var list<string|MiddlewareInterface> */
    private array $middleware = [];

    public function __construct(private Container $container, private bool $debug = false)
    {
        $this->router = new Router();
        $container->instance(Container::class, $container);
        $container->instance(Router::class, $this->router);
        $container->instance(self::class, $this);
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function router(): Router
    {
        return $this->router;
    }

    /**
     * Adds middleware that runs for every matched route.
     */
    public function pipe(string|MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    public function handle(Request $request): Response
    {
        try {
            $match = $this->router->match($request);
        } catch (RouteNotFoundException) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        } catch (MethodNotAllowedException $e) {
            return Response::html('<h1>405 Method Not Allowed</h1>', 405)
                ->withHeader('Allow', implode(', ', $e->allowed));
        }

        foreach ($match->params as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        $layers = array_map(
            fn (string|object $m): MiddlewareInterface => $this->resolveMiddleware($m),
            [...$this->middleware, ...$match->route->getMiddleware()],
        );

        $response = (new Pipeline($layers))->handle(
            $request,
            fn (Request $request): Response => $this->dispatch($match, $request),
        );

        // HEAD responses carry headers only.
        return $request->isMethod('HEAD') ? new Response('', $response->status(), $response->headers()) : $response;
    }

    /**
     * Like handle(), but turns uncaught exceptions into a 500 page.
     * Used by the front controller; tests call handle() directly.
     */
    public function run(Request $request): Response
    {
        try {
            return $this->handle($request);
        } catch (Throwable $e) {
            error_log((string) $e);
            $body = $this->debug
                ? '<h1>500 Server Error</h1><pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>'
                : '<h1>500 Server Error</h1>';

            return Response::html($body, 500);
        }
    }

    private function dispatch(RouteMatch $match, Request $request): Response
    {
        $result = $this->container->call(
            $match->route->handler(),
            [...$match->params, Request::class => $request],
        );

        return match (true) {
            $result instanceof Response => $result,
            is_string($result) => Response::html($result),
            is_array($result) => Response::json($result),
            $result === null => new Response('', 204),
            default => throw new RuntimeException('Route handlers must return a Response, string, array or null.'),
        };
    }

    private function resolveMiddleware(string|object $middleware): MiddlewareInterface
    {
        $instance = is_string($middleware) ? $this->container->get($middleware) : $middleware;

        if (!$instance instanceof MiddlewareInterface) {
            throw new RuntimeException(get_debug_type($instance) . ' does not implement ' . MiddlewareInterface::class);
        }

        return $instance;
    }
}
