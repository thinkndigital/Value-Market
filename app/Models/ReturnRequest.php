<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReturnRequest extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    // Phase 3 (docs/PHASE_3_COMMERCE_CORE.md): named replacements for the magic status numbers that were
    // duplicated ad hoc across Admin\ReturnRequestController and Seller\ReturnRequestController. Values are
    // unchanged from what those controllers already used - this doesn't change any stored data or API
    // response shape, just gives the numbers names.
    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;
    const STATUS_RETURNED = 3;
    const STATUS_PICKED_UP = 8;

    protected $fillable = [
        'user_id',
        'product_id',
        'product_variant_id',
        'order_id',
        'order_item_id',
        'status',
        'remarks',
        'reason',
        'quantity',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItems::class);
    }
}
