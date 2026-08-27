<?php

namespace App\Services;

use App\Models\OrderItems;
use App\Models\ReturnRequest;

/**
 * Phase 3 (docs/PHASE_3_COMMERCE_CORE.md): the return-request approve/reject/complete transition-guard
 * chain and its status/refund/stock side effects were duplicated near-verbatim between
 * Admin\ReturnRequestController::update() and Seller\ReturnRequestController::update(), keyed off magic
 * status numbers (0/1/2/3/8) instead of the App\Models\ReturnRequest status constants. One consolidated,
 * tested path for both callers.
 *
 * Notification-building (FCM push text/recipients) deliberately stays in each controller, unchanged - only
 * the guard checks and the actual state-changing side effects (refund, stock restock, order-item status,
 * delivery-boy assignment) move here.
 */
class ReturnRequestService
{
    /**
     * Returns an error message if transitioning from the return request's current status to $newStatus is
     * not allowed, or null if it is. Consolidates all six guards found across the two original controllers -
     * Admin\ReturnRequestController was missing the two "can't revert to pending" guards
     * (rejected->pending, approved->pending) that Seller\ReturnRequestController already enforced; both now
     * get all six.
     */
    public function guardTransition(ReturnRequest $returnRequest, int $newStatus): ?string
    {
        $current = (int) $returnRequest->status;

        if ($current === ReturnRequest::STATUS_RETURNED && $newStatus === ReturnRequest::STATUS_RETURNED) {
            return 'This Item Is Already Returned!';
        }
        if ($current === ReturnRequest::STATUS_APPROVED && $newStatus === ReturnRequest::STATUS_APPROVED) {
            return 'This Item Is Already Approved!';
        }
        if ($current === ReturnRequest::STATUS_REJECTED && $newStatus === ReturnRequest::STATUS_REJECTED) {
            return 'This Item Is Already Rejected!';
        }
        if ($current === ReturnRequest::STATUS_REJECTED && $newStatus === ReturnRequest::STATUS_APPROVED) {
            return 'You can not approve rejected return request!';
        }
        if ($current === ReturnRequest::STATUS_REJECTED && $newStatus === ReturnRequest::STATUS_PENDING) {
            return 'You cannot change the status of a rejected return request back to pending!';
        }
        if ($current === ReturnRequest::STATUS_APPROVED && $newStatus === ReturnRequest::STATUS_PENDING) {
            return 'You cannot change the status of a approved return request back to pending!';
        }

        return null;
    }

    /**
     * Saves the new status/remarks, then fires the same side effects the original controllers each fired
     * inline for status 3/1/2: wallet refund + stock restock + order-item status (Returned), delivery-boy
     * assignment + order-item status (Approved), or just order-item status (Rejected). Callers are
     * responsible for calling guardTransition() first and for building/sending their own notifications
     * afterward - this only applies the transition itself.
     */
    public function applyTransition(ReturnRequest $returnRequest, int $newStatus, ?string $remarks, $deliverBy = null): void
    {
        $returnRequest->status = $newStatus;
        $returnRequest->remarks = $remarks;
        $returnRequest->save();

        $itemId = $returnRequest->order_item_id;

        if ($newStatus === ReturnRequest::STATUS_RETURNED) {
            $data = fetchDetails(OrderItems::class, ['id' => $itemId], ['product_variant_id', 'quantity']);
            app(OrderService::class)->process_refund($itemId, 'returned');
            if (!empty($data)) {
                app(ProductService::class)->updateStock($data[0]->product_variant_id, $data[0]->quantity, 'plus');
            }
            app(OrderService::class)->update_order_item($itemId, 'returned', 1);
        } elseif ($newStatus === ReturnRequest::STATUS_APPROVED) {
            updateDetails(['delivery_boy_id' => $deliverBy], ['id' => $itemId], OrderItems::class);
            app(OrderService::class)->update_order_item($itemId, 'return_request_approved', 1);
        } elseif ($newStatus === ReturnRequest::STATUS_REJECTED) {
            app(OrderService::class)->update_order_item($itemId, 'return_request_decline', 1);
        }
    }
}
