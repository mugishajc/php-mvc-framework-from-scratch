<?php

declare(strict_types=1);

namespace Tests\Unit;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Middleware\CsrfMiddleware;
use Framework\Session\CsrfTokenManager;
use Framework\Session\Session;
use PHPUnit\Framework\TestCase;

final class CsrfMiddlewareTest extends TestCase
{
    private CsrfTokenManager $tokens;
    private CsrfMiddleware $middleware;

    protected function setUp(): void
    {
        $this->tokens = new CsrfTokenManager(new Session());
        $this->middleware = new CsrfMiddleware($this->tokens);
    }

    private function handle(Request $request): Response
    {
        return $this->middleware->process($request, static fn () => new Response('passed'));
    }

    public function testTokenIsStableWithinASession(): void
    {
        $this->assertSame(64, strlen($this->tokens->token()));
        $this->assertSame($this->tokens->token(), $this->tokens->token());
    }

    public function testSafeMethodsPassWithoutAToken(): void
    {
        $this->assertSame('passed', $this->handle(new Request('GET', '/tasks'))->content());
    }

    public function testPostWithoutTokenIsRejected(): void
    {
        $this->assertSame(419, $this->handle(new Request('POST', '/tasks', [], ['title' => 'x']))->status());
    }

    public function testPostWithWrongTokenIsRejected(): void
    {
        $this->tokens->token();
        $request = new Request('DELETE', '/tasks/1', [], ['_token' => str_repeat('a', 64)]);

        $this->assertSame(419, $this->handle($request)->status());
    }

    public function testPostWithFormTokenPasses(): void
    {
        $request = new Request('POST', '/tasks', [], ['_token' => $this->tokens->token()]);

        $this->assertSame('passed', $this->handle($request)->content());
    }

    public function testTokenCanBeSentAsAHeader(): void
    {
        $request = new Request('PUT', '/tasks/1', [], [], ['X-CSRF-Token' => $this->tokens->token()]);

        $this->assertSame('passed', $this->handle($request)->content());
    }

    public function testNonStringTokenIsRejected(): void
    {
        $request = new Request('POST', '/tasks', [], ['_token' => [$this->tokens->token()]]);

        $this->assertSame(419, $this->handle($request)->status());
    }
}
