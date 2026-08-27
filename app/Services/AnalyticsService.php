<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\DeliveryEarning;
use App\Models\LinkClick;
use App\Models\OrderItems;
use App\Models\ReferralConversion;
use App\Models\StockItem;

/**
 * Phase 12 (docs/PHASE_12_ANALYTICS.md): a pure read-layer over everything Phases 1-11 built. Master prompt
 * Section 34 (referenced by the roadmap for Phase 14, and the same principle applies here): no hardcoded
 * fake numbers, and per this phase's own roadmap line - "no independent numbers" - every method here is a
 * live query against real tables, never a separately-stored total that could drift from the truth.
 */
class AnalyticsService
{
    /**
     * @return array{order_count: int, total_revenue: float, average_order_value: float}
     */
    public function salesSummary(?int $sellerId, string $fromDate, string $toDate): array
    {
        $query = OrderItems::where('active_status', 'delivered')
            ->whereBetween('created_at', ["{$fromDate} 00:00:00", "{$toDate} 23:59:59"])
            ->when($sellerId !== null, fn ($q) => $q->where('seller_id', $sellerId));

        $orderCount = (clone $query)->distinct('order_id')->count('order_id');
        $totalRevenue = (float) (clone $query)->sum('sub_total');

        return [
            'order_count' => $orderCount,
            'total_revenue' => $totalRevenue,
            'average_order_value' => $orderCount > 0 ? round($totalRevenue / $orderCount, 4) : 0.0,
        ];
    }

    /**
     * @return array<int, array{product_variant_id: int, quantity_sold: int, revenue: float}>
     */
    public function topSellingProducts(?int $sellerId, int $limit = 10): array
    {
        return OrderItems::where('active_status', 'delivered')
            ->when($sellerId !== null, fn ($q) => $q->where('seller_id', $sellerId))
            ->selectRaw('product_variant_id, SUM(quantity) as quantity_sold, SUM(sub_total) as revenue')
            ->groupBy('product_variant_id')
            ->orderByDesc('quantity_sold')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_variant_id' => (int) $row->product_variant_id,
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue' => (float) $row->revenue,
            ])
            ->all();
    }

    /**
     * Sum of on-hand quantity x weighted-average cost (InventoryService::weightedAverageCost() - Phase 5)
     * across every stock_items row. A variant with stock but no recorded purchase receipts contributes 0 -
     * its cost is genuinely unknown, not guessed at as the sale price or any other stand-in figure.
     */
    public function stockValuation(?int $sellerId): float
    {
        $inventory = app(InventoryService::class);

        return (float) StockItem::where('quantity', '>', 0)
            ->when($sellerId !== null, fn ($q) => $q->where('seller_id', $sellerId))
            ->get()
            ->sum(function ($item) use ($inventory) {
                $cost = $inventory->weightedAverageCost((int) $item->product_variant_id);

                return $cost === null ? 0.0 : $cost * (int) $item->quantity;
            });
    }

    /**
     * @return array{delivery_count: int, total_earnings_paid: float}
     */
    public function deliveryPerformance(?int $deliveryBoyId, string $fromDate, string $toDate): array
    {
        $query = DeliveryEarning::whereBetween('earned_at', ["{$fromDate} 00:00:00", "{$toDate} 23:59:59"])
            ->when($deliveryBoyId !== null, fn ($q) => $q->where('delivery_boy_id', $deliveryBoyId));

        return [
            'delivery_count' => (clone $query)->count(),
            'total_earnings_paid' => (float) (clone $query)->sum('amount'),
        ];
    }

    /**
     * @return array{clicks: int, conversions: int, approved_commission: float, pending_commission: float}
     */
    public function affiliatePerformance(int $affiliateUserId): array
    {
        $linkIds = \App\Models\AffiliateLink::where('user_id', $affiliateUserId)->pluck('id');

        return [
            'clicks' => LinkClick::whereIn('affiliate_link_id', $linkIds)->count(),
            'conversions' => ReferralConversion::whereIn('affiliate_link_id', $linkIds)->count(),
            'approved_commission' => (float) ReferralConversion::whereIn('affiliate_link_id', $linkIds)
                ->where('status', ReferralConversion::STATUS_APPROVED)->sum('commission_amount'),
            'pending_commission' => (float) ReferralConversion::whereIn('affiliate_link_id', $linkIds)
                ->where('status', ReferralConversion::STATUS_PENDING)->sum('commission_amount'),
        ];
    }

    /**
     * Every active chart-of-accounts row with its live signed balance (LedgerService::accountBalance() -
     * Phase 9). A real trial balance, not a cached snapshot - since every journal entry is guaranteed
     * balanced at write time (LedgerService::postEntry()), this always reflects reality.
     *
     * @return array<int, array{code: string, name: string, type: string, balance: float}>
     */
    public function trialBalance(): array
    {
        $ledger = app(LedgerService::class);

        return ChartOfAccount::where('status', 1)
            ->get()
            ->map(fn ($account) => [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'balance' => $ledger->accountBalance($account->code),
            ])
            ->all();
    }
}
