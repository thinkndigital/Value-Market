<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateLink extends Model
{
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    const TARGET_PLATFORM = 'platform';
    const TARGET_STORE = 'store';
    const TARGET_CATEGORY = 'category';
    const TARGET_PRODUCT = 'product';

    protected $fillable = [
        'user_id', 'target_type', 'target_id', 'code', 'clicks_count', 'conversions_count', 'status',
    ];

    public function clicks()
    {
        return $this->hasMany(LinkClick::class, 'affiliate_link_id');
    }

    public function conversions()
    {
        return $this->hasMany(ReferralConversion::class, 'affiliate_link_id');
    }
}
