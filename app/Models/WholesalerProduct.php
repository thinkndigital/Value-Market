<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Images stored as plain path strings (MediaService::getMediaImageUrl()), matching Product/Category/etc. */
class WholesalerProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'wholesaler_id',
        'category_id',
        'name',
        'description',
        'image',
        'wholesale_price',
        'min_order_qty',
        'stock',
        'status',
        'affiliate_enabled',
        'affiliate_commission_rate',
        'slug',
    ];

    public function wholesaler()
    {
        return $this->belongsTo(Wholesaler::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /** Every seller Product row created by importing this listing (docs: WHOLESALER_MODULE.md). */
    public function imports()
    {
        return $this->hasMany(Product::class, 'wholesaler_product_id');
    }
}
