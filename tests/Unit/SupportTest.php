<?php

declare(strict_types=1);

namespace VtPhp\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VtPhp\Support\Arr;
use VtPhp\Support\Str;

final class SupportTest extends TestCase
{
    /**
     * @return iterable<array{0: string, 1: string}>
     */
    public static function studlyCases(): iterable
    {
        yield ['user_profile', 'UserProfile'];
        yield ['user-profile', 'UserProfile'];
        yield ['userProfile', 'UserProfile'];
        yield ['invoice', 'Invoice'];
    }

    #[DataProvider('studlyCases')]
    public function test_studly(string $input, string $expected): void
    {
        $this->assertSame($expected, Str::studly($input));
    }

    /**
     * @return iterable<array{0: string, 1: string}>
     */
    public static function snakeCases(): iterable
    {
        yield ['InvoicePaid', 'invoice_paid'];
        yield ['welcomeEmail', 'welcome_email'];
        yield ['already_snake', 'already_snake'];
    }

    #[DataProvider('snakeCases')]
    public function test_snake(string $input, string $expected): void
    {
        $this->assertSame($expected, Str::snake($input));
    }

    public function test_random_returns_hex_of_the_requested_length(): void
    {
        foreach ([1, 7, 32, 64] as $length) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{'.$length.'}$/', Str::random($length));
        }

        $this->assertNotSame(Str::random(), Str::random());
    }

    public function test_arr_get_only_and_except(): void
    {
        $array = ['user' => ['name' => 'Ada', 'address' => ['city' => 'London']], 'a.b' => 'literal', 'x' => 1];

        $this->assertSame('London', Arr::get($array, 'user.address.city'));
        $this->assertSame('literal', Arr::get($array, 'a.b'));
        $this->assertSame('none', Arr::get($array, 'user.phone', 'none'));
        $this->assertSame(['x' => 1], Arr::only($array, ['x', 'missing']));
        $this->assertSame(['x' => 1], Arr::except($array, ['user', 'a.b']));
    }

    /**
     * @return iterable<string, array{0: string, 1: mixed}>
     */
    public static function envCases(): iterable
    {
        yield 'true' => ['true', true];
        yield '(true)' => ['(true)', true];
        yield 'false' => ['false', false];
        yield 'null' => ['null', null];
        yield 'empty' => ['empty', ''];
        yield 'quoted' => ['"quoted value"', 'quoted value'];
        yield 'plain' => ['plain', 'plain'];
    }

    #[DataProvider('envCases')]
    public function test_env_casts_special_values(string $raw, mixed $expected): void
    {
        putenv("VTPHP_TEST_ENV={$raw}");
        $_ENV['VTPHP_TEST_ENV'] = $_SERVER['VTPHP_TEST_ENV'] = $raw;

        try {
            $this->assertSame($expected, env('VTPHP_TEST_ENV'));
        } finally {
            putenv('VTPHP_TEST_ENV');
            unset($_ENV['VTPHP_TEST_ENV'], $_SERVER['VTPHP_TEST_ENV']);
        }
    }

    public function test_env_returns_the_default_for_unset_keys(): void
    {
        $this->assertSame('fallback', env('VTPHP_DEFINITELY_UNSET', 'fallback'));
    }
}
