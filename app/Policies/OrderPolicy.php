<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\Seller;
use App\Models\User;

/**
 * Phase 2 (docs/PHASE_2_RBAC_ARCHITECTURE.md, Task 5): order-level view access. An Order belongs to the
 * customer who placed it (`orders.user_id`); a seller or delivery boy may view it only if they own at
 * least one of its order_items (see OrderItemsPolicy for the item-level rule this delegates to - that is
 * the real tenant boundary in a multi-vendor order). This Policy intentionally does not expose an
 * `update` ability: nothing in the app mutates an Order as a single unit - all real mutations are
 * per-order-item (status, tracking, refunds), which is OrderItemsPolicy's job.
 */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        if ($user->isCustomer()) {
            return (int) $order->user_id === (int) $user->id;
        }

        if ($user->isSeller()) {
            $sellerId = Seller::where('user_id', $user->id)->value('id');

            if ($sellerId === null) {
                return false;
            }

            return $order->orderItems()->where('seller_id', $sellerId)->exists();
        }

        if ($user->isDeliveryBoy()) {
            return $order->orderItems()->where('delivery_boy_id', $user->id)->exists();
        }

        return false;
    }
}
