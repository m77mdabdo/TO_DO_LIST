<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Auth\PdoLoginThrottle;
use App\Support\FrozenClock;
use Tests\Support\Database;
use Tests\TestCase;

final class PdoLoginThrottleTest extends TestCase
{
    private FrozenClock $clock;
    private PdoLoginThrottle $throttle;

    public function setUp(): void
    {
        $this->clock = new FrozenClock('2026-02-01 10:00:00');
        $this->throttle = new PdoLoginThrottle(Database::fresh(), $this->clock, maxAttempts: 3, lockSeconds: 600);
    }

    public function testLocksAfterTooManyFailures(): void
    {
        $this->fail(2);
        $this->assertFalse($this->throttle->tooManyAttempts('1.1.1.1'));

        $this->fail(1);
        $this->assertTrue($this->throttle->tooManyAttempts('1.1.1.1'));
        $this->assertFalse($this->throttle->tooManyAttempts('2.2.2.2'));
    }

    public function testTheLockWearsOff(): void
    {
        $this->fail(3);
        $this->clock->travel('+11 minutes');

        $this->assertFalse($this->throttle->tooManyAttempts('1.1.1.1'));
    }

    public function testASuccessfulLoginClearsTheCount(): void
    {
        $this->fail(3);
        $this->throttle->clear('1.1.1.1');

        $this->assertFalse($this->throttle->tooManyAttempts('1.1.1.1'));
    }

    private function fail(int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->throttle->recordFailure('1.1.1.1', 'sara@example.com');
        }
    }
}
