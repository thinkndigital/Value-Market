<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 4 (docs/PHASE_4_VENDOR_SYSTEM.md): a real login-capable staff member (their own `users` row)
 * distinct from the seller owner, scoped to a seller and optionally one branch. See
 * TenantContext::sellerIdFor() for how an employee's login resolves to their employer's seller_id, and
 * docs/PHASE_4_VENDOR_SYSTEM.md for the explicit scope boundary on what that resolution does and doesn't
 * yet cover.
 */
class Employee extends Model
{
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    protected $fillable = [
        'seller_id',
        'branch_id',
        'user_id',
        'position',
        'permissions',
        'status',
        'disk',
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
