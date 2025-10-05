<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Harvest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'crop_schedule_id',
        'harvested_at',
        'yield_qty',
        'yield_unit',
        'grade',
        'waste_qty',
        'moisture_pct',
        'lot_code',
        'verified',
        'verified_at',
        'verified_by',
        'published',
        'published_at',
        'product_id',
        'created_by_type',
        'created_by_id',
        'photos_json',
        'harvest_image',
    ];

    protected $casts = [
        'harvested_at' => 'datetime',
        'yield_qty' => 'decimal:2',
        'waste_qty' => 'decimal:2',
        'moisture_pct' => 'decimal:2',
        'verified' => 'boolean',
        'verified_at' => 'datetime',
        'published' => 'boolean',
        'published_at' => 'datetime',
        'photos_json' => 'array',
    ];

    /**
     * A harvest belongs to a crop schedule.
     */
    public function cropSchedule()
    {
        return $this->belongsTo(CropSchedule::class);
    }

    /**
     * A harvest can be verified by an admin.
     */
    public function verifier()
    {
        return $this->belongsTo(Admin::class, 'verified_by');
    }

    /**
     * A harvest can be linked to a product.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    /**
     * Get the creator (seller or admin) based on created_by_type and created_by_id.
     */
    public function creator()
    {
        if ($this->created_by_type === 'seller') {
            return $this->belongsTo(Seller::class, 'created_by_id');
        } elseif ($this->created_by_type === 'admin') {
            return $this->belongsTo(Admin::class, 'created_by_id');
        }
        return null;
    }

    /**
     * Scope to get only verified harvests.
     */
    public function scopeVerified($query)
    {
        return $query->where('verified', true);
    }

    /**
     * Scope to get only published harvests.
     */
    public function scopePublished($query)
    {
        return $query->where('published', true);
    }
}
