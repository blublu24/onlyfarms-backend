<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitConversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'vegetable_slug',
        'unit',
        'standard_weight_kg'
    ];

    protected $casts = [
        'standard_weight_kg' => 'decimal:4'
    ];

    /**
     * Get the standard weight for a specific vegetable and unit combination
     */
    public static function getStandardWeight(string $vegetableSlug, string $unit): float
    {
        return self::where('vegetable_slug', $vegetableSlug)
                  ->where('unit', $unit)
                  ->value('standard_weight_kg') ?? 0;
    }

    /**
     * Get all available units for a specific vegetable
     */
    public static function getAvailableUnits(string $vegetableSlug): array
    {
        return self::where('vegetable_slug', $vegetableSlug)
                  ->pluck('unit')
                  ->toArray();
    }

    /**
     * Get all vegetables that support a specific unit
     */
    public static function getVegetablesForUnit(string $unit): array
    {
        return self::where('unit', $unit)
                  ->pluck('vegetable_slug')
                  ->toArray();
    }

    /**
     * Calculate estimated weight for a given quantity and unit
     */
    public static function calculateEstimatedWeight(string $vegetableSlug, string $unit, float $quantity): float
    {
        $standardWeight = self::getStandardWeight($vegetableSlug, $unit);
        return $standardWeight * $quantity;
    }
}