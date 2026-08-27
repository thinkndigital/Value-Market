<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    // Phase 3 (docs/PHASE_3_COMMERCE_CORE.md): the order-origin discriminator. CHANNEL_AFFILIATE is defined
    // now so Phase 7 (Affiliate/Reseller Engine, net-new) doesn't need another migration just to widen this
    // column - no code path sets it yet, since no affiliate order-placement flow exists in this codebase.
    const CHANNEL_MARKETPLACE = 'marketplace';
    const CHANNEL_POS = 'pos';
    const CHANNEL_AFFILIATE = 'affiliate';

    public function orderItems()
    {
        return $this->hasMany(OrderItems::class);
    }
    public function orderCharges()
    {
        return $this->hasMany(OrderCharges::class);
    }
    public function orderBankTransfers()
    {
        return $this->hasMany(OrderBankTransfers::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItems::class);
    }

    public function promoCode()
    {
        return $this->belongsTo(Promocode::class);
    }
    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
    public function shippingAddress()
    {
        return $this->belongsTo(Address::class, 'address_id');
    }
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s'); 
    }
}
