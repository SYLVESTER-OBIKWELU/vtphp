<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Who may manage user accounts. Found automatically for App\Models\User.
 */
final class UserPolicy
{
    public function update(User $user, User $account): bool
    {
        return $user->id === $account->id;
    }

    public function delete(User $user, User $account): bool
    {
        return $user->id === $account->id;
    }
}
