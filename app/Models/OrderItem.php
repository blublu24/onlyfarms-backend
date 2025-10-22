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
        'unit',         // Unit of measurement (e.g., kg, sacks, tali, piece, packet)
        'image_url',    // Snapshot of product image (optional convenience)
        
        // Product variation fields
        'variation_type',          // Product variation: premium, typeA, typeB
        'variation_name',          // Display name: Premium, Type A, Type B
        
        // Seller verification fields
        'estimated_weight_kg',     // Estimated weight based on unit conversion
        'actual_weight_kg',        // Actual weight as confirmed by seller
        'price_per_kg_at_order',   // Price per kg at time of order
        'estimated_price',         // Estimated price based on estimated weight
        'reserved',                // Whether this item is reserved in stock
        'seller_verification_status', // Verification status
        'seller_notes',            // Seller notes during verification
        'seller_confirmed_at',     // When seller confirmed the actual weight
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'estimated_weight_kg' => 'decimal:4',
        'actual_weight_kg' => 'decimal:4',
        'price_per_kg_at_order' => 'decimal:2',
        'estimated_price' => 'decimal:2',
        'reserved' => 'boolean',
        'seller_confirmed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Order::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class, 'order_item_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id', 'user_id');
    }

    /**
     * Calculate the final price based on actual weight
     */
    public function getFinalPrice(): float
    {
        if ($this->actual_weight_kg) {
            return $this->actual_weight_kg * $this->price_per_kg_at_order;
        }
        return $this->estimated_price ?? 0;
    }

    /**
     * Check if this item is awaiting seller verification
     */
    public function isAwaitingVerification(): bool
    {
        return $this->seller_verification_status === 'pending' && $this->reserved;
    }

    /**
     * Check if seller has accepted this item
     */
    public function isSellerAccepted(): bool
    {
        return $this->seller_verification_status === 'seller_accepted';
    }

    /**
     * Check if seller has rejected this item
     */
    public function isSellerRejected(): bool
    {
        return $this->seller_verification_status === 'seller_rejected';
    }

    /**
     * Get the weight difference between actual and estimated
     */
    public function getWeightDifference(): float
    {
        if (!$this->actual_weight_kg) {
            return 0;
        }
        return $this->actual_weight_kg - $this->estimated_weight_kg;
    }

}
