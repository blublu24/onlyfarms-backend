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
        'crop_name',
        'planting_date',
        'expected_harvest_start',
        'expected_harvest_end',
        'quantity_estimate',
        'quantity_unit',
        'status',        // Planted, Growing, Ready for Harvest, Harvested
        'is_active',
        'notes',
    ];

    protected $casts = [
        'planting_date' => 'date',
        'expected_harvest_start' => 'date',
        'expected_harvest_end' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * A crop schedule belongs to a product (custom PK: product_id).
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    /**
     * A crop schedule belongs to a seller record.
     * NOTE: This references the Seller model (id), not User.
     */
    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id', 'id');
    }
}
