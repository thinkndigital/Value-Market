<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'store_id',
        'name',
        'short_description',
        'slug',
        'type',
        'tax',
        'category_id',
        'seller_id',
        'made_in',
        'brand',
        'indicator',
        'image',
        'total_allowed_quantity',
        'minimum_order_quantity',
        'quantity_step_size',
        'warranty_period',
        'guarantee_period',
        'other_images',
        'video_type',
        'video',
        'tags',
        'status',
        'description',
        'extra_description',
        'deliverable_type',
        'deliverable_zones',
        'hsn_code',
        'pickup_location',
        'stock_type',
        'sku',
        'stock',
        'availability',
        'is_returnable',
        'is_cancelable',
        'is_attachment_required',
        'cancelable_till',
        'download_allowed',
        'download_type',
        'download_link',
        'cod_allowed',
        'is_prices_inclusive_tax',
        'product_identity',
        'row_order',
        'rating',
        'no_of_ratings',
        'minimum_free_delivery_order_qty',
        'delivery_charges',
    ];

    // public function category()
    // {
    //     return $this->hasMany(Product_variants::class);
    // }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
    public function sellerData()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function productVariants()
    {
        return $this->hasMany(Product_variants::class, 'product_id');
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class, 'tax');
    }
    public function taxInfo()
    {
        return $this->belongsTo(Tax::class, 'tax');
    }
    public function productAttributes()
    {
        return $this->hasMany(Product_attributes::class, 'product_id');
    }
    public function ratings()
    {
        return $this->hasMany(ProductRating::class);
    }
    public function favorites()
    {
        return $this->belongsToMany(User::class, 'favorites');
    }
    public function sellerStore()
    {
        return $this->belongsTo(Seller::class, 'seller_id', 'id');
    }
    public function variants()
    {
        return $this->hasMany(Product_variants::class);
    }
    public function orderItems()
    {
        return $this->hasManyThrough(OrderItems::class, Product_variants::class, 'product_id', 'product_variant_id');
    }
    public function faqs()
    {
        return $this->hasMany(ProductFaq::class);
    }
    public function orders()
    {
        return $this->hasManyThrough(OrderItems::class, Product_variants::class, 'product_id', 'product_variant_id');
    }
    public function taxes()
    {
        return Tax::whereRaw("FIND_IN_SET(id, products.tax)");
    }
    public function attributes()
    {
        return $this->hasMany(Product_attributes::class);
    }

    public function weeklyOrderItems()
    {
        return $this->hasManyThrough(
            OrderItemS::class,
            Product_variants::class,
            'product_id',
            'product_variant_id',
            'id',
            'id'
        )
            ->where('order_items.created_at', '>=', now()->subDays(7))
            ->where('order_items.order_type', 'regular_order');
    }
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand');
    }
    public function brandRelation()
    {
        return $this->belongsTo(Brand::class, 'brand');
    }
    public function zones()
    {
        return $this->belongsToMany(Zone::class, 'product_zone', 'product_id', 'zone_id');
    }

    public function sellerStoreData()
    {
        return $this->hasOne(SellerStore::class, 'seller_id', 'seller_id');
    }
    public function getTaxPercentages()
    {
        if (empty($this->tax)) {
            return [];
        }

        $taxIds = explode(',', $this->tax);
        return Tax::whereIn('id', $taxIds)->pluck('percentage')->toArray();
    }

    public function getTaxTitles()
    {
        if (empty($this->tax)) {
            return [];
        }

        $taxIds = explode(',', $this->tax);
        return Tax::whereIn('id', $taxIds)->pluck('title')->toArray();
    }

    public function customFieldValues()
    {
        return $this->hasMany(ProductCustomFieldValue::class);
    }

}
