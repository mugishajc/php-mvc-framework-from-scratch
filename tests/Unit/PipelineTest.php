<?php

declare(strict_types=1);

namespace Tests\Unit;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Middleware\MiddlewareInterface;
use Framework\Middleware\Pipeline;
use PHPUnit\Framework\TestCase;

final class PipelineTest extends TestCase
{
    private function tagger(string $tag): MiddlewareInterface
    {
        return new class ($tag) implements MiddlewareInterface {
            public function __construct(private string $tag)
            {
            }

            public function process(Request $request, callable $next): Response
            {
                $trail = $request->attribute('trail', '') . $this->tag . '>';
                $response = $next($request->withAttribute('trail', $trail));

                return new Response($response->content() . '<' . $this->tag, $response->status());
            }
        };
    }

    public function testMiddlewareRunsOutsideIn(): void
    {
        $pipeline = new Pipeline([$this->tagger('a'), $this->tagger('b')]);

        $response = $pipeline->handle(
            new Request('GET', '/'),
            static fn (Request $r) => new Response($r->attribute('trail') . 'core'),
        );

        $this->assertSame('a>b>core<b<a', $response->content());
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $blocker = new class implements MiddlewareInterface {
            public function process(Request $request, callable $next): Response
            {
                return new Response('blocked', 403);
            }
        };

        $reached = false;
        $response = (new Pipeline([$blocker]))->handle(new Request('GET', '/'), function () use (&$reached) {
            $reached = true;

            return new Response('core');
        });

        $this->assertSame(403, $response->status());
        $this->assertFalse($reached);
    }

    public function testEmptyPipelineCallsTheCore(): void
    {
        $response = (new Pipeline([]))->handle(new Request('GET', '/'), static fn () => new Response('core'));

        $this->assertSame('core', $response->content());
    }
}
