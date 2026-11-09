<?php

declare(strict_types=1);

namespace Framework\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Session\CsrfTokenManager;

/**
 * Rejects state-changing requests that don't carry the session's CSRF
 * token, either as a `_token` form field or an X-CSRF-Token header.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private CsrfTokenManager $tokens)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if (!in_array($request->method(), self::SAFE_METHODS, true)) {
            $sent = $request->input('_token') ?? $request->header('X-CSRF-Token');

            if (!$this->tokens->isValid($sent)) {
                return Response::html('<h1>419 Page Expired</h1><p>Invalid or missing CSRF token.</p>', 419);
            }
        }

        return $next($request);
    }
}
