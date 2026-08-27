<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LinkClick extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'affiliate_link_id', 'ip_address', 'user_agent', 'referrer', 'clicked_at',
    ];

    public function link()
    {
        return $this->belongsTo(AffiliateLink::class, 'affiliate_link_id');
    }
}
