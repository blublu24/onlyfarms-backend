<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $primaryKey = 'product_id'; // ✅ matches migration

    protected $fillable = [
        'product_name',
        'description',
        'price',
        'image_url',
        'seller_id',      // link to User/Seller
        'avg_rating',     // rating stats
        'ratings_count',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'avg_rating' => 'decimal:2',
        'ratings_count' => 'integer',
    ];

    // 👇 Automatically include in API response
    protected $appends = ['fixed_image_url'];

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
     * Relationship: Product has many crop schedules
     * ✅ Connects crop schedules to product for automatic crop_name assignment
     */
    public function cropSchedules()
    {
        return $this->hasMany(CropSchedule::class, 'product_id', 'product_id');
    }

    /**
     * Accessor: Return full image URL (frontend-friendly)
     */
    public function getImageUrlAttribute($value)
    {
        return $value ? url('storage/' . $value) : null;
    }

    /**
     * New Accessor: Fix duplicate "storage/storage" issues
     */
    public function getFixedImageUrlAttribute()
    {
        $value = $this->attributes['image_url'] ?? null;

        if (!$value) {
            return null;
        }

        // Already a full URL
        if (str_starts_with($value, 'http')) {
            return $value;
        }

        // Already starts with "storage/"
        if (str_starts_with($value, 'storage/')) {
            return url($value);
        }

        // Default case
        return url('storage/' . $value);
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
