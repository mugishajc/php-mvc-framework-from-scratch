<?php

declare(strict_types=1);

namespace Framework\View;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Renders plain PHP templates.
 *
 * Data passed to a template is escaped before the template sees it, so
 * `<?= $title ?>` is safe by default. Arrays are escaped recursively.
 * Wrap a value in HtmlString to opt out.
 */
final class View
{
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(private string $basePath)
    {
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $data = array_merge($this->shared, $data);
        $escaped = self::escapeData($data);

        $content = $this->evaluate($this->resolve($template), $escaped);

        if ($layout === null) {
            return $content;
        }

        return $this->evaluate($this->resolve($layout), ['content' => new HtmlString($content)] + $escaped);
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    public static function escapeData(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = match (true) {
                is_string($value) => self::escape($value),
                is_array($value) => self::escapeData($value),
                $value instanceof HtmlString => $value,
                $value instanceof \Stringable => self::escape($value),
                default => $value,
            };
        }

        return $data;
    }

    private function resolve(string $template): string
    {
        if (str_contains($template, '..') || str_starts_with($template, '/')) {
            throw new InvalidArgumentException("Invalid template name [$template].");
        }

        $path = rtrim($this->basePath, '/') . '/' . $template . '.php';
        if (!is_file($path)) {
            throw new RuntimeException("Template [$template] not found at $path.");
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function evaluate(string $path, array $vars): string
    {
        // A static closure keeps $this out of template scope.
        $render = static function (string $__path, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            $e = [View::class, 'escape'];

            ob_start();
            try {
                require $__path;
            } catch (Throwable $exception) {
                ob_end_clean();
                throw $exception;
            }

            return (string) ob_get_clean();
        };

        return $render($path, $vars);
    }
}
