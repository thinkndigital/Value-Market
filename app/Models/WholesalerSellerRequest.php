<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WholesalerSellerRequest extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'wholesaler_id',
        'seller_id',
        'status',
    ];

    public function wholesaler()
    {
        return $this->belongsTo(Wholesaler::class);
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }
}
