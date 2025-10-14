<?php

namespace App\Http\Controllers;

use App\Models\UnitConversion;
use Illuminate\Http\Request;

class UnitConversionController extends Controller
{
    /**
     * Get available units for a specific vegetable slug
     */
    public function getAvailableUnits(string $vegetableSlug)
    {
        try {
            $units = UnitConversion::getAvailableUnits($vegetableSlug);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'vegetable_slug' => $vegetableSlug,
                    'units' => $units
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching available units',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all unit conversions
     */
    public function index()
    {
        try {
            $conversions = UnitConversion::all();
            
            return response()->json([
                'success' => true,
                'data' => $conversions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching unit conversions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
