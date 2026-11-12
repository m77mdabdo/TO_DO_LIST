<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Shared\Ordering\FractionalIndex as F;
use InvalidArgumentException;
use Tests\TestCase;

final class FractionalIndexTest extends TestCase
{
    public function testKeysLandBetweenTheirNeighbours(): void
    {
        $first = F::between(null, null);
        $later = F::between($first, null);
        $earlier = F::between(null, $first);
        $middle = F::between($first, $later);

        $this->assertTrue(strcmp($earlier, $first) < 0);
        $this->assertTrue(strcmp($first, $middle) < 0);
        $this->assertTrue(strcmp($middle, $later) < 0);
    }

    public function testRandomInsertsKeepEverythingSortedAndUnique(): void
    {
        mt_srand(42);
        $keys = [F::between(null, null)];

        for ($i = 0; $i < 2000; $i++) {
            $at = mt_rand(0, count($keys));
            $key = F::between($keys[$at - 1] ?? null, $keys[$at] ?? null);
            array_splice($keys, $at, 0, [$key]);
        }

        $sorted = $keys;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $keys);
        $this->assertCount(2001, array_unique($keys));
    }

    public function testAppendingStaysShort(): void
    {
        $key = null;
        for ($i = 0; $i < 1000; $i++) {
            $key = F::between($key, null);
        }

        $this->assertTrue(strlen($key) < F::NEEDS_REBALANCE, 'Appending 1000 items gave a key of ' . strlen($key) . ' chars.');
    }

    public function testSpreadGivesShortSortedKeys(): void
    {
        $keys = F::spread(5000);
        $sorted = $keys;
        sort($sorted, SORT_STRING);

        $this->assertSame($sorted, $keys);
        $this->assertCount(5000, array_unique($keys));
        $this->assertTrue(max(array_map('strlen', $keys)) <= 3);
        $this->assertTrue(F::between(null, $keys[0]) < $keys[0]);
    }

    public function testRejectsBadKeys(): void
    {
        $this->assertThrows(InvalidArgumentException::class, fn () => F::between('b', 'a'));
        $this->assertThrows(InvalidArgumentException::class, fn () => F::between('a0', null));
        $this->assertThrows(InvalidArgumentException::class, fn () => F::between('a-b', null));
    }
}
