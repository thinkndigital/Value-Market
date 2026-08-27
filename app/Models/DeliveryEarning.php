<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 8 (docs/PHASE_8_DELIVERY.md): an immutable record of what the platform paid a driver for one
 * delivered order item - credited to their wallet at the same moment this row is created (see
 * DeliveryEarningService::creditForDeliveredItem()).
 */
class DeliveryEarning extends Model
{
    const RATE_FLAT = 'flat';
    const RATE_PERCENTAGE = 'percentage';

    public $timestamps = false;

    protected $fillable = [
        'delivery_boy_id', 'order_id', 'order_item_id', 'amount', 'rate_type', 'rate_value', 'earned_at',
    ];
}
