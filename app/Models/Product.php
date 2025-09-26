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
        'seller_id', // ✅ new field for filtering & categorization
        'avg_rating', // ✅ added
        'ratings_count', // ✅ added
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'avg_rating' => 'decimal:2',
        'ratings_count' => 'integer',
    ];

    /**
     * Relationship: Product belongs to a seller (User)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }

    /**
     * Relationship: Product has many reviews
     */
    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id', 'product_id');
    }

    /**
     * Accessor: Return full image URL (frontend friendly)
     */
    public function getImageUrlAttribute($value)
    {
        if ($value) {
            return url('storage/' . $value); // ✅ Always return full URL
        }
        return null;
    }

    /**
     * Helper: Recalculate avg_rating & ratings_count based on reviews
     */
    public function updateRatingStats()
    {
        $this->avg_rating = $this->reviews()->avg('rating') ?? 0;
        $this->ratings_count = $this->reviews()->count();
        $this->save();
    }
}
