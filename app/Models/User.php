<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_seller',
        'consumer_id', // ✅ auto-generated
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_seller' => 'boolean',
    ];

    // ✅ Automatically generate consumer_id on creating
    protected static function booted()
    {
        static::creating(function ($user) {
            $user->consumer_id = uniqid('cons_');
        });
    }

    // Relationships
    public function seller()
    {
        return $this->hasOne(Seller::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id');
    }
}
