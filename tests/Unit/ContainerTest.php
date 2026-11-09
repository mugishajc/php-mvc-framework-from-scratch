<?php

declare(strict_types=1);

namespace Tests\Unit;

use Framework\Container\Container;
use Framework\Container\ContainerException;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\CircularA;
use Tests\Fixtures\Clock;
use Tests\Fixtures\FixedClock;
use Tests\Fixtures\Mailer;
use Tests\Fixtures\NeedsDsn;
use Tests\Fixtures\Newsletter;
use Tests\Fixtures\ShowTask;
use Tests\Fixtures\Transport;

final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testAutowiresNestedConstructorDependencies(): void
    {
        $newsletter = $this->container->get(Newsletter::class);

        $this->assertInstanceOf(Newsletter::class, $newsletter);
        $this->assertInstanceOf(Transport::class, $newsletter->mailer->transport);
        $this->assertSame('noreply@example.com', $newsletter->from);
    }

    public function testUnboundClassesAreBuiltFreshEachTime(): void
    {
        $this->assertNotSame($this->container->get(Mailer::class), $this->container->get(Mailer::class));
    }

    public function testSingletonsAreShared(): void
    {
        $this->container->singleton(Mailer::class);

        $this->assertSame($this->container->get(Mailer::class), $this->container->get(Mailer::class));
    }

    public function testInterfacesNeedABinding(): void
    {
        try {
            $this->container->get(Clock::class);
            $this->fail('Expected ContainerException');
        } catch (ContainerException) {
            // expected
        }

        $this->container->bind(Clock::class, FixedClock::class);

        $this->assertInstanceOf(FixedClock::class, $this->container->get(Clock::class));
    }

    public function testFactoryClosureReceivesTheContainer(): void
    {
        $this->container->instance('config.dsn', 'sqlite::memory:');
        $this->container->bind(NeedsDsn::class, static fn (Container $c) => new NeedsDsn($c->get('config.dsn')));

        $this->assertSame('sqlite::memory:', $this->container->get(NeedsDsn::class)->dsn);
    }

    public function testInstancesAreReturnedAsIs(): void
    {
        $transport = new Transport();
        $this->container->instance(Transport::class, $transport);

        $this->assertSame($transport, $this->container->get(Mailer::class)->transport);
    }

    public function testRebindingReplacesASharedInstance(): void
    {
        $this->container->singleton(Clock::class, FixedClock::class);
        $first = $this->container->get(Clock::class);

        $this->container->singleton(Clock::class, static fn () => new FixedClock());

        $this->assertNotSame($first, $this->container->get(Clock::class));
    }

    public function testDetectsCircularDependencies(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency');

        $this->container->get(CircularA::class);
    }

    public function testScalarWithoutDefaultCannotBeResolved(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('$dsn');

        $this->container->get(NeedsDsn::class);
    }

    public function testBuildAcceptsExplicitParameters(): void
    {
        $object = $this->container->build(NeedsDsn::class, ['dsn' => 'mysql:host=db']);

        $this->assertSame('mysql:host=db', $object->dsn);
    }

    public function testCallInjectsServicesAndCastsNamedParameters(): void
    {
        $result = $this->container->call(
            static fn (Mailer $mailer, int $id, string $slug = 'none') => [$mailer::class, $id, $slug],
            ['id' => '42'],
        );

        $this->assertSame([Mailer::class, 42, 'none'], $result);
    }

    public function testCallResolvesClassMethodPairs(): void
    {
        $result = $this->container->call([Newsletter::class, 'send'], ['issue' => '7']);

        $this->assertSame('issue 7 via ' . Transport::class, $result);
    }

    public function testCallSupportsInvokableClasses(): void
    {
        $this->assertSame('task 3', $this->container->call(ShowTask::class, ['id' => '3']));
    }

    public function testTypedParametersCanBeSuppliedByClassName(): void
    {
        $transport = new Transport();

        $result = $this->container->call(
            static fn (Transport $t) => $t,
            [Transport::class => $transport],
        );

        $this->assertSame($transport, $result);
    }
}
