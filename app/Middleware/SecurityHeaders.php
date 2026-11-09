<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Middleware\MiddlewareInterface;

final class SecurityHeaders implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        return $next($request)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'same-origin')
            ->withHeader('Content-Security-Policy', "default-src 'self'; style-src 'self'; form-action 'self'");
    }
}
