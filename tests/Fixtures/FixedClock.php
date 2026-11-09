<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class FixedClock implements Clock
{
    public function now(): string
    {
        return '2026-01-01 00:00:00';
    }
}
