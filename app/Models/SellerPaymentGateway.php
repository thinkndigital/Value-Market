<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SellerPaymentGateway extends Model
{
    use HasFactory;

    /** Every gateway a seller can self-configure; keys match the credential field names each
     *  app/Libraries/*.php class already reads from the platform-global `payment_method` setting, so a
     *  seller override slots into the exact same field. */
    public const GATEWAYS = ['razorpay', 'hyperpay', 'paytabs', 'tap'];

    protected $fillable = ['seller_id', 'gateway', 'credentials', 'is_enabled'];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'is_enabled' => 'boolean',
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }
}
