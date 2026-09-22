<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use VtPhp\Database\Seeder;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'ada@example.com'],
            ['name' => 'Ada Lovelace', 'password' => password_hash('password', PASSWORD_BCRYPT)],
        );

        User::query()->firstOrCreate(
            ['email' => 'grace@example.com'],
            ['name' => 'Grace Hopper', 'password' => password_hash('password', PASSWORD_BCRYPT)],
        );
    }
}
