<?php

declare(strict_types=1);

namespace Tests\Unit;

use Framework\Http\Request;
use Framework\Routing\MethodNotAllowedException;
use Framework\Routing\RouteNotFoundException;
use Framework\Routing\Router;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function testMatchesAStaticRoute(): void
    {
        $route = $this->router->get('/tasks', static fn () => 'ok');

        $match = $this->router->match(new Request('GET', '/tasks'));

        $this->assertSame($route, $match->route);
        $this->assertSame([], $match->params);
    }

    public function testMatchesTheRootPath(): void
    {
        $route = $this->router->get('/', static fn () => 'home');

        $this->assertSame($route, $this->router->match(new Request('GET', '/'))->route);
    }

    public function testIgnoresTrailingSlashAndQueryString(): void
    {
        $route = $this->router->get('/tasks', static fn () => 'ok');

        $match = $this->router->match(new Request('GET', '/tasks/?page=2'));

        $this->assertSame($route, $match->route);
    }

    public function testCapturesNamedParameters(): void
    {
        $this->router->get('/users/{user}/posts/{post}', static fn () => 'ok');

        $match = $this->router->match(new Request('GET', '/users/jc/posts/42'));

        $this->assertSame(['user' => 'jc', 'post' => '42'], $match->params);
    }

    public function testParameterConstraintIsEnforced(): void
    {
        $this->router->get('/tasks/{id:\d+}', static fn () => 'ok');

        $this->assertSame(['id' => '7'], $this->router->match(new Request('GET', '/tasks/7'))->params);

        $this->expectException(RouteNotFoundException::class);
        $this->router->match(new Request('GET', '/tasks/seven'));
    }

    public function testUnconstrainedParameterDoesNotSpanSegments(): void
    {
        $this->router->get('/files/{name}', static fn () => 'ok');

        $this->expectException(RouteNotFoundException::class);
        $this->router->match(new Request('GET', '/files/a/b'));
    }

    public function testStaticSegmentsAreMatchedLiterally(): void
    {
        $this->router->get('/files/report.pdf', static fn () => 'ok');

        $this->expectException(RouteNotFoundException::class);
        $this->router->match(new Request('GET', '/files/reportXpdf'));
    }

    public function testFirstMatchingRouteWins(): void
    {
        $first = $this->router->get('/tasks/new', static fn () => 'form');
        $this->router->get('/tasks/{id}', static fn () => 'show');

        $this->assertSame($first, $this->router->match(new Request('GET', '/tasks/new'))->route);
    }

    public function testWrongMethodReportsAllowedMethods(): void
    {
        $this->router->get('/tasks/{id}', static fn () => 'show');
        $this->router->delete('/tasks/{id}', static fn () => 'delete');

        try {
            $this->router->match(new Request('POST', '/tasks/1'));
            $this->fail('Expected MethodNotAllowedException');
        } catch (MethodNotAllowedException $e) {
            $this->assertSame(['GET', 'DELETE'], $e->allowed);
        }
    }

    public function testHeadRequestsUseTheGetRoute(): void
    {
        $route = $this->router->get('/tasks', static fn () => 'ok');

        $this->assertSame($route, $this->router->match(new Request('HEAD', '/tasks'))->route);
    }

    public function testGroupsApplyPrefixAndMiddleware(): void
    {
        $this->router->group(['prefix' => 'admin', 'middleware' => ['auth']], function (Router $r): void {
            $r->get('/users', static fn () => 'users');

            $r->group(['prefix' => '/reports', 'middleware' => ['audit']], function (Router $r): void {
                $r->get('/{year:\d{4}}', static fn () => 'report')->middleware('cache');
            });
        });
        $this->router->get('/public', static fn () => 'public');

        $users = $this->router->match(new Request('GET', '/admin/users'))->route;
        $report = $this->router->match(new Request('GET', '/admin/reports/2026'));
        $public = $this->router->match(new Request('GET', '/public'))->route;

        $this->assertSame('/admin/users', $users->path());
        $this->assertSame(['auth'], $users->getMiddleware());
        $this->assertSame(['year' => '2026'], $report->params);
        $this->assertSame(['auth', 'audit', 'cache'], $report->route->getMiddleware());
        $this->assertSame([], $public->getMiddleware());
    }

    public function testBuildsUrlsForNamedRoutes(): void
    {
        $this->router->get('/tasks/{id:\d+}/edit', static fn () => 'edit')->name('tasks.edit');

        $this->assertSame('/tasks/5/edit', $this->router->url('tasks.edit', ['id' => 5]));
    }

    public function testUrlGenerationRequiresAllParameters(): void
    {
        $this->router->get('/tasks/{id}', static fn () => 'show')->name('tasks.show');

        $this->expectException(InvalidArgumentException::class);
        $this->router->url('tasks.show');
    }

    public function testUnknownPathThrowsNotFound(): void
    {
        $this->expectException(RouteNotFoundException::class);
        $this->router->match(new Request('GET', '/nope'));
    }
}
