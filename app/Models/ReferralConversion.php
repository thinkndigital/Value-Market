<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralConversion extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'affiliate_link_id', 'order_id', 'buyer_user_id', 'order_total',
        'commission_rate_type', 'commission_rate_value', 'commission_amount', 'status', 'approved_at',
    ];

    public function link()
    {
        return $this->belongsTo(AffiliateLink::class, 'affiliate_link_id');
    }
}
