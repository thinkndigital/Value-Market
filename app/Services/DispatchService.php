<?php

namespace App\Services;

use App\Models\OrderItems;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Phase 8 (docs/PHASE_8_DELIVERY.md): zone-aware auto-dispatch. Reuses the same zone-matching technique
 * app/function_helper.php's getDeliveryBoys() already uses (FIND_IN_SET against users.serviceable_zones),
 * not a new geography model. The existing manual assignment endpoints
 * (Admin/Seller\OrderController's delivery_boy_id updates) are untouched - this is an additive alternative,
 * not a replacement.
 */
class DispatchService
{
    /**
     * Active, zone-matching delivery boys ranked by current load - fewest active (not yet
     * delivered/cancelled/returned) assigned deliveries first, so dispatch spreads work rather than always
     * picking the same driver.
     */
    public function rankAvailableDeliveryBoys(?int $zoneId = null): Collection
    {
        $query = User::where('role_id', Role::DELIVERY_BOY)->where('status', 1);

        if ($zoneId !== null) {
            $query->whereRaw('FIND_IN_SET(?, serviceable_zones)', [$zoneId]);
        }

        return $query->withCount([
            'deliveryOrderItems as active_deliveries_count' => function ($q) {
                $q->whereNotIn('active_status', ['delivered', 'cancelled', 'returned']);
            },
        ])->orderBy('active_deliveries_count')->get();
    }

    /**
     * Assigns the least-loaded zone-matching delivery boy to an order item, reusing the exact same
     * OrderService::updateOrder() call Admin/Seller\OrderController's manual assignment already uses - one
     * assignment code path, whether a human picked the driver or dispatch did.
     */
    public function autoAssign(int $orderItemId, ?int $zoneId = null): ?User
    {
        $best = $this->rankAvailableDeliveryBoys($zoneId)->first();
        if (!$best) {
            return null;
        }

        app(OrderService::class)->updateOrder(
            ['delivery_boy_id' => $best->id],
            ['id' => $orderItemId],
            false,
            'order_items',
            false,
            0,
            OrderItems::class
        );

        return $best;
    }
}
