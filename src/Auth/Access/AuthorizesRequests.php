<?php

declare(strict_types=1);

namespace VtPhp\Auth\Access;

/**
 * Adds $this->authorize() to a controller, like Laravel's:
 *
 *     $this->authorize('update', $note);   // 403 unless NotePolicy::update() allows it
 */
trait AuthorizesRequests
{
    protected function authorize(string $ability, mixed ...$arguments): void
    {
        app(Gate::class)->authorize($ability, ...$arguments);
    }
}
