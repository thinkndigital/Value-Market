<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cart extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'cart';

    protected $fillable = [
        'user_id',
        'store_id',
        'product_variant_id',
        'qty',
        'is_saved_for_later',
    ];

    public function productVariant()
    {
        return $this->belongsTo(Product_variants::class, 'product_variant_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function reminder()
    {
        return $this->hasOne(CartReminder::class)
            ->whereColumn('cart.user_id', 'cart_reminders.user_id')
            ->whereColumn('cart.product_variant_id', 'cart_reminders.product_variant_id');
    }
    public function comboProduct()
    {
        return $this->belongsTo(ComboProduct::class, 'product_variant_id');
    }
    public function product()
    {
        return $this->productVariant?->product();
    }
}
