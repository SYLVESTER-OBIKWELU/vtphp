<?php

declare(strict_types=1);

namespace VtPhp\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * An API token (see HasApiTokens), like Laravel Sanctum's. Only a SHA-256
 * hash of the secret is stored; the plain-text token, "{id}|{secret}", is
 * shown once when it's created.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $token
 * @property array<int, string>|null $abilities
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 */
final class PersonalAccessToken extends Model
{
    protected $table = 'personal_access_tokens';

    /** @var array<int, string> */
    protected $fillable = ['user_id', 'name', 'token', 'abilities', 'expires_at', 'last_used_at'];

    /** @var array<int, string> */
    protected $hidden = ['token'];

    /** @var array<string, string> */
    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Find the token for a plain-text "{id}|{secret}" value, comparing the
     * secret in constant time. Null for unknown or malformed tokens.
     */
    public static function findToken(string $plainText): ?self
    {
        $parts = explode('|', $plainText, 2);

        if (count($parts) !== 2 || !ctype_digit($parts[0]) || $parts[1] === '') {
            return null;
        }

        /** @var self|null $token */
        $token = self::query()->find((int) $parts[0]);

        return $token !== null && hash_equals($token->token, hash('sha256', $parts[1])) ? $token : null;
    }

    /**
     * Whether the token may perform the ability ("*" grants everything).
     */
    public function can(string $ability): bool
    {
        $abilities = $this->abilities ?? [];

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    /**
     * Expired by its own expires_at, or older than the app-wide
     * `auth.tokens.expiration` minutes (when set).
     */
    public function isExpired(?int $maxLifetimeMinutes = null): bool
    {
        if ($this->expires_at !== null && $this->expires_at->getTimestamp() <= time()) {
            return true;
        }

        return $maxLifetimeMinutes !== null
            && $this->created_at !== null
            && $this->created_at->getTimestamp() + $maxLifetimeMinutes * 60 <= time();
    }
}
