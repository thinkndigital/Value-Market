<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderItems extends Model
{
    protected $table = 'order_items';
    use HasApiTokens, HasFactory, Notifiable;
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function productVariant()
    {
        return $this->belongsTo(Product_variants::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sellerData()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function deliveryBoy()
    {
        return $this->belongsTo(User::class, 'delivery_boy_id');
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class, 'order_item_id');
    }

    public function orderTracking()
    {
        return $this->hasOne(OrderTracking::class, 'order_item_id');
    }
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
    public function sellerStore()
    {
        return $this->belongsTo(SellerStore::class, 'seller_id', 'seller_id');
    }
    
    public function sellerUser()
    {
        return $this->hasOneThrough(User::class, SellerStore::class, 'seller_id', 'id', 'seller_id', 'user_id');
    }
    public function product()
    {
        return $this->productVariant->product(); 
    }
    public function parcelItems()
    {
        return $this->hasMany(Parcelitem::class, 'order_item_id', 'id');
    }
    public function comboProduct()
    {
        return $this->belongsTo(ComboProduct::class, 'product_variant_id');
    }
    public function sellerStoreByOrderStore($storeId = null)
    {
        $query = $this->hasOne(SellerStore::class, 'seller_id', 'seller_id');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query;
    }
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s'); 
    }
}
