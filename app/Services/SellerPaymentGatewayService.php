<?php

namespace App\Services;

use App\Models\OrderItems;
use App\Models\SellerPaymentGateway;
use App\Models\SellerStore;

/**
 * Resolves per-seller payment gateway credentials, falling back to the platform-global default
 * (`payment_method` setting) when a seller hasn't configured their own, or configured one but disabled
 * it. See app/Libraries/Razorpay.php for the one gateway currently wired to call this (Phase 6's
 * reference implementation - docs/PHASE_6_PAYMENT_GATEWAYS.md explains why the other 4 gateway classes
 * aren't wired yet).
 */
class SellerPaymentGatewayService
{
    /** @return array<string, string>|null Seller-owned credentials for this gateway, or null to use the platform default. */
    public function credentialsFor(?int $sellerId, string $gateway): ?array
    {
        if (empty($sellerId)) {
            return null;
        }

        $row = SellerPaymentGateway::where('seller_id', $sellerId)
            ->where('gateway', $gateway)
            ->where('is_enabled', true)
            ->first();

        return $row && !empty($row->credentials) ? $row->credentials : null;
    }

    /**
     * An order belongs to exactly one seller only when every one of its order_items shares the same
     * seller_id (true whenever `single_seller_order_system` is on, and true by coincidence for plenty of
     * single-seller carts even when it's off). A multi-seller order has no single gateway to charge
     * against with this pass's single-charge-per-order model, so it deliberately returns null (platform
     * default) rather than guessing which seller's credentials to use.
     */
    public function resolveSellerIdForOrder($orderId): ?int
    {
        if (empty($orderId) || !is_numeric($orderId)) {
            return null;
        }

        $sellerIds = OrderItems::where('order_id', $orderId)->distinct()->pluck('seller_id');

        return $sellerIds->count() === 1 ? (int) $sellerIds->first() : null;
    }

    /**
     * CartController::pre_payment_setup() creates the razorpay order at checkout time, before an Order
     * row exists - session('store_id') is the only tenant signal available there (a real scalar, the
     * single store the shopper is currently checking out against; confirmed by reading that call site).
     */
    public function resolveSellerIdForStore($storeId): ?int
    {
        if (empty($storeId)) {
            return null;
        }

        $sellerId = SellerStore::where('store_id', $storeId)->value('seller_id');

        return $sellerId ? (int) $sellerId : null;
    }
}
