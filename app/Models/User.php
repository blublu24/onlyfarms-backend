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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // ✅ Casts ensure `is_seller` returns boolean instead of 0/1
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_seller' => 'boolean',
    ];

    // Relationship: A user can have a seller profile
    public function seller()
    {
        return $this->hasOne(Seller::class);
    }

    // Relationship: A user can have many products (if seller)
    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id');
    }
}
