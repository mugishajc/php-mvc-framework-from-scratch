<?php

declare(strict_types=1);

namespace Framework\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Wraps a core handler in layers of middleware. The first middleware in the
 * list is the outermost layer, so it sees the request first and the
 * response last.
 */
final class Pipeline
{
    /**
     * @param list<MiddlewareInterface> $middleware
     */
    public function __construct(private array $middleware)
    {
    }

    /**
     * @param callable(Request): Response $core
     */
    public function handle(Request $request, callable $core): Response
    {
        $next = $core;

        foreach (array_reverse($this->middleware) as $layer) {
            $next = static fn (Request $request): Response => $layer->process($request, $next);
        }

        return $next($request);
    }
}
