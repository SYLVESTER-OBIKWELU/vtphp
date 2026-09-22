<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VtPhp\Validation\Validator;
use VtPhp\Exceptions\ValidationException;

final class ValidatorTest extends TestCase
{
    public function test_it_passes_for_valid_data(): void
    {
        $validator = Validator::make(
            ['name' => 'Ada', 'email' => 'ada@example.com'],
            ['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email']],
        );

        $this->assertTrue($validator->passes());
    }

    public function test_it_throws_on_invalid_data(): void
    {
        $this->expectException(ValidationException::class);

        Validator::make(
            ['email' => 'not-an-email'],
            ['name' => ['required'], 'email' => ['required', 'email']],
        )->validate();
    }
}
