<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Support\FrozenClock;
use App\Support\StreamLogger;
use RuntimeException;
use Tests\TestCase;

final class LoggingAndConfigTest extends TestCase
{
    public function testStreamLoggerWritesOneEntryPerError(): void
    {
        $stream = fopen('php://memory', 'w+b');
        (new StreamLogger(new FrozenClock('2026-01-20 10:00:00'), $stream))->error(new RuntimeException('Disk full'));

        rewind($stream);
        $out = (string) stream_get_contents($stream);
        $this->assertTrue(str_starts_with($out, '[2026-01-20 10:00:00] RuntimeException: Disk full in '));
    }

    /** Regression: APP_AUTO_MIGRATE=0 used to read as "unset" and leave migrations on. */
    public function testYesNoSettingsAcceptZeroAndFalse(): void
    {
        $this->withEnv(['APP_AUTO_MIGRATE' => '0', 'APP_DEBUG' => '1'], function (): void {
            $config = Config::load(BASE_PATH);
            $this->assertFalse($config->get('auto_migrate'));
            $this->assertTrue($config->get('debug'));
        });

        $this->withEnv(['APP_AUTO_MIGRATE' => 'false'], fn () => $this->assertFalse(Config::load(BASE_PATH)->get('auto_migrate')));
        $this->withEnv(['APP_AUTO_MIGRATE' => ''], fn () => $this->assertTrue(Config::load(BASE_PATH)->get('auto_migrate')));
    }

    public function testLogChannel(): void
    {
        $this->withEnv(['LOG_CHANNEL' => 'stderr'], fn () => $this->assertSame('stderr', Config::load(BASE_PATH)->get('log')));
        $this->withEnv(['LOG_CHANNEL' => 'anything else'], fn () => $this->assertSame('file', Config::load(BASE_PATH)->get('log')));
    }

    /** @param array<string, string> $vars */
    private function withEnv(array $vars, callable $test): void
    {
        $previous = [];
        foreach ($vars as $name => $value) {
            $previous[$name] = getenv($name);
            putenv("$name=$value");
        }

        try {
            $test();
        } finally {
            foreach ($previous as $name => $value) {
                putenv($value === false ? $name : "$name=$value");
            }
        }
    }
}
