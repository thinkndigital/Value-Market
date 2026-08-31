<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateStoreProduct extends Model
{
    protected $fillable = [
        'affiliate_store_id',
        'affiliate_link_id',
        'sort_order',
    ];

    public function affiliateStore()
    {
        return $this->belongsTo(AffiliateStore::class);
    }

    public function affiliateLink()
    {
        return $this->belongsTo(AffiliateLink::class);
    }
}
