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
     * Get valid units for this product's vegetable type with full information
     */
    public function getValidUnits(): array
    {
        $vegetableSlug = $this->getVegetableSlug();
        $units = \App\Models\UnitConversion::getAvailableUnitsWithInfo($vegetableSlug);
        
        // If no units from database, return vegetable-specific default units
        if (empty($units)) {
            return $this->getVegetableSpecificUnits($vegetableSlug);
        }
        
        return $units;
    }

    /**
     * Get units for a specific variation with pricing
     */
    public function getUnitsForVariation(string $variationType = 'regular'): array
    {
        $vegetableSlug = $this->getVegetableSlug();
        $units = \App\Models\UnitConversion::getAvailableUnitsWithInfo($vegetableSlug);
        
        // If no units from database, return vegetable-specific default units
        if (empty($units)) {
            return $this->getVegetableSpecificUnits($vegetableSlug);
        }
        
        // Filter units and apply variation pricing
        return array_map(function ($unit) use ($variationType) {
            return [
                'key' => $unit['key'],
                'label' => $unit['label'],
                'weight_kg' => $unit['weight_kg'],
                'description' => $unit['description'],
                'price' => $unit['prices'][$variationType] ?? $unit['prices']['regular'] ?? 0,
                'enabled' => $unit['enabled'],
                'sort_order' => $unit['sort_order'],
            ];
        }, $units);
    }

    /**
     * Get vegetable-specific available units
     */
    private function getVegetableSpecificUnits(string $vegetableSlug): array
    {
        $vegetableUnits = [
            // Eggplant (talong) - Available: kg, sack, small_sack, tali
            'talong' => ['kg', 'sack', 'small_sack', 'tali'],
            
            // Tomato (kamatis) - Available: kg, sack, small_sack, pieces
            'kamatis' => ['kg', 'sack', 'small_sack', 'pieces'],
            
            // Onion (sibuyas) - Available: kg, sack, small_sack, packet
            'sibuyas' => ['kg', 'sack', 'small_sack', 'packet'],
            
            // Garlic (bawang) - Available: kg, sack, small_sack, tali, packet
            'bawang' => ['kg', 'sack', 'small_sack', 'tali', 'packet'],
            
            // Squash (kalabasa) - Available: kg, sack, small_sack, pieces
            'kalabasa' => ['kg', 'sack', 'small_sack', 'pieces'],
            
            // Okra - Available: kg, sack, small_sack, tali
            'okra' => ['kg', 'sack', 'small_sack', 'tali'],
            
            // String beans (sitaw) - Available: kg, sack, small_sack, tali
            'sitaw' => ['kg', 'sack', 'small_sack', 'tali'],
            
            // Water spinach (kangkong) - Available: kg, sack, small_sack, tali
            'kangkong' => ['kg', 'sack', 'small_sack', 'tali'],
            
            // Pechay - Available: kg, sack, small_sack, tali
            'pechay' => ['kg', 'sack', 'small_sack', 'tali'],
            
            // Cabbage (repolyo) - Available: kg, sack, small_sack, pieces
            'repolyo' => ['kg', 'sack', 'small_sack', 'pieces'],
            
            // Carrot - Available: kg, sack, small_sack, packet
            'carrots' => ['kg', 'sack', 'small_sack', 'packet'],
            
            // Sayote - Available: kg, sack, small_sack, pieces
            'sayote' => ['kg', 'sack', 'small_sack', 'pieces'],
            
            // Potato (patatas) - Available: kg, sack, small_sack, pieces
            'patatas' => ['kg', 'sack', 'small_sack', 'pieces'],
        ];
        
        return $vegetableUnits[$vegetableSlug] ?? ['kg', 'sack', 'small_sack']; // Default fallback
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

    /* ==============================
     | Reserved Quantity Tracking for Preorders
     ============================== */

    /**
     * Get total reserved quantity for a specific variation from active preorders
     */
    public function getReservedQuantity(string $variationType = 'regular'): float
    {
        return $this->preorders()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('variation_type', $variationType)
            ->sum('reserved_qty') ?? 0;
    }

    /**
     * Get available stock (actual stock minus reserved quantities)
     */
    public function getAvailableStock(string $variationType = 'regular'): float
    {
        $actualStock = $this->getVariationStock($variationType);
        $reserved = $this->getReservedQuantity($variationType);
        
        return max(0, $actualStock - $reserved);
    }

    /**
     * Get stock for a specific variation
     */
    private function getVariationStock(string $variationType): float
    {
        switch ($variationType) {
            case 'premium':
                return $this->premium_stock_kg ?? 0;
            case 'type_a':
                return $this->type_a_stock_kg ?? 0;
            case 'type_b':
                return $this->type_b_stock_kg ?? 0;
            case 'regular':
            default:
                return $this->stock_kg ?? 0;
        }
    }

    /**
     * Check if product can accept a preorder of given quantity
     */
    public function canAcceptPreorder(float $reservedQty, string $variationType = 'regular'): bool
    {
        // Get expected harvest quantity from active crop schedules
        $expectedHarvest = $this->getExpectedHarvestQuantity();
        
        // Get current reserved quantity
        $currentReserved = $this->getReservedQuantity($variationType);
        
        // Total reserved if this preorder is accepted
        $totalReserved = $currentReserved + $reservedQty;
        
        // Can accept if total reserved doesn't exceed expected harvest
        return $totalReserved <= $expectedHarvest;
    }

    /**
     * Get expected harvest quantity from active crop schedules
     */
    public function getExpectedHarvestQuantity(): float
    {
        $activeSchedules = $this->cropSchedules()
            ->where('is_active', true)
            ->whereNotNull('quantity_estimate')
            ->get();
        
        $totalEstimate = 0;
        
        foreach ($activeSchedules as $schedule) {
            // Convert to kg if needed
            $estimate = $schedule->quantity_estimate ?? 0;
            $unit = $schedule->quantity_unit ?? 'kg';
            
            if ($unit === 'kg') {
                $totalEstimate += $estimate;
            }
            // Add conversions for other units if needed
        }
        
        return $totalEstimate;
    }

    /**
     * Get the full URL for the product image
     */
    public function getFullImageUrlAttribute()
    {
        $imageUrl = $this->image_url;
        
        if (!$imageUrl) {
            return null;
        }
        
        // Already a full URL - return as is
        if (str_starts_with($imageUrl, 'http')) {
            return $imageUrl;
        }
        
        // Construct full URL based on environment
        $baseUrl = request()->getSchemeAndHttpHost();
        
        // For local development, use the actual request URL
        if (str_contains($baseUrl, 'localhost') || str_contains($baseUrl, '127.0.0.1') || str_contains($baseUrl, 'xampp')) {
            return $baseUrl . '/' . $imageUrl;
        } else {
            // For production, use the configured APP_URL
            $appUrl = config('app.url');
            return $appUrl . '/' . $imageUrl;
        }
    }

    /**
     * Get all variations with their stock info including reserved quantities
     */
    public function getVariationsWithReservedQuantities(): array
    {
        return [
            [
                'type' => 'regular',
                'name' => 'Regular',
                'actual_stock' => $this->stock_kg ?? 0,
                'reserved' => $this->getReservedQuantity('regular'),
                'available' => $this->getAvailableStock('regular'),
            ],
            [
                'type' => 'premium',
                'name' => 'Premium',
                'actual_stock' => $this->premium_stock_kg ?? 0,
                'reserved' => $this->getReservedQuantity('premium'),
                'available' => $this->getAvailableStock('premium'),
            ],
            [
                'type' => 'type_a',
                'name' => 'Type A',
                'actual_stock' => $this->type_a_stock_kg ?? 0,
                'reserved' => $this->getReservedQuantity('type_a'),
                'available' => $this->getAvailableStock('type_a'),
            ],
            [
                'type' => 'type_b',
                'name' => 'Type B',
                'actual_stock' => $this->type_b_stock_kg ?? 0,
                'reserved' => $this->getReservedQuantity('type_b'),
                'available' => $this->getAvailableStock('type_b'),
            ],
        ];
    }

}
