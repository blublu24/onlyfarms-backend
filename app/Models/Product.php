<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

    // Accessor: Return full image URL
    public function getImageUrlAttribute($value)
    {
        if ($value) {
            return Storage::url($value);
            // e.g. /storage/products/test.jpg
        }
        return null; // or return a default image
    }
}
