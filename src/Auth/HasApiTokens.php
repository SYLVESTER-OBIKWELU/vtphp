<?php

declare(strict_types=1);

namespace VtPhp\Auth;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lets an Eloquent user model issue API tokens, like Laravel Sanctum:
 *
 *     $token = $user->createToken('iPhone', ['notes:read']);
 *     $token->plainTextToken; // "1|9f2c..." - send as "Authorization: Bearer 1|9f2c..."
 */
trait HasApiTokens
{
    private ?PersonalAccessToken $currentAccessToken = null;

    /**
     * @return HasMany<PersonalAccessToken, $this>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(PersonalAccessToken::class, 'user_id');
    }

    /**
     * @param array<int, string> $abilities What the token may do; ['*'] = everything.
     */
    public function createToken(string $name, array $abilities = ['*'], ?\DateTimeInterface $expiresAt = null): NewAccessToken
    {
        $secret = bin2hex(random_bytes(20));

        /** @var PersonalAccessToken $token */
        $token = $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $secret),
            'abilities' => array_values($abilities),
            'expires_at' => $expiresAt,
        ]);

        return new NewAccessToken($token, $token->getKey().'|'.$secret);
    }

    /**
     * The token this request authenticated with (null for session logins).
     */
    public function currentAccessToken(): ?PersonalAccessToken
    {
        return $this->currentAccessToken;
    }

    public function withAccessToken(?PersonalAccessToken $token): static
    {
        $this->currentAccessToken = $token;

        return $this;
    }

    /**
     * Whether this request may perform the ability. Session (cookie) logins
     * may do anything; token logins only what their token allows.
     */
    public function tokenCan(string $ability): bool
    {
        return $this->currentAccessToken?->can($ability) ?? true;
    }
}
