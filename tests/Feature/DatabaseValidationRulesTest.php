<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use App\Models\User;
use VtPhp\Tests\Support\FeatureTestCase;
use VtPhp\Validation\Validator;

final class DatabaseValidationRulesTest extends FeatureTestCase
{
    private User $ada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ada = User::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => null]);
    }

    public function test_unique_rejects_taken_values(): void
    {
        $this->assertFalse(Validator::make(['email' => 'ada@example.com'], ['email' => 'unique:users'])->passes());
        $this->assertTrue(Validator::make(['email' => 'grace@example.com'], ['email' => 'unique:users'])->passes());
        $this->assertSame(
            ['email' => ['The email has already been taken.']],
            Validator::make(['email' => 'ada@example.com'], ['email' => 'unique:users,email'])->errors(),
        );
    }

    public function test_unique_uses_an_explicit_column_and_can_ignore_a_row(): void
    {
        $this->assertFalse(Validator::make(['contact' => 'ada@example.com'], ['contact' => 'unique:users,email'])->passes());
        $this->assertTrue(
            Validator::make(['email' => 'ada@example.com'], ['email' => "unique:users,email,{$this->ada->id}"])->passes(),
            'An update may keep its own current value.',
        );
        $this->assertTrue(Validator::make(['email' => 'ada@example.com'], ['email' => "unique:users,email,{$this->ada->id},id"])->passes());
    }

    public function test_exists_requires_a_matching_row(): void
    {
        $this->assertTrue(Validator::make(['user_id' => $this->ada->id], ['user_id' => 'exists:users,id'])->passes());
        $this->assertFalse(Validator::make(['user_id' => 999], ['user_id' => 'exists:users,id'])->passes());
        $this->assertFalse(Validator::make(['user_id' => [1]], ['user_id' => 'exists:users,id'])->passes(), 'Non-scalar values never match.');
    }
}
