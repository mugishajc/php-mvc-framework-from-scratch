<?php

declare(strict_types=1);

namespace Tests\Unit;

use Framework\Validation\Validator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testValidDataPasses(): void
    {
        $validator = Validator::make(
            ['title' => '  Write tests  ', 'status' => 'todo', 'extra' => 'ignored'],
            ['title' => 'required|string|max:120', 'status' => 'required|in:todo,doing,done'],
        );

        $this->assertTrue($validator->passes());
        $this->assertSame([], $validator->errors());
        $this->assertSame(['title' => 'Write tests', 'status' => 'todo'], $validator->validated());
    }

    public function testRequiredRejectsMissingAndBlankValues(): void
    {
        $validator = Validator::make(
            ['title' => '   '],
            ['title' => 'required', 'status' => 'required'],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame([
            'title' => ['The title field is required.'],
            'status' => ['The status field is required.'],
        ], $validator->errors());
    }

    public function testOptionalEmptyFieldsSkipOtherRules(): void
    {
        $validator = Validator::make(['description' => ''], ['description' => 'string|min:10']);

        $this->assertTrue($validator->passes());
        $this->assertSame(['description' => null], $validator->validated());
    }

    public function testStringLengthUsesCharactersNotBytes(): void
    {
        // "héllo" is five characters but six bytes in UTF-8.
        $this->assertTrue(Validator::make(['name' => 'héllo'], ['name' => 'max:5'])->passes());
        $this->assertTrue(Validator::make(['name' => 'héllos'], ['name' => 'max:5'])->fails());
    }

    public function testMinAndMaxCompareValuesForNumericFields(): void
    {
        $rules = ['age' => 'integer|min:18|max:65'];

        $this->assertTrue(Validator::make(['age' => '30'], $rules)->passes());
        $this->assertSame(
            ['age' => ['The age field must be at least 18.']],
            Validator::make(['age' => '9'], $rules)->errors(),
        );
        $this->assertSame(
            ['age' => ['The age field must not be greater than 65.']],
            Validator::make(['age' => 100], $rules)->errors(),
        );
    }

    public function testIntegerRule(): void
    {
        $this->assertTrue(Validator::make(['n' => '12'], ['n' => 'integer'])->passes());
        $this->assertTrue(Validator::make(['n' => '1.5'], ['n' => 'integer'])->fails());
        $this->assertTrue(Validator::make(['n' => 'abc'], ['n' => 'integer'])->fails());
    }

    public function testEmailRule(): void
    {
        $this->assertTrue(Validator::make(['e' => 'jc@example.com'], ['e' => 'email'])->passes());
        $this->assertTrue(Validator::make(['e' => 'not-an-email'], ['e' => 'email'])->fails());
    }

    public function testInRuleIsStrict(): void
    {
        $rules = ['status' => 'in:todo,doing,done'];

        $this->assertTrue(Validator::make(['status' => 'doing'], $rules)->passes());
        $this->assertTrue(Validator::make(['status' => 'DONE'], $rules)->fails());
        $this->assertTrue(Validator::make(['status' => ['todo']], $rules)->fails());
    }

    public function testStringRuleRejectsArrays(): void
    {
        $validator = Validator::make(['title' => ['a', 'b']], ['title' => 'required|string']);

        $this->assertSame(['title' => ['The title field must be text.']], $validator->errors());
    }

    public function testStopsAtTheFirstFailingRulePerField(): void
    {
        $validator = Validator::make(['title' => str_repeat('x', 200)], ['title' => 'required|integer|max:10']);

        $this->assertCount(1, $validator->errors()['title']);
    }

    public function testRulesCanBeGivenAsAnArray(): void
    {
        $this->assertTrue(Validator::make(['x' => 'abc'], ['x' => ['required', 'max:3']])->passes());
    }

    public function testUnknownRuleThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Validator::make(['x' => 'abc'], ['x' => 'uppercase'])->passes();
    }
}
