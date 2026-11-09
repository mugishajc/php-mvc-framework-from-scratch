<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class Newsletter
{
    public function __construct(
        public readonly Mailer $mailer,
        public readonly string $from = 'noreply@example.com',
    ) {
    }

    public function send(Transport $transport, int $issue): string
    {
        return "issue $issue via " . $transport::class;
    }
}
