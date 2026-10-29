<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $email
 * @property string $token
 * @property \Illuminate\Support\Carbon|null $created_at
 */
final class PasswordResetToken extends Model
{
    public const TTL_MINUTES = 60;

    protected $table = 'password_reset_tokens';

    protected $primaryKey = 'email';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /** @var array<int, string> */
    protected $fillable = ['email', 'token', 'created_at'];

    /** @var array<string, string> */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Create (or replace) the user's reset token. Only its SHA-256 hash is
     * stored; the returned plain-text token is what goes in the email.
     */
    public static function issueFor(User $user): string
    {
        $token = bin2hex(random_bytes(32));

        self::query()->updateOrCreate(
            ['email' => $user->email],
            ['token' => hash('sha256', $token), 'created_at' => now()],
        );

        return $token;
    }

    public function matches(string $token): bool
    {
        return hash_equals($this->token, hash('sha256', $token));
    }

    public function isExpired(): bool
    {
        return $this->created_at === null
            || (now()->getTimestamp() - $this->created_at->getTimestamp()) > self::TTL_MINUTES * 60;
    }
}
