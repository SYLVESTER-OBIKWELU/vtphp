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
}
