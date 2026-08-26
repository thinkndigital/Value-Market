<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SellerStore extends Model
{
    use HasApiTokens, HasFactory, Notifiable;
    protected $table = 'seller_store';
    public $timestamps = false;

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function zipcode()
    {
        return $this->belongsTo(Zipcode::class, 'zipcode');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id', 'seller_id');
    }

    public function comboProducts()
    {
        return $this->hasMany(ComboProduct::class, 'seller_id', 'seller_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'seller_id', 'seller_id');
    }
}
