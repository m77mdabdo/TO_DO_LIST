<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Container;
use App\Support\Clock;
use App\Support\FrozenClock;
use App\Support\Greeting;
use RuntimeException;
use Tests\TestCase;

final class ContainerTest extends TestCase
{
    public function testAutowiresConstructorDependencies(): void
    {
        $container = new Container();
        $container->singleton(Clock::class, fn () => new FrozenClock('2026-01-01 09:00'));

        $greeting = $container->get(Greeting::class);

        $this->assertSame('Good morning', $greeting->forNow());
    }

    public function testSingletonsAreShared(): void
    {
        $container = new Container();
        $container->singleton(Clock::class, fn () => new FrozenClock());

        $this->assertSame($container->get(Clock::class), $container->get(Clock::class));
    }

    public function testExtendersRunOnceWhenTheServiceIsBuilt(): void
    {
        $container = new Container();
        $calls = 0;
        $container->singleton(Clock::class, fn () => new FrozenClock());
        $container->extend(Clock::class, function (object $clock) use (&$calls): void { $calls++; });

        $container->get(Clock::class);
        $container->get(Clock::class);

        $this->assertSame(1, $calls);
    }

    public function testInterfacesNeedABinding(): void
    {
        $this->assertThrows(RuntimeException::class, fn () => (new Container())->get(Clock::class));
    }
}
