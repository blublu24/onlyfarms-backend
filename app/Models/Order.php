<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'user_id',           // Who placed the order (the buyer)
        'total',             // Total cost of the whole order
        'status',            // pending / paid / shipped / completed
        'delivery_address',  // Where it’s going
        'notes',             // Optional instructions
    ];

    protected $casts = [
        'total' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(\App\Models\OrderItem::class);
    }
}
