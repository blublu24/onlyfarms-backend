<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seller extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_name',
        'address',
        'phone_number',     // ✅ matches migration
        'business_permit',  // ✅ matches migration
    ];

    // A seller belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // A seller has many products
    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id');
    }
}
