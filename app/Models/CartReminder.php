<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartReminder extends Model
{
    protected $table = 'cart_reminders';

    protected $fillable = [
        'user_id',
        'product_variant_id',
        'reminded_at',
    ];

    public $timestamps = false;

    // Relationships (optional)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(Product_variants::class);
    }
}
