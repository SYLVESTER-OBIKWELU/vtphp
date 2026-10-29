<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use VtPhp\Exceptions\ValidationException;
use VtPhp\Validation\Validator;

final class ValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: mixed, 2: bool}>
     */
    public static function ruleCases(): iterable
    {
        yield 'required: value' => ['required', 'x', true];
        yield 'required: empty string' => ['required', '', false];
        yield 'required: null' => ['required', null, false];
        yield 'string: string' => ['string', 'x', true];
        yield 'string: int' => ['string', 5, false];
        yield 'integer: int' => ['integer', 5, true];
        yield 'integer: digit string' => ['integer', '42', true];
        yield 'integer: word' => ['integer', 'abc', false];
        yield 'integer: float' => ['integer', 4.5, false];
        yield 'numeric: decimal string' => ['numeric', '12.5', true];
        yield 'numeric: word' => ['numeric', 'abc', false];
        yield 'boolean: true' => ['boolean', true, true];
        yield 'boolean: "0"' => ['boolean', '0', true];
        yield 'boolean: "yes"' => ['boolean', 'yes', false];
        yield 'array: array' => ['array', [1], true];
        yield 'array: string' => ['array', 'x', false];
        yield 'email: valid' => ['email', 'ada@example.com', true];
        yield 'email: invalid' => ['email', 'not-an-email', false];
        yield 'max: string at limit' => ['max:3', 'abc', true];
        yield 'max: string over limit' => ['max:3', 'abcd', false];
        yield 'max: multibyte counts characters' => ['max:3', 'äöü', true];
        yield 'max: int at limit' => ['max:100', 100, true];
        yield 'max: int over limit' => ['max:100', 101, false];
        yield 'min: string under limit' => ['min:3', 'ab', false];
        yield 'min: int at limit' => ['min:3', 3, true];
        yield 'min: int under limit' => ['min:3', 2, false];
        yield 'in: listed' => ['in:draft,published', 'draft', true];
        yield 'in: unlisted' => ['in:draft,published', 'archived', false];
        yield 'date: valid' => ['date', '2026-10-29', true];
        yield 'date: invalid' => ['date', 'not a date', false];
        yield 'nullable: null skips other rules' => ['nullable|string|email', null, true];
        yield 'pipe syntax: all pass' => ['required|string|max:5', 'hello', true];
        yield 'pipe syntax: one fails' => ['required|string|max:5', 'hello!', false];
    }

    #[DataProvider('ruleCases')]
    public function test_rule(string $rules, mixed $value, bool $passes): void
    {
        $this->assertSame($passes, Validator::make(['field' => $value], ['field' => $rules])->passes());
    }

    public function test_confirmed_requires_a_matching_confirmation_field(): void
    {
        $rules = ['password' => ['confirmed']];

        $this->assertTrue(Validator::make(['password' => 'secret', 'password_confirmation' => 'secret'], $rules)->passes());
        $this->assertFalse(Validator::make(['password' => 'secret', 'password_confirmation' => 'other'], $rules)->passes());
        $this->assertFalse(Validator::make(['password' => 'secret'], $rules)->passes());
    }

    public function test_it_collects_messages_per_field(): void
    {
        $errors = Validator::make(
            ['email' => 'nope', 'age' => 'abc'],
            ['name' => 'required', 'email' => 'required|email', 'age' => 'integer'],
        )->errors();

        $this->assertSame(['The name field is required.'], $errors['name']);
        $this->assertSame(['The email field must be a valid email address.'], $errors['email']);
        $this->assertSame(['The age field must be an integer.'], $errors['age']);
    }

    public function test_validate_returns_the_data_when_valid(): void
    {
        $data = ['name' => 'Ada'];

        $this->assertSame($data, Validator::make($data, ['name' => 'required|string'])->validate());
    }

    public function test_validated_returns_only_fields_that_have_rules(): void
    {
        $validator = Validator::make(
            ['name' => 'Ada', 'is_admin' => true, 'password' => 'x', 'password_confirmation' => 'x'],
            ['name' => 'required', 'password' => 'confirmed', 'nickname' => 'nullable|string'],
        );

        $this->assertSame(['name' => 'Ada', 'password' => 'x'], $validator->validated());
        $this->assertSame($validator->validated(), $validator->validate());
    }

    public function test_sometimes_skips_absent_fields_but_validates_present_ones(): void
    {
        $rules = ['name' => 'sometimes|string|max:5'];

        $this->assertTrue(Validator::make([], $rules)->passes());
        $this->assertTrue(Validator::make(['name' => 'Ada'], $rules)->passes());
        $this->assertFalse(Validator::make(['name' => 'Adalovelace'], $rules)->passes());
        $this->assertFalse(Validator::make(['name' => null], $rules)->passes(), 'Present-but-null is still validated.');
    }

    public function test_validate_throws_with_the_errors_when_invalid(): void
    {
        try {
            Validator::make(['email' => 'not-an-email'], ['name' => ['required'], 'email' => ['required', 'email']])->validate();
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertSame(['name', 'email'], array_keys($e->getErrors()));
        }
    }

    #[Group('defect')]
    public function test_an_unknown_rule_is_not_silently_treated_as_passing(): void
    {
        // A typo ("requird") or an unimplemented Laravel rule ("unique") passes today.
        try {
            $passes = Validator::make(['field' => ''], ['field' => 'requird'])->passes();
        } catch (\Throwable) {
            $passes = false;
        }

        $this->assertFalse($passes, 'Unknown rule "requird" was silently accepted.');
    }

    #[Group('defect')]
    public function test_min_compares_numeric_strings_by_value(): void
    {
        // Query-string / form input arrives as strings: "25" must satisfy integer|min:18.
        $this->assertTrue(Validator::make(['age' => '25'], ['age' => 'integer|min:18'])->passes());
    }

    #[Group('defect')]
    public function test_integer_accepts_negative_numeric_strings(): void
    {
        $this->assertTrue(Validator::make(['offset' => '-5'], ['offset' => 'integer'])->passes());
    }

    #[Group('defect')]
    public function test_in_rejects_an_array_value_cleanly(): void
    {
        // Currently casts the array to string, raising "Array to string conversion".
        $this->assertTrue(Validator::make(['status' => ['draft']], ['status' => 'in:draft,published'])->fails());
    }
}
