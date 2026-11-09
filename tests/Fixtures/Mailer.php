<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class Mailer
{
    public function __construct(public readonly Transport $transport)
    {
    }
}
