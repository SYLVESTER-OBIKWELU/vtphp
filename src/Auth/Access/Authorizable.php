<?php

declare(strict_types=1);

namespace VtPhp\Auth\Access;

/**
 * Adds can()/cannot() to a user model: `$user->can('update', $note)`.
 */
trait Authorizable
{
    public function can(string $ability, mixed ...$arguments): bool
    {
        return app(Gate::class)->forUser($this)->allows($ability, ...$arguments);
    }

    public function cannot(string $ability, mixed ...$arguments): bool
    {
        return !$this->can($ability, ...$arguments);
    }
}
