<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LalamoveDelivery extends Model
{
    protected $fillable = [
        'order_id',
        'quotation_id',
        'lalamove_order_id',
        'delivery_fee',
        'service_type',
        'pickup_address',
        'dropoff_address',
        'driver_id',
        'driver_name',
        'driver_phone',
        'plate_number',
        'status',
        'share_link',
        'price_breakdown',
        'distance',
    ];

    protected $casts = [
        'delivery_fee' => 'decimal:2',
        'price_breakdown' => 'array',
        'distance' => 'array',
    ];

    /**
     * Get the order that owns the Lalamove delivery.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Check if delivery is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if driver is assigned.
     */
    public function hasDriver(): bool
    {
        return !empty($this->driver_id);
    }

    /**
     * Get tracking URL.
     */
    public function getTrackingUrl(): ?string
    {
        return $this->share_link;
    }

    /**
     * Get status display name.
     */
    public function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'assigned' => 'Driver Assigned',
            'picked_up' => 'Picked Up',
            'completed' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }
}
