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
        'standard_weight_kg',
        'base_price_per_unit',
        'premium_price_per_unit',
        'type_a_price_per_unit',
        'type_b_price_per_unit',
        'unit_label',
        'unit_description',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'standard_weight_kg' => 'decimal:4',
        'base_price_per_unit' => 'decimal:2',
        'premium_price_per_unit' => 'decimal:2',
        'type_a_price_per_unit' => 'decimal:2',
        'type_b_price_per_unit' => 'decimal:2',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
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

    /**
     * Get all available units for a specific vegetable with full information
     */
    public static function getAvailableUnitsWithInfo(string $vegetableSlug): array
    {
        return self::where('vegetable_slug', $vegetableSlug)
                  ->where('is_enabled', true)
                  ->orderBy('sort_order')
                  ->get()
                  ->map(function ($unit) {
                      return [
                          'key' => $unit->unit,
                          'label' => $unit->unit_label ?? ucfirst(str_replace('_', ' ', $unit->unit)),
                          'weight_kg' => $unit->standard_weight_kg,
                          'description' => $unit->unit_description,
                          'prices' => [
                              'regular' => $unit->base_price_per_unit,
                              'premium' => $unit->premium_price_per_unit,
                              'type_a' => $unit->type_a_price_per_unit,
                              'type_b' => $unit->type_b_price_per_unit,
                          ],
                          'enabled' => $unit->is_enabled,
                          'sort_order' => $unit->sort_order,
                      ];
                  })
                  ->toArray();
    }

    /**
     * Get price for a specific unit and variation
     */
    public static function getUnitPrice(string $vegetableSlug, string $unit, string $variation = 'regular'): float
    {
        $unitConversion = self::where('vegetable_slug', $vegetableSlug)
                             ->where('unit', $unit)
                             ->where('is_enabled', true)
                             ->first();

        if (!$unitConversion) {
            return 0;
        }

        switch ($variation) {
            case 'premium':
                return $unitConversion->premium_price_per_unit ?? $unitConversion->base_price_per_unit ?? 0;
            case 'type_a':
                return $unitConversion->type_a_price_per_unit ?? $unitConversion->base_price_per_unit ?? 0;
            case 'type_b':
                return $unitConversion->type_b_price_per_unit ?? $unitConversion->base_price_per_unit ?? 0;
            default:
                return $unitConversion->base_price_per_unit ?? 0;
        }
    }

    /**
     * Get complete unit information for a specific vegetable and unit
     */
    public static function getUnitInfo(string $vegetableSlug, string $unit): ?array
    {
        $unitConversion = self::where('vegetable_slug', $vegetableSlug)
                             ->where('unit', $unit)
                             ->where('is_enabled', true)
                             ->first();

        if (!$unitConversion) {
            return null;
        }

        return [
            'key' => $unitConversion->unit,
            'label' => $unitConversion->unit_label ?? ucfirst(str_replace('_', ' ', $unitConversion->unit)),
            'weight_kg' => $unitConversion->standard_weight_kg,
            'description' => $unitConversion->unit_description,
            'prices' => [
                'regular' => $unitConversion->base_price_per_unit,
                'premium' => $unitConversion->premium_price_per_unit,
                'type_a' => $unitConversion->type_a_price_per_unit,
                'type_b' => $unitConversion->type_b_price_per_unit,
            ],
            'enabled' => $unitConversion->is_enabled,
            'sort_order' => $unitConversion->sort_order,
        ];
    }

    /**
     * Scope to get only enabled units
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope to get units for a specific vegetable
     */
    public function scopeForVegetable($query, string $vegetableSlug)
    {
        return $query->where('vegetable_slug', $vegetableSlug);
    }
}