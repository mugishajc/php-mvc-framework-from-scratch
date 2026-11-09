<?php

declare(strict_types=1);

namespace Framework\Session;

final class CsrfTokenManager
{
    private const SESSION_KEY = '_csrf_token';

    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function isValid(mixed $token): bool
    {
        // hash_equals keeps the comparison constant-time.
        return is_string($token) && $token !== '' && hash_equals($this->token(), $token);
    }
}
