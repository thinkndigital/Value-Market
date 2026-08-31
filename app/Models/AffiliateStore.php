<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateStore extends Model
{
    const STATUS_DRAFT = 0;
    const STATUS_PUBLISHED = 1;

    protected $fillable = [
        'user_id',
        'slug',
        'name',
        'description',
        'logo',
        'banner',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function storeProducts()
    {
        return $this->hasMany(AffiliateStoreProduct::class)->orderBy('sort_order');
    }
}
