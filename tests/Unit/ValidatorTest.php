<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validation\Rule;
use App\Validation\Validator;
use InvalidArgumentException;
use Tests\TestCase;

final class ValidatorTest extends TestCase
{
    public function testReportsTheFirstFailingRulePerField(): void
    {
        $result = (new Validator())->validate(
            ['title' => '1', 'email' => 'nope'],
            ['title' => ['Title', 'required|text|min:2'], 'email' => ['Email', 'required|email']],
        );

        $this->assertSame([
            'title' => 'Title must be text, not just a number.',
            'email' => 'Email must be a valid email address.',
        ], $result->errors());
    }

    public function testMissingAndArrayValuesCountAsEmpty(): void
    {
        $result = (new Validator())->validate(['title' => ['x']], [
            'title' => ['Title', 'required'],
            'notes' => ['Notes', 'max:5'],
        ]);

        $this->assertSame(['title' => 'Title is required.'], $result->errors());
    }

    public function testArabicTextCountsByCharacter(): void
    {
        $result = (new Validator())->validate(['name' => 'محمد'], ['name' => ['Name', 'min:4|max:4']]);

        $this->assertTrue($result->passes());
    }

    public function testNewRulesPlugInWithoutChangingTheValidator(): void
    {
        $validator = (new Validator())->extend('lowercase', new class implements Rule {
            public function check(string $label, mixed $value, array $params): ?string
            {
                return $value === mb_strtolower((string) $value) ? null : "$label must be lowercase.";
            }
        });

        $this->assertTrue($validator->validate(['tag' => 'Work'], ['tag' => ['Tag', 'lowercase']])->fails());
    }

    public function testUnknownRulesAreAProgrammingError(): void
    {
        $this->assertThrows(
            InvalidArgumentException::class,
            fn () => (new Validator())->validate(['a' => 'b'], ['a' => ['A', 'nope']])
        );
    }
}
