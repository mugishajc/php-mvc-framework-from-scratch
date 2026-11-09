<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class NeedsDsn
{
    public function __construct(public readonly string $dsn)
    {
    }
}
