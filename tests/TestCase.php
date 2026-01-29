<?php

declare(strict_types=1);

namespace Tests;

/*
 | A tiny base class for the test suite, so the project keeps zero dependencies.
 | Every public method whose name starts with "test" is run by tests/run.php.
 */
abstract class TestCase
{
    private int $assertions = 0;

    public function setUp(): void
    {
    }

    public function assertionCount(): int
    {
        return $this->assertions;
    }

    protected function assertTrue(mixed $value, string $message = 'Expected true.'): void
    {
        $this->assertions++;

        if ($value !== true) {
            throw new AssertionFailed($message);
        }
    }

    protected function assertFalse(mixed $value, string $message = 'Expected false.'): void
    {
        $this->assertTrue($value === false, $message);
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertTrue(
            $expected === $actual,
            $message ?: sprintf('Expected %s, got %s.', $this->export($expected), $this->export($actual))
        );
    }

    protected function assertCount(int $expected, array $items): void
    {
        $this->assertSame($expected, count($items), sprintf('Expected %d items, got %d.', $expected, count($items)));
    }

    protected function assertNull(mixed $value): void
    {
        $this->assertSame(null, $value);
    }

    protected function assertArrayHasKey(string|int $key, array $array): void
    {
        $this->assertTrue(array_key_exists($key, $array), "Missing key [$key].");
    }

    protected function assertThrows(string $exception, callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            $this->assertTrue($e instanceof $exception, sprintf('Expected %s, got %s.', $exception, $e::class));
            return;
        }

        throw new AssertionFailed("Expected $exception to be thrown.");
    }

    private function export(mixed $value): string
    {
        return var_export($value, true);
    }
}
