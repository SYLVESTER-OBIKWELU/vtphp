<?php

declare(strict_types=1);

namespace App\Models;

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

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill(['email_verified_at' => now()])->save();
    }
}
