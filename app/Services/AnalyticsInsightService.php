<?php

namespace App\Services;

use App\Models\StockItem;
use Carbon\Carbon;

/**
 * Phase 14 (docs/PHASE_14_AI_ANALYTICS_LAYER.md): "API/service scaffolding only... no hardcoded fake
 * insights" (master prompt Section 34, quoted directly in the roadmap). Every observation this service
 * returns is a real number derived from live data via AnalyticsService (Phase 12) - never a canned string,
 * never a guessed/invented figure. This is deliberately NOT an LLM-backed "AI insights" engine - no AI
 * provider credentials exist for this application, and mocking one out would produce exactly the fake
 * insights the roadmap's own instruction forbids. See PHASE_14_AI_ANALYTICS_LAYER.md §1 for the documented
 * extension point a real provider would plug into later.
 */
class AnalyticsInsightService
{
    /**
     * Compares total revenue in [$fromDate, $toDate] against the immediately preceding period of the same
     * length. change_percent is null (not 0, not a divide-by-zero crash) when the previous period had zero
     * revenue - "infinite growth from zero" isn't a meaningful percentage to report.
     *
     * @return array{current_revenue: float, previous_revenue: float, change_percent: ?float}
     */
    public function periodOverPeriodRevenue(?int $sellerId, string $fromDate, string $toDate): array
    {
        $from = Carbon::parse($fromDate);
        $to = Carbon::parse($toDate);
        $days = $from->diffInDays($to) + 1;

        $previousTo = $from->copy()->subDay();
        $previousFrom = $previousTo->copy()->subDays($days - 1);

        $current = app(AnalyticsService::class)->salesSummary($sellerId, $fromDate, $toDate);
        $previous = app(AnalyticsService::class)->salesSummary($sellerId, $previousFrom->toDateString(), $previousTo->toDateString());

        $changePercent = $previous['total_revenue'] > 0
            ? round((($current['total_revenue'] - $previous['total_revenue']) / $previous['total_revenue']) * 100, 2)
            : null;

        return [
            'current_revenue' => $current['total_revenue'],
            'previous_revenue' => $previous['total_revenue'],
            'change_percent' => $changePercent,
        ];
    }

    /**
     * Variants genuinely low on stock (quantity between 1 and $threshold inclusive) right now, from the
     * real stock_items table - a rule-based flag, not a prediction.
     *
     * @return array<int, array{product_variant_id: int, quantity: int}>
     */
    public function lowStockAlerts(?int $sellerId, int $threshold = 5): array
    {
        return StockItem::where('quantity', '>', 0)
            ->where('quantity', '<=', $threshold)
            ->when($sellerId !== null, fn ($q) => $q->where('seller_id', $sellerId))
            ->get()
            ->map(fn ($item) => [
                'product_variant_id' => (int) $item->product_variant_id,
                'quantity' => (int) $item->quantity,
            ])
            ->all();
    }
}
