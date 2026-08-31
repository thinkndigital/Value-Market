<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WholesaleOrder extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 0;
    public const STATUS_ACCEPTED = 1;
    public const STATUS_SHIPPED = 2;
    public const STATUS_DELIVERED = 3;
    public const STATUS_REJECTED = 4;
    public const STATUS_CANCELLED = 5;

    protected $fillable = [
        'wholesaler_id',
        'wholesaler_product_id',
        'seller_id',
        'store_id',
        'quantity',
        'unit_price',
        'total_amount',
        'retail_price',
        'status',
        'payment_status',
        'seller_note',
        'wholesaler_note',
        'fulfilled_product_id',
    ];

    public function wholesaler()
    {
        return $this->belongsTo(Wholesaler::class);
    }

    public function wholesalerProduct()
    {
        return $this->belongsTo(WholesalerProduct::class);
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function fulfilledProduct()
    {
        return $this->belongsTo(Product::class, 'fulfilled_product_id');
    }
}
