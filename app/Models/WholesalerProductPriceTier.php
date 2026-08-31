<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WholesalerProductPriceTier extends Model
{
    protected $fillable = [
        'wholesaler_product_id',
        'seller_id',
        'min_quantity',
        'unit_price',
    ];

    public function wholesalerProduct()
    {
        return $this->belongsTo(WholesalerProduct::class);
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }
}
