<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Laravel\Sanctum\HasApiTokens;
use MongoDB\Laravel\Eloquent\Model as Eloquent;  // Correctement importé

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, Notifiable;
    /**
     * MongoDB connection and collection details
     */
    protected $connection = 'mongodb';
    protected $collection = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'identity', 'email', 'username', 'isOnLine', 'isActivated',
        'password', 'verifyAd', 'verifyToken', 'tokenExpiredAt',
        'createdAt', 'updatedAt',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'identity' => 'array',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
            'tokenExpiredAt' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the identifier for JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Get custom claims for JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Override the `getAuthIdentifierName` method to use MongoDB `_id`.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return '_id'; // MongoDB uses `_id` as the primary key.
    }

    
}