<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject; // ✅ add this

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable; // remove HasApiTokens if you are using JWT

    protected $fillable = [
        'name',
        'username',
        'password',
        'role',
        'email'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // JWT methods
    public function getJWTIdentifier()
    {
        return $this->getKey(); // primary key
    }

    public function getJWTCustomClaims()
    {
        return []; // optional extra claims
    }
}
