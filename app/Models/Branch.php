<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 4 (docs/PHASE_4_VENDOR_SYSTEM.md): a physical location owned by a seller_data tenant. Phase 5's
 * warehouses/stock_items will reference branches, not the other way around.
 */
class Branch extends Model
{
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    protected $fillable = [
        'seller_id',
        'name',
        'address',
        'city',
        'zipcode',
        'latitude',
        'longitude',
        'phone',
        'is_default',
        'status',
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'branch_id');
    }
}
