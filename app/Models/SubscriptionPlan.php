<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 11 (docs/PHASE_11_SUBSCRIPTIONS.md): admin-managed subscription tier a seller can be placed on.
 * The three seeded rows (Basic/Pro/Premium) are placeholder defaults, not fixed business decisions - the
 * admin edits name/price/limits/features from admin/subscription_plans.
 */
class SubscriptionPlan extends Model
{
    const BILLING_MONTHLY = 'monthly';
    const BILLING_YEARLY = 'yearly';

    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    protected $fillable = [
        'name', 'slug', 'billing_cycle', 'price', 'commission_rate', 'max_products',
        'description', 'features', 'status', 'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function sellers()
    {
        return $this->hasMany(Seller::class, 'subscription_plan_id');
    }
}
