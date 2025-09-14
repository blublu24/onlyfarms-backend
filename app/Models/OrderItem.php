<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',     // Which order this line item belongs to
        'product_id',   // Which product was bought
        'seller_id',    // Which seller owns the product (helps payouts)
        'product_name', // Snapshot of product name at the time of order
        'price',        // Snapshot of price at the time of order
        'quantity',     // How many of this product
        'unit',         // Unit of measurement (e.g., kg, sacks)
        'image_url',    // Snapshot of product image (optional convenience)
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Order::class);
    }
}
