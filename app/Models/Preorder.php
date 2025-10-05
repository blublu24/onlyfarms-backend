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
}
