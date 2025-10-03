<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'buyer_id', 'seller_id', 'last_message_at'];

    // Each conversation has many messages
    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    // Buyer
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    // Seller (adjust if sellers are also users)
    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
        // or return $this->belongsTo(User::class, 'seller_id'); if seller is a user
    }

    // Optional: latest message shortcut
    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latest();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }


}
