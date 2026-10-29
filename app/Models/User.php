<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class User extends Model
{
    /** @var array<int, string> */
    protected $fillable = ['name', 'email', 'password'];

    /** @var array<int, string> */
    protected $hidden = ['password'];

    /** @var array<string, string> */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Hash plain-text passwords on assignment, like Laravel's `hashed` cast,
     * so `User::query()->create($request->validated())` is safe. Values that
     * are already hashes are stored untouched.
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            set: static fn (?string $value): ?string => $value === null || password_get_info($value)['algoName'] !== 'unknown'
                ? $value
                : password_hash($value, PASSWORD_BCRYPT),
        );
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill(['email_verified_at' => now()])->save();
    }

    public function verificationHash(): string
    {
        return sha1($this->email);
    }

    public function verificationUrl(): string
    {
        return rtrim((string) config('app.url'), '/')."/api/v1/email/verify/{$this->id}/".$this->verificationHash();
    }
}
