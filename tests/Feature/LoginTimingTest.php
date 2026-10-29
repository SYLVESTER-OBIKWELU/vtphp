<?php

declare(strict_types=1);

namespace VtPhp\Tests\Feature;

use App\Models\User;
use VtPhp\Tests\Support\FeatureTestCase;

final class LoginTimingTest extends FeatureTestCase
{
    private function averageMs(string $email): float
    {
        $start = hrtime(true);

        for ($i = 0; $i < 3; ++$i) {
            auth()->attempt(['email' => $email, 'password' => 'wrong-password']);
        }

        return (hrtime(true) - $start) / 3 / 1e6;
    }

    public function test_unknown_and_passwordless_accounts_take_as_long_as_real_ones(): void
    {
        User::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'password123']);
        User::query()->create(['name' => 'Social', 'email' => 'social@example.com', 'password' => null]);
        $this->app->instance(\VtPhp\Session\Session::class, $this->app->make(\VtPhp\Session\SessionManager::class)->start(null));

        $real = $this->averageMs('ada@example.com');
        $unknown = $this->averageMs('nobody@example.com');
        $passwordless = $this->averageMs('social@example.com');

        // Before the fix: ~360 ms vs ~13 ms. Allow generous noise either way.
        $this->assertGreaterThan($real * 0.6, $unknown, sprintf('unknown %.0f ms vs real %.0f ms', $unknown, $real));
        $this->assertGreaterThan($real * 0.6, $passwordless, sprintf('passwordless %.0f ms vs real %.0f ms', $passwordless, $real));
    }
}
