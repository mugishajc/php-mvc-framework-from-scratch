<?php

declare(strict_types=1);

namespace Framework\View;

use Stringable;

/**
 * Marks a string as already-safe HTML so the view layer won't escape it.
 * Only wrap content you produced yourself.
 */
final class HtmlString implements Stringable
{
    public function __construct(private string $html)
    {
    }

    public function __toString(): string
    {
        return $this->html;
    }
}
