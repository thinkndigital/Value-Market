<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Images are stored as plain path strings (resolved via MediaService::getMediaImageUrl()), matching how
 * Product/Category/etc. already do it in this app - not via a per-model Spatie media collection. */
class Wholesaler extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'description',
        'logo',
        'address',
        'commission_rate',
        'status',
        'buyer_visibility',
        'disk',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(WholesalerProduct::class);
    }

    public function sellerRequests()
    {
        return $this->hasMany(WholesalerSellerRequest::class);
    }

    public function isPrivate(): bool
    {
        return $this->buyer_visibility === 'private';
    }
}
