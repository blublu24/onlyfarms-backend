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
        'crop_name',              // ✅ added so it can be auto-filled
        'planting_date',
        'expected_harvest_start',
        'expected_harvest_end',
        'quantity_estimate',
        'quantity_unit',
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
