<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CropSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'product_id',
        'crop_name',              // Added to auto-fill the crop name
        'planting_date',
        'expected_harvest_start',
        'expected_harvest_end',
        'quantity_estimate',
        'quantity_unit',
        'status',                 // Added for status tracking (Planted, Growing, Ready for Harvest, Harvested)
        'is_active',
        'notes',
    ];

    /**
     * A crop schedule belongs to a product.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    /**
     * A crop schedule belongs to a seller (User).
     */
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }
}
