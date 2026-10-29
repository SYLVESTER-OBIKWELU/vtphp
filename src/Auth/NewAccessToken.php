<?php

declare(strict_types=1);

namespace VtPhp\Auth;

/**
 * A freshly created token. `plainTextToken` is the only time the secret is
 * available: give it to the client, it can't be recovered later.
 */
final readonly class NewAccessToken
{
    public function __construct(
        public PersonalAccessToken $accessToken,
        public string $plainTextToken,
    ) {
    }
}
