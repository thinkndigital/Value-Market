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

    public function priceTiers()
    {
        return $this->hasMany(WholesalerProductPriceTier::class);
    }

    /**
     * Master architecture prompt Phase 6 (section 18 "Wholesale" group): the price a given seller pays
     * per unit for a given order quantity. Picks the tier with the highest `min_quantity` that the
     * requested quantity still satisfies, preferring a seller-specific tier over a generic one at that
     * same quantity threshold; falls back to the listing's flat `wholesale_price` when no tier matches
     * (e.g. no tiers configured at all, or the quantity is below every tier's minimum).
     */
    public function priceFor(int $sellerId, int $quantity): float
    {
        $tiers = $this->relationLoaded('priceTiers')
            ? $this->priceTiers
            : $this->priceTiers()->get();

        $bestSellerSpecific = $tiers
            ->where('seller_id', $sellerId)
            ->where('min_quantity', '<=', $quantity)
            ->sortByDesc('min_quantity')
            ->first();

        if ($bestSellerSpecific) {
            return (float) $bestSellerSpecific->unit_price;
        }

        $bestGeneric = $tiers
            ->whereNull('seller_id')
            ->where('min_quantity', '<=', $quantity)
            ->sortByDesc('min_quantity')
            ->first();

        if ($bestGeneric) {
            return (float) $bestGeneric->unit_price;
        }

        return (float) $this->wholesale_price;
    }

    /** Every seller Product row created by importing this listing (docs: WHOLESALER_MODULE.md). */
    public function imports()
    {
        return $this->hasMany(Product::class, 'wholesaler_product_id');
    }
}
