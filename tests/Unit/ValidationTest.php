<?php

declare(strict_types=1);

namespace Tests\Unit;

use Classes\Validation\Validation;
use Tests\TestCase;

final class ValidationTest extends TestCase
{
    public function testRequiredFailsOnBlankText(): void
    {
        $v = (new Validation())->validate('title', '   ', ['Required']);

        $this->assertTrue($v->fails());
        $this->assertSame('Title is required.', $v->errors()['title']);
    }

    public function testLengthCountsCharactersNotBytes(): void
    {
        $v = (new Validation())->validate('title', 'مرحبا', ['Max:5']);

        $this->assertFalse($v->fails());
    }

    public function testEmptyOptionalFieldsSkipOtherRules(): void
    {
        $v = (new Validation())->validate('due_date', '', ['Date']);

        $this->assertFalse($v->fails());
    }

    public function testInAcceptsOnlyListedValues(): void
    {
        $v = (new Validation())->validate('priority', 'urgent', ['In:low,normal,high']);

        $this->assertTrue($v->fails());
    }
}
