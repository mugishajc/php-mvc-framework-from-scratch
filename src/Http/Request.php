<?php

declare(strict_types=1);

namespace Framework\Http;

/**
 * Immutable HTTP request. Built from the PHP superglobals in production
 * and constructed directly in tests.
 */
final class Request
{
    private string $method;
    private string $path;

    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     */
    public function __construct(
        string $method,
        string $uri,
        private array $query = [],
        private array $body = [],
        array $headers = [],
        private array $cookies = [],
    ) {
        $this->method = strtoupper($method);
        $this->path = self::normalizePath((string) parse_url($uri, PHP_URL_PATH));

        foreach ($headers as $name => $value) {
            $this->headers[strtolower($name)] = $value;
        }
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // HTML forms can only send GET and POST, so allow a hidden _method field.
        $override = strtoupper((string) ($_POST['_method'] ?? ''));
        if ($method === 'POST' && in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
            $method = $override;
        }

        return new self(
            $method,
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            $_GET,
            $_POST,
            $headers,
            $_COOKIE,
        );
    }

    public static function normalizePath(string $path): string
    {
        return '/' . trim($path, '/');
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function cookie(string $name, ?string $default = null): ?string
    {
        return $this->cookies[$name] ?? $default;
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }
}
