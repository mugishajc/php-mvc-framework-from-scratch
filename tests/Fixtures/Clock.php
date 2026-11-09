<?php

declare(strict_types=1);

namespace Tests\Fixtures;

interface Clock
{
    public function now(): string;
}
