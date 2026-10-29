<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use VtPhp\Database\Seeder;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Sample accounts with a known password: never in production, even
        // with `db:seed --force`.
        if (app()->environment() === 'production') {
            return;
        }

        User::query()->firstOrCreate(
            ['email' => 'ada@example.com'],
            ['name' => 'Ada Lovelace', 'password' => 'password'],
        );

        User::query()->firstOrCreate(
            ['email' => 'grace@example.com'],
            ['name' => 'Grace Hopper', 'password' => 'password'],
        );
    }
}
