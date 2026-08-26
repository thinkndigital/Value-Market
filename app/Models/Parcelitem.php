<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parcelitem extends Model
{
    protected $table = 'parcel_items';
    use HasFactory;
    protected $fillable = [
        'parcel_id',
        'order_item_id',
        'product_variant_id	',
        'unit_price',
        'quantity',
    ];

    public function orderItem() { 
        return $this->belongsTo(OrderItems::class); 
    }
    public function productVariant() { 
        return $this->belongsTo(Product_variants::class); 
    }
    public function parcel() {
        return $this->belongsTo(Parcel::class); 
    }
    public function comboProduct()
    {
        return $this->belongsTo(ComboProduct::class, 'product_variant_id');
    }
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}