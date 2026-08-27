<?php

namespace App\Services;

use App\Models\DeliveryEarning;
use App\Models\Order;

/**
 * Phase 8 (docs/PHASE_8_DELIVERY.md): pays a driver a structured fee for a delivered order item. Rate is
 * configured via system_settings (same JSON-config pattern refer-a-friend already uses:
 * 'delivery_earning_status', 'delivery_earning_type' [flat|percentage], 'delivery_earning_value') - off by
 * default, so this is a no-op until an admin explicitly configures it.
 */
class DeliveryEarningService
{
    public function creditForDeliveredItem(int $orderItemId, int $orderId, ?int $deliveryBoyId): ?DeliveryEarning
    {
        if (empty($deliveryBoyId)) {
            return null;
        }

        // Idempotent by design (order_item_id is unique on delivery_earnings) - re-processing the same
        // delivered-status transition (e.g. a retried request) must never pay twice.
        if (DeliveryEarning::where('order_item_id', $orderItemId)->exists()) {
            return null;
        }

        $settings = json_decode(app(SettingService::class)->getSettings('system_settings', true), true);
        if (empty($settings['delivery_earning_status']) || (int) $settings['delivery_earning_status'] !== 1) {
            return null;
        }

        $rateType = $settings['delivery_earning_type'] ?? DeliveryEarning::RATE_FLAT;
        $rateValue = (float) ($settings['delivery_earning_value'] ?? 0);
        if ($rateValue <= 0) {
            return null;
        }

        if ($rateType === DeliveryEarning::RATE_PERCENTAGE) {
            $deliveryCharge = (float) (Order::where('id', $orderId)->value('delivery_charge') ?? 0);
            $amount = round($deliveryCharge * ($rateValue / 100), 4);
        } else {
            $amount = $rateValue;
        }

        if ($amount <= 0) {
            return null;
        }

        $earning = DeliveryEarning::forceCreate([
            'delivery_boy_id' => $deliveryBoyId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'amount' => $amount,
            'rate_type' => $rateType,
            'rate_value' => $rateValue,
            'earned_at' => now(),
        ]);

        app(WalletService::class)->updateWalletBalance(
            'credit',
            $deliveryBoyId,
            $amount,
            'Delivery earning credited',
            'delivery-earning-' . $earning->id
        );

        return $earning;
    }
}
