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
        'image_url',
        'additional_images',
        'seller_id',

        // Multi-unit support
        'stock_kg',
        'total_sold',
        'price_per_kg',
        'available_units',
        'pieces_per_bundle',

        // Variation prices
        'premium_price_per_kg',
        'type_a_price_per_kg',
        'type_b_price_per_kg',

        // Variation stocks
        'premium_stock_kg',
        'type_a_stock_kg',
        'type_b_stock_kg',

        // Ratings
        'avg_rating',
        'ratings_count',
        'rating_weight',

        // Analytics
        'total_sold',
        'relevance_score',

        // Preorders
        'manual_availability_date',
        'accept_preorders',
        'max_preorder_quantity',

        // Admin verification
        'status',         // pending, approved, rejected
        'approved_at',    // when approved
        'approved_by',    // admin who approved
    ];

    /**
     * Attribute casting for database fields.
     */
    protected $casts = [
        'stock_kg' => 'decimal:4',
        'price_per_kg' => 'decimal:2',
        'available_units' => 'array',
        'additional_images' => 'array',
        'avg_rating' => 'decimal:2',
        'ratings_count' => 'integer',
        'rating_weight' => 'decimal:4',
        'total_sold' => 'decimal:2',
        'relevance_score' => 'decimal:4',
        'manual_availability_date' => 'date',
        'accept_preorders' => 'boolean',
        // Variation prices
        'premium_price_per_kg' => 'decimal:2',
        'type_a_price_per_kg' => 'decimal:2',
        'type_b_price_per_kg' => 'decimal:2',
        // Variation stocks
        'premium_stock_kg' => 'decimal:4',
        'type_a_stock_kg' => 'decimal:4',
        'type_b_stock_kg' => 'decimal:4',
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
     * Helper: Recalculate avg_rating, ratings_count, and rating_weight based on reviews
     */
    public function updateRatingStats()
    {
        $avgRating = $this->reviews()->avg('rating') ?? 0;
        $ratingsCount = $this->reviews()->count();
        
        // Calculate weighted rating for relevance score
        // Combines rating quality with review volume (trust factor)
        $ratingQuality = $avgRating / 5.0; // Normalize to 0-1
        $reviewTrust = log($ratingsCount + 1) / log(101); // Caps at ~100 reviews
        $ratingWeight = ($ratingQuality * 0.7) + ($reviewTrust * 0.3);
        
        $this->avg_rating = $avgRating;
        $this->ratings_count = $ratingsCount;
        $this->rating_weight = $ratingWeight;
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

    /**
     * Get available units for this product based on vegetable type
     */
    public function getAvailableUnitsAttribute($value)
    {
        if ($value) {
            return is_string($value) ? json_decode($value, true) : $value;
        }
        
        // Fallback to default units if not set
        return ['kg', 'sack', 'small_sack'];
    }

    /**
     * Get valid units for this product's vegetable type
     */
    public function getValidUnits(): array
    {
        $vegetableSlug = $this->getVegetableSlug();
        return \App\Models\UnitConversion::getAvailableUnits($vegetableSlug);
    }

    /**
     * Extract vegetable slug from product name
     */
    public function getVegetableSlug(): string
    {
        // Convert product name to slug format
        $name = strtolower($this->product_name);
        $name = str_replace(' ', '_', $name);
        
        // Handle common vegetable name mappings (comprehensive list)
        $mappings = [
            // Tomato variations
            'tomato' => 'kamatis',
            'tomatoes' => 'kamatis',
            'kamatis' => 'kamatis',
            // Onion variations
            'onion' => 'sibuyas',
            'onions' => 'sibuyas',
            'sibuyas' => 'sibuyas',
            // Garlic variations
            'garlic' => 'bawang',
            'bawang' => 'bawang',
            // Squash variations
            'squash' => 'kalabasa',
            'kalabasa' => 'kalabasa',
            // Bitter gourd variations
            'bitter_gourd' => 'ampalaya',
            'bitter_gourd' => 'ampalaya',
            'ampalaya' => 'ampalaya',
            // Eggplant variations
            'eggplant' => 'talong',
            'talong' => 'talong',
            // Okra
            'okra' => 'okra',
            // String beans variations
            'string_beans' => 'sitaw',
            'sitaw' => 'sitaw',
            // Water spinach variations
            'water_spinach' => 'kangkong',
            'kangkong' => 'kangkong',
            // Pechay
            'pechay' => 'pechay',
            // Cabbage variations
            'cabbage' => 'repolyo',
            'repolyo' => 'repolyo',
            // Carrot variations
            'carrot' => 'carrots',
            'carrots' => 'carrots',
            // Sayote
            'sayote' => 'sayote',
            // Potato variations
            'potato' => 'patatas',
            'potatoes' => 'patatas',
            'patatas' => 'patatas',
            // Radish variations
            'radish' => 'labanos',
            'labanos' => 'labanos',
            // Bottle gourd variations
            'bottle_gourd' => 'upo',
            'upo' => 'upo',
            // Ginger variations
            'ginger' => 'luya',
            'luya' => 'luya',
            // Green chili variations
            'green_chili' => 'siling_green',
            'siling_green' => 'siling_green',
            // Red chili variations
            'red_chili' => 'siling_red',
            'siling_red' => 'siling_red',
            // Bell pepper variations
            'bell_pepper' => 'bell_pepper',
            // Lettuce
            'lettuce' => 'lettuce',
            // Cucumber
            'cucumber' => 'cucumber',
            // Broccoli
            'broccoli' => 'broccoli',
            // Taro variations
            'taro' => 'gabi',
            'gabi' => 'gabi',
            // Sweet potato tops variations
            'sweet_potato_tops' => 'talbos_ng_kamote',
            'talbos_ng_kamote' => 'talbos_ng_kamote',
        ];
        
        return $mappings[$name] ?? $name;
    }

    /**
     * Check if product has sufficient stock for the requested weight
     */
    public function hasStockForWeight(float $weightKg): bool
    {
        return $this->stock_kg >= $weightKg;
    }

    /**
     * Reserve stock for an order
     */
    public function reserveStock(float $weightKg): bool
    {
        if ($this->hasStockForWeight($weightKg)) {
            $this->stock_kg -= $weightKg;
            return $this->save();
        }
        return false;
    }

    /**
     * Release reserved stock back to available stock
     */
    public function releaseStock(float $weightKg): bool
    {
        $this->stock_kg += $weightKg;
        return $this->save();
    }

}
