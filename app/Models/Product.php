<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    /**
     * Primary key for the products table.
     */
    protected $primaryKey = 'product_id';
    public $incrementing = true;
    protected $keyType = 'int';

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'product_name',
        'description',
        'price',
        'image_url',
        'seller_id',

        // Ratings
        'avg_rating',
        'ratings_count',

        // Preorders
        'manual_availability_date',
        'accept_preorders',
        'max_preorder_quantity',
    ];

    /**
     * Attribute casting for database fields.
     */
    protected $casts = [
        'price' => 'decimal:2',
        'avg_rating' => 'decimal:2',
        'ratings_count' => 'integer',
        'manual_availability_date' => 'date',
        'accept_preorders' => 'boolean',
    ];

    /* ==============================
     | Relationships
     ============================== */
    // 👇 Automatically include in API response
    protected $appends = ['fixed_image_url'];

    /**
     * A product belongs to a seller (User).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * A product can have many reviews.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id', 'product_id');
    }

    /**
     * A product can have many crop schedules.
     */
    public function cropSchedules()
    {
        return $this->hasMany(CropSchedule::class, 'product_id', 'product_id');
    }

    /**
     * A product can have many preorders.
     */
    public function preorders()
    {
        return $this->hasMany(Preorder::class, 'product_id', 'product_id');
    }

    /* ==============================
     | Accessors & Helpers
     ============================== */

    /**
     * Accessor: Return relative path for the frontend.
     */
    public function getImageUrlAttribute($value)
    {
        if (!$value) {
            return null;
        }
        
        // ✅ Avoid double "storage/" - if already starts with storage/, return as is
        if (str_starts_with($value, 'storage/')) {
            return $value;
        }
        
        return 'storage/' . $value;
    }

    /**
     * Helper: Recalculate average rating & ratings count.
     * New Accessor: Fix duplicate "storage/storage" issues
     */
    public function getFixedImageUrlAttribute()
    {
        $value = $this->attributes['image_url'] ?? null;

        if (!$value) {
            return null;
        }

        // Already a full URL - return as is
        if (str_starts_with($value, 'http')) {
            return $value;
        }

        // Already starts with "storage/" - return as is
        if (str_starts_with($value, 'storage/')) {
            return $value;
        }

        // Default case - return relative path
        return 'storage/' . $value;
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

    /**
     * Helper: Determine expected availability date.
     * Priority:
     *  1. Manual override (manual_availability_date).
     *  2. Nearest crop schedule with expected_harvest_start.
     */
    public function getExpectedAvailabilityDate()
    {
        if ($this->manual_availability_date) {
            return $this->manual_availability_date;
        }

        $nearest = $this->cropSchedules()
                        ->whereNotNull('expected_harvest_start')
                        ->orderBy('expected_harvest_start', 'asc')
                        ->first();

        return $nearest ? $nearest->expected_harvest_start : null; // null means TBD
    }

    public function seller()
    {
        return $this->hasOne(\App\Models\Seller::class, 'user_id', 'seller_id');
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class, 'product_id');
    }
}
