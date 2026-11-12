<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Shared\Ids\Ulid;
use Tests\TestCase;

final class UlidTest extends TestCase
{
    public function testGeneratesValidUniqueTimeSortedIds(): void
    {
        $older = Ulid::generate(1_700_000_000_000);
        $newer = Ulid::generate(1_700_000_000_001);

        $this->assertTrue(Ulid::isValid($older));
        $this->assertSame(26, strlen($older));
        $this->assertTrue(strcmp($older, $newer) < 0);
        $this->assertTrue(Ulid::generate() !== Ulid::generate());
    }

    public function testRejectsAnythingElse(): void
    {
        $this->assertFalse(Ulid::isValid('1'));
        $this->assertFalse(Ulid::isValid(strtolower(Ulid::generate())));
        $this->assertFalse(Ulid::isValid(str_repeat('I', 26)));
    }
}
