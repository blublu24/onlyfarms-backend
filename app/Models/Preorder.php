<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Preorder extends Model
{
    use HasFactory;

    protected $fillable = [
        'consumer_id',
        'product_id',
        'seller_id',
        'quantity',
        'expected_availability_date',
        
        // Variation and unit fields
        'variation_type',
        'variation_name',
        'unit_key',
        'unit_weight_kg',
        'unit_price',
        'status',
        'harvest_date',
        'harvest_date_ref',
        'reserved_qty',
        'allocated_qty',
        'notes',
        'version',
        
        // Audit fields
        'status_updated_at',
        'status_updated_by',
        'matched_at',
        'ready_at',
    ];

    /* ==============================
     | Relationships
     ============================== */

    /**
     * A preorder belongs to a product.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    /**
     * A preorder belongs to the consumer (User).
     */
    public function consumer()
    {
        return $this->belongsTo(User::class, 'consumer_id', 'id');
    }

    /**
     * A preorder belongs to the seller (User).
     */
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }

    /**
     * A preorder can be fulfilled into an order.
     */
    public function fulfilledOrder()
    {
        return $this->hasOne(Order::class, 'preorder_id', 'id');
    }

    /**
     * A preorder can be linked to a harvest that will fulfill it.
     */
    public function harvest()
    {
        return $this->belongsTo(Harvest::class, 'harvest_date_ref', 'id');
    }

    /**
     * A preorder can have its status updated by a user (seller, admin, or buyer).
     */
    public function statusUpdater()
    {
        return $this->belongsTo(User::class, 'status_updated_by', 'id');
    }

    /* ==============================
     | Attribute Casting
     ============================== */

    protected $casts = [
        'expected_availability_date' => 'date',
        'harvest_date' => 'date',
        'unit_weight_kg' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'reserved_qty' => 'decimal:4',
        'allocated_qty' => 'decimal:4',
        'version' => 'integer',
        'status_updated_at' => 'datetime',
        'matched_at' => 'datetime',
        'ready_at' => 'datetime',
    ];

    /* ==============================
     | Scopes
     ============================== */

    /**
     * Scope: Filter by variation type
     */
    public function scopeByVariationType($query, $variationType)
    {
        return $query->where('variation_type', $variationType);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by unit key
     */
    public function scopeByUnitKey($query, $unitKey)
    {
        return $query->where('unit_key', $unitKey);
    }

    /**
     * Scope: Filter by harvest date range
     */
    public function scopeByHarvestDateRange($query, $startDate, $endDate = null)
    {
        $query->where('harvest_date', '>=', $startDate);
        if ($endDate) {
            $query->where('harvest_date', '<=', $endDate);
        }
        return $query;
    }

    /* ==============================
     | Helper Methods
     ============================== */

    /**
     * Calculate subtotal based on quantity and unit price
     */
    public function getSubtotalAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    /**
     * Get total weight in kg for this preorder
     */
    public function getTotalWeightKgAttribute(): float
    {
        return $this->quantity * $this->unit_weight_kg;
    }

    /**
     * Convert quantity to base units (kg) using unit weight
     */
    public function getQuantityInKg(): float
    {
        return $this->quantity * $this->unit_weight_kg;
    }

    /**
     * Check if preorder can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'reserved']) && 
               (!$this->harvest || !$this->harvest->published);
    }

    /**
     * Check if preorder can be fulfilled
     */
    public function canBeFulfilled(): bool
    {
        return $this->status === 'ready' && $this->allocated_qty > 0;
    }

    /**
     * Check if preorder can be matched to harvest
     */
    public function canBeMatched(): bool
    {
        return $this->status === 'pending' && $this->harvest_date_ref === null;
    }

    /**
     * Check if preorder is ready for fulfillment
     */
    public function isReadyForFulfillment(): bool
    {
        return $this->status === 'ready' && $this->ready_at !== null;
    }

    /**
     * Get unit display name with weight info
     */
    public function getUnitDisplayNameAttribute(): string
    {
        $unitNames = [
            'kg' => 'Kilogram',
            'sack' => 'Sack',
            'small_sack' => 'Small Sack',
            'tali' => 'Tali',
            'pieces' => 'Pieces',
        ];

        $name = $unitNames[$this->unit_key] ?? ucfirst($this->unit_key);
        
        if ($this->unit_weight_kg) {
            $name .= " ({$this->unit_weight_kg}kg)";
        }
        
        return $name;
    }

    /**
     * Get variation display name
     */
    public function getVariationDisplayNameAttribute(): string
    {
        if ($this->variation_name) {
            return $this->variation_name;
        }

        $variationNames = [
            'premium' => 'Premium',
            'type_a' => 'Type A',
            'type_b' => 'Type B',
            'regular' => 'Regular',
        ];

        return $variationNames[$this->variation_type] ?? ucfirst($this->variation_type);
    }

    /**
     * Update status with version checking for optimistic locking and audit tracking
     */
    public function updateStatus($newStatus, $expectedVersion = null, $updatedBy = null)
    {
        if ($expectedVersion && $this->version !== $expectedVersion) {
            throw new \Exception('Preorder has been modified by another process');
        }

        $this->status = $newStatus;
        $this->version++;
        $this->status_updated_at = now();
        $this->status_updated_by = $updatedBy;

        // Set timestamps based on status
        if ($newStatus === 'reserved' && !$this->matched_at) {
            $this->matched_at = now();
        } elseif ($newStatus === 'ready' && !$this->ready_at) {
            $this->ready_at = now();
        }

        return $this->save();
    }

    /**
     * Allocate quantity from harvest
     */
    public function allocateQuantity($quantity, $harvestId = null)
    {
        $this->allocated_qty = $quantity;
        if ($harvestId) {
            $this->harvest_date_ref = $harvestId;
        }
        $this->updateStatus('reserved', null, null);
        return $this->save();
    }

    /**
     * Mark as ready for fulfillment
     */
    public function markAsReady()
    {
        $this->updateStatus('ready', null, null);
        return $this->save();
    }
}
