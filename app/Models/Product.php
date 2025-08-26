<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $primaryKey = 'product_id';

    protected $fillable = [
        'product_name',
        'description',
        'price',
        'image_url',
        'seller_id'
    ];

    // Relationship: Product belongs to a seller (User)
    public function user()
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
        // seller_id in products → id in users
    }
}
