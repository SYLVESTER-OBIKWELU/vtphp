<?php

declare(strict_types=1);

namespace VtPhp\Auth;

/**
 * Spends the same time as a real password check when there is nothing to
 * check (unknown email, account without a password), so response times
 * don't reveal which accounts exist.
 *
 * The hash is precomputed (hashing at request time would itself be slow and
 * re-open the gap) and uses bcrypt cost 12, PHP 8.4+'s default, so verifying
 * against it costs the same as verifying a real user's password.
 */
final class TimingSafeCheck
{
    /** A bcrypt (cost 12) hash of a random, discarded password. */
    private const DUMMY_HASH = '$2y$12$NCyDWQmKA9ME/6D6t3TAl.8NZeGonQp5LJ0i6.WwbSXB.3dOKHZpC';

    public static function burn(string $password): void
    {
        password_verify($password, self::DUMMY_HASH);
    }
}
