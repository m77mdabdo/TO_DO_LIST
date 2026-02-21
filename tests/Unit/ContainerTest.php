<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Container;
use App\Support\FrozenClock;
use InvalidArgumentException;
use stdClass;
use Tests\TestCase;

final class ContainerTest extends TestCase
{
    public function testBuildsServicesOnceAndSharesThem(): void
    {
        $container = new Container();
        $built = 0;
        $container->set('thing', function () use (&$built) {
            $built++;
            return new stdClass();
        });

        $this->assertTrue($container->get('thing') === $container->get('thing'));
        $this->assertSame(1, $built);
    }

    public function testFactoriesCanUseOtherServices(): void
    {
        $container = new Container();
        $container->instance('now', '2026-02-01 09:00');
        $container->set(FrozenClock::class, fn (Container $c) => new FrozenClock($c->get('now')));

        $this->assertSame('2026-02-01', $container->get(FrozenClock::class)->now()->format('Y-m-d'));
    }

    public function testOverridingAServiceReplacesTheSharedInstance(): void
    {
        $container = new Container();
        $container->set('name', fn () => 'first');
        $container->get('name');
        $container->set('name', fn () => 'second');

        $this->assertSame('second', $container->get('name'));
    }

    public function testUnknownServicesFailLoudly(): void
    {
        $this->assertThrows(InvalidArgumentException::class, fn () => (new Container())->get('missing'));
    }
}
