<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class ShowTask
{
    public function __invoke(Mailer $mailer, int $id): string
    {
        return "task $id";
    }
}
