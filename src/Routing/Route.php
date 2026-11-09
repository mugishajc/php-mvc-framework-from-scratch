<?php

declare(strict_types=1);

namespace Framework\Routing;

use Closure;
use Framework\Http\Request;

final class Route
{
    private string $regex;

    /** @var list<string|object> */
    private array $middleware = [];

    private ?string $name = null;

    /**
     * @param Closure|array{0: class-string, 1: string}|string $handler
     */
    public function __construct(
        private string $method,
        private string $path,
        private Closure|array|string $handler,
    ) {
        $this->path = Request::normalizePath($path);
        $this->regex = self::compile($this->path);
    }

    /**
     * Turns "/tasks/{id:\d+}" into "#^/tasks/(?P<id>\d+)$#". Placeholders
     * without a constraint match a single path segment.
     */
    public static function compile(string $path): string
    {
        $parts = preg_split('#(\{\w+(?::[^}]+)?\})#', $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $regex = '';

        foreach ($parts ?: [] as $part) {
            if (preg_match('#^\{(\w+)(?::([^}]+))?\}$#', $part, $m) === 1) {
                $regex .= '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }

        return '#^' . $regex . '$#';
    }

    /**
     * Returns the captured parameters, or null when the path does not match.
     *
     * @return array<string, string>|null
     */
    public function matchPath(string $path): ?array
    {
        if (preg_match($this->regex, $path, $matches) !== 1) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return Closure|array{0: class-string, 1: string}|string
     */
    public function handler(): Closure|array|string
    {
        return $this->handler;
    }

    public function middleware(string|object ...$middleware): self
    {
        array_push($this->middleware, ...$middleware);

        return $this;
    }

    /**
     * @return list<string|object>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
