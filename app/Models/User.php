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
        'consumer_id',
        'is_seller',
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

    // ✅ Relationship: A user can have many products if they are a seller
    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    // ✅ Relationship: A user may have one seller profile
    public function seller()
    {
        return $this->hasOne(Seller::class, 'user_id');
    }
}
