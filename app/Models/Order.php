<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'address_id',
        'total',
        'status',
        'delivery_address',
        'delivery_method',
        'use_third_party_delivery',
        'note',
        'payment_method',
        'payment_link',
        'payment_status',
        'preorder_id',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'use_third_party_delivery' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(\App\Models\OrderItem::class);
    }

    // In Order.php
    public function address()
    {
        return $this->belongsTo(Address::class, 'address_id', 'address_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }

    /**
     * Check if order is awaiting seller verification
     */
    public function isAwaitingSellerVerification(): bool
    {
        return $this->status === 'for_seller_verification';
    }

    /**
     * Check if order is awaiting buyer confirmation
     */
    public function isAwaitingBuyerConfirmation(): bool
    {
        return $this->status === 'awaiting_buyer_confirmation';
    }

    /**
     * Get total estimated weight of all items
     */
    public function getTotalEstimatedWeight(): float
    {
        return $this->items()->sum('estimated_weight_kg');
    }

    /**
     * Get total actual weight of all items
     */
    public function getTotalActualWeight(): float
    {
        return $this->items()->sum('actual_weight_kg');
    }

    /**
     * Check if all items have been verified by seller
     */
    public function allItemsVerified(): bool
    {
        return $this->items()->where('seller_verification_status', 'pending')->count() === 0;
    }

    /**
     * Check if any items were rejected by seller
     */
    public function hasRejectedItems(): bool
    {
        return $this->items()->where('seller_verification_status', 'seller_rejected')->exists();
    }

    /**
     * Get the Lalamove delivery for this order.
     */
    public function lalamoveDelivery()
    {
        return $this->hasOne(LalamoveDelivery::class);
    }
}
