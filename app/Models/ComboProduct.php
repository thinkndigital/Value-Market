<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComboProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'short_description',
        'description',
        'seller_id',
        'product_type',
        'product_ids',
        'tax',
        'tags',
        'pro_input_tax',
        'is_prices_inclusive_tax',
        'price',
        'special_price',
        'deliverable_type',
        'deliverable_zones',
        'pickup_location',
        'cod_allowed',
        'is_returnable',
        'is_cancelable',
        'is_attachment_required',
        'cancelable_till',
        'image',
        'other_images',
        'attribute',
        'attribute_value_ids',
        'simple_stock_management_status',
        'sku',
        'stock',
        'availability',
        'status',
        'store_id',
        'slug',
        'selected_products',
        'breadth',
        'length',
        'height',
        'weight',
        'has_similar_product',
        'similar_product_ids',
        'total_allowed_quantity',
        'minimum_order_quantity',
        'quantity_step_size',
    ];
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
    public function cart()
    {
        return $this->hasOne(Cart::class, 'product_variant_id');
    }
    public function sellerData()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }
    public function product()
    {
        return $this->belongsTo(ComboProduct::class, 'product_id');
    }

    public function zones()
    {
        return $this->belongsToMany(Zone::class, 'product_zone', 'product_id', 'zone_id');
    }


    // Relationships
    // public function sellerData()
    // {
    //     return $this->belongsTo(Seller::class, 'seller_id', 'id');
    // }

    public function sellerStore()
    {
        return $this->hasOne(SellerStore::class, 'seller_id', 'seller_id');
    }

    public function favorites()
    {
        // User <-> ComboProduct (favorites pivot, product_id column is used for combo_products too)
        return $this->belongsToMany(User::class, 'favorites', 'product_id', 'user_id');
    }
    public function user()
    {
        return $this->hasOneThrough(User::class, Seller::class, 'id', 'id', 'seller_id', 'user_id');
    }

    public function taxes()
    {
        return $this->belongsToMany(Tax::class, 'combo_products', 'id', 'tax')
            ->whereRaw('FIND_IN_SET(taxes.id, combo_products.tax) > 0');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'combo_products', 'id', 'product_ids')
            ->whereRaw('FIND_IN_SET(products.id, combo_products.product_ids) > 0');
    }

    public function attributeValues()
    {
        return $this->belongsToMany(Attribute_values::class, 'combo_products', 'id', 'attribute_value_ids')
            ->whereRaw('FIND_IN_SET(attribute_values.id, combo_products.attribute_value_ids) > 0');
    }

    public function productVariants()
    {
        return $this->belongsToMany(Product_variants::class, 'combo_products', 'id', 'product_ids')
            ->whereRaw('FIND_IN_SET(product_variants.product_id, combo_products.product_ids) > 0');
    }

    public function comboProductAttributeValues()
    {
        return $this->belongsToMany(ComboProductAttributeValue::class, 'combo_products', 'id', 'attribute_value_ids')
            ->whereRaw('FIND_IN_SET(combo_product_attribute_values.id, combo_products.attribute_value_ids) > 0');
    }

    public function comboProductAttributes()
    {
        return $this->hasMany(ComboProductAttribute::class, 'id', 'attribute_value_ids')
            ->whereRaw('FIND_IN_SET(combo_product_attributes.id, combo_products.attribute_value_ids) > 0');
    }

    public function customFieldValues()
    {
        return $this->hasMany(ComboProductCustomFieldValue::class);
    }
    public function ratings()
    {
        return $this->hasMany(ComboProductRating::class, 'product_id'); 
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
}
