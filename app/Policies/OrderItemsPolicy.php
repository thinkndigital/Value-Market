<?php

namespace App\Policies;

use App\Models\OrderItems;
use App\Models\Seller;
use App\Models\User;

/**
 * Phase 2 (docs/PHASE_2_RBAC_ARCHITECTURE.md, Task 5): this is the actual tenant boundary for orders in
 * this multi-vendor codebase - a single Order can contain items from several different sellers, so
 * ownership has to be checked per order_item (seller_id / delivery_boy_id), not per order. Centralizes the
 * same rule that Seller\OrderController etc. already enforce ad-hoc by scoping list queries with
 * `where('seller_id', $sellerId)` - this Policy is for the single-record lookups (view/update a specific
 * order item by id) where that scoping is easy to forget, which is exactly the shape of IDOR this phase's
 * sweep (docs/PHASE_2_IDOR_AUDIT.md) is looking for.
 */
class OrderItemsPolicy
{
    /**
     * A customer may view an order item only if it belongs to an order they placed.
     */
    public function view(User $user, OrderItems $orderItem): bool
    {
        if ($this->manage($user, $orderItem)) {
            return true;
        }

        if ($user->isCustomer()) {
            $orderUserId = $orderItem->order()->value('user_id');

            return $orderUserId !== null && (int) $orderUserId === (int) $user->id;
        }

        return false;
    }

    /**
     * A seller may manage (update status/tracking on) an order item only if it belongs to their own
     * seller_data record; a delivery boy only if they are the assigned delivery boy for it. Customers do
     * not manage order items directly - they place orders and (separately) request returns/cancellations,
     * which are their own resources with their own ownership rules.
     */
    public function manage(User $user, OrderItems $orderItem): bool
    {
        if ($user->isSeller()) {
            $sellerId = Seller::where('user_id', $user->id)->value('id');

            if ($sellerId !== null && (int) $orderItem->seller_id === (int) $sellerId) {
                return true;
            }
        }

        if ($user->isDeliveryBoy() && $orderItem->delivery_boy_id !== null) {
            return (int) $orderItem->delivery_boy_id === (int) $user->id;
        }

        return false;
    }

    public function update(User $user, OrderItems $orderItem): bool
    {
        return $this->manage($user, $orderItem);
    }
}
