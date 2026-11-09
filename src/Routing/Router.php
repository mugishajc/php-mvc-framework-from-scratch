<?php

declare(strict_types=1);

namespace Framework\Routing;

use Closure;
use Framework\Http\Request;
use InvalidArgumentException;

final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var list<array{prefix: string, middleware: list<string|object>}> */
    private array $groupStack = [];

    public function get(string $path, Closure|array|string $handler): Route
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, Closure|array|string $handler): Route
    {
        return $this->add('POST', $path, $handler);
    }

    public function put(string $path, Closure|array|string $handler): Route
    {
        return $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, Closure|array|string $handler): Route
    {
        return $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, Closure|array|string $handler): Route
    {
        return $this->add('DELETE', $path, $handler);
    }

    public function add(string $method, string $path, Closure|array|string $handler): Route
    {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], true)) {
            throw new InvalidArgumentException("Unsupported HTTP method [$method].");
        }

        $prefix = '';
        $middleware = [];
        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'];
            array_push($middleware, ...$group['middleware']);
        }

        $route = new Route($method, $prefix . '/' . ltrim($path, '/'), $handler);
        if ($middleware !== []) {
            $route->middleware(...$middleware);
        }

        $this->routes[] = $route;

        return $route;
    }

    /**
     * Registers routes that share a path prefix and/or middleware.
     *
     * @param array{prefix?: string, middleware?: list<string|object>} $attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $prefix = isset($attributes['prefix']) ? '/' . trim($attributes['prefix'], '/') : '';

        $this->groupStack[] = [
            'prefix' => $prefix === '/' ? '' : $prefix,
            'middleware' => $attributes['middleware'] ?? [],
        ];

        try {
            $callback($this);
        } finally {
            array_pop($this->groupStack);
        }
    }

    public function match(Request $request): RouteMatch
    {
        $allowed = [];
        $method = $request->method();

        foreach ($this->routes as $route) {
            $params = $route->matchPath($request->path());
            if ($params === null) {
                continue;
            }

            // HEAD is answered by the matching GET route.
            if ($route->method() === $method || ($method === 'HEAD' && $route->method() === 'GET')) {
                return new RouteMatch($route, $params);
            }

            $allowed[] = $route->method();
        }

        if ($allowed !== []) {
            throw new MethodNotAllowedException(array_values(array_unique($allowed)));
        }

        throw new RouteNotFoundException("No route for {$method} {$request->path()}");
    }

    /**
     * Builds a URL for a named route, filling in placeholders.
     *
     * @param array<string, string|int> $params
     */
    public function url(string $name, array $params = []): string
    {
        foreach ($this->routes as $route) {
            if ($route->getName() !== $name) {
                continue;
            }

            $url = preg_replace_callback('#\{(\w+)(?::' . Route::CONSTRAINT . ')?\}#', function (array $m) use ($params, $name): string {
                if (!array_key_exists($m[1], $params)) {
                    throw new InvalidArgumentException("Missing parameter [{$m[1]}] for route [$name].");
                }

                return rawurlencode((string) $params[$m[1]]);
            }, $route->path());

            return (string) $url;
        }

        throw new InvalidArgumentException("Route [$name] is not defined.");
    }

    /**
     * @return list<Route>
     */
    public function routes(): array
    {
        return $this->routes;
    }
}
