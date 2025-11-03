<?php

namespace App\Models;

use Core\Model;

class User extends Model
{
    protected static $table = 'users';
    
    protected $fillable = [
        'name',
        'email',
        'password'
    ];
    
    protected $hidden = [
        'password'
    ];
    
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'bool'
    ];
}
