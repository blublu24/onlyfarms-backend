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
        'phone_number',
        'business_permit',
    ];

    // Each seller belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
