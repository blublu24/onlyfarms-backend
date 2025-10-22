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
        'email',
        'registered_name',
        'business_name',
        'tin',
        'vat_status',
        'business_email',
        'business_phone',
        'government_id_type',
        'government_id_front',
        'government_id_back',
        'status',
        'rejection_reason',
    ];

    // 🔗 A seller belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 🔗 A seller has many products
    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    // 🔗 A seller has many crop schedules
    public function cropSchedules()
    {
        return $this->hasMany(CropSchedule::class, 'seller_id');
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class, 'seller_id');
    }
}
