<?php

namespace Tests\Feature\Phase12;

use App\Models\AffiliateLink;
use App\Models\Branch;
use App\Models\CommissionRule;
use App\Models\DeliveryEarning;
use App\Models\LinkClick;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\ReferralConversion;
use App\Models\Role;
use App\Models\Seller;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\AnalyticsService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeSeller(): Seller
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);

        return Seller::forceCreate(['user_id' => $user->id, 'disk' => 'public', 'status' => 1]);
    }

    private function makeOrderItem(int $sellerId, float $subTotal, int $quantity, string $variantId = '1', string $createdAt = null): OrderItems
    {
        $order = Order::forceCreate([
            'user_id' => 1, 'mobile' => (string) random_int(6000000000, 6999999999), 'total' => $subTotal,
            'payment_method' => 'cod', 'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD',
            'base_currency_code' => 'USD', 'order_payment_currency_conversion_rate' => 1,
        ]);

        return OrderItems::forceCreate([
            'user_id' => 1, 'order_id' => $order->id, 'seller_id' => $sellerId,
            'product_variant_id' => $variantId, 'quantity' => $quantity, 'price' => $subTotal / $quantity, 'sub_total' => $subTotal,
            'status' => json_encode([['delivered', now()->toDateTimeString()]]),
            'active_status' => 'delivered', 'order_type' => 'regular_order',
            'created_at' => $createdAt ?? now(),
        ]);
    }

    public function test_sales_summary_computes_revenue_order_count_and_average(): void
    {
        $seller = $this->makeSeller();
        $this->makeOrderItem($seller->id, 100, 1);
        $this->makeOrderItem($seller->id, 200, 1);

        $summary = app(AnalyticsService::class)->salesSummary($seller->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        $this->assertSame(2, $summary['order_count']);
        $this->assertSame(300.0, $summary['total_revenue']);
        $this->assertSame(150.0, $summary['average_order_value']);
    }

    public function test_sales_summary_excludes_items_outside_the_date_range(): void
    {
        $seller = $this->makeSeller();
        $this->makeOrderItem($seller->id, 100, 1, '1', now()->subMonths(2)->toDateTimeString());
        $this->makeOrderItem($seller->id, 200, 1, '1', now()->toDateTimeString());

        $summary = app(AnalyticsService::class)->salesSummary($seller->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        $this->assertSame(1, $summary['order_count']);
        $this->assertSame(200.0, $summary['total_revenue']);
    }

    public function test_sales_summary_is_scoped_to_the_requested_seller(): void
    {
        $sellerA = $this->makeSeller();
        $sellerB = $this->makeSeller();
        $this->makeOrderItem($sellerA->id, 100, 1);
        $this->makeOrderItem($sellerB->id, 500, 1);

        $summary = app(AnalyticsService::class)->salesSummary($sellerA->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        $this->assertSame(100.0, $summary['total_revenue']);
    }

    public function test_top_selling_products_ranks_by_quantity_sold(): void
    {
        $seller = $this->makeSeller();
        $this->makeOrderItem($seller->id, 100, 5, variantId: '10');
        $this->makeOrderItem($seller->id, 40, 2, variantId: '20');

        $top = app(AnalyticsService::class)->topSellingProducts($seller->id);

        $this->assertSame(10, $top[0]['product_variant_id']);
        $this->assertSame(5, $top[0]['quantity_sold']);
    }

    public function test_stock_valuation_sums_quantity_times_weighted_average_cost(): void
    {
        $seller = $this->makeSeller();
        app(InventoryService::class)->recordMovement($seller->id, null, 77, StockMovement::TYPE_IN, 10, StockMovement::REFERENCE_GOODS_RECEIVED_NOTE, null, 5.0);

        $valuation = app(AnalyticsService::class)->stockValuation($seller->id);

        $this->assertSame(50.0, $valuation); // 10 units @ $5 average cost
    }

    public function test_stock_valuation_contributes_zero_for_a_variant_with_no_recorded_cost(): void
    {
        $seller = $this->makeSeller();
        StockItem::forceCreate(['seller_id' => $seller->id, 'branch_id' => null, 'product_variant_id' => 999, 'quantity' => 20]);

        $valuation = app(AnalyticsService::class)->stockValuation($seller->id);

        $this->assertSame(0.0, $valuation);
    }

    public function test_delivery_performance_counts_and_sums_within_the_date_range(): void
    {
        $driver = User::forceCreate([
            'username' => 'driver_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::DELIVERY_BOY,
        ]);
        DeliveryEarning::forceCreate(['delivery_boy_id' => $driver->id, 'order_id' => 1, 'order_item_id' => 1, 'amount' => 5, 'rate_type' => 'flat', 'rate_value' => 5, 'earned_at' => now()]);
        DeliveryEarning::forceCreate(['delivery_boy_id' => $driver->id, 'order_id' => 2, 'order_item_id' => 2, 'amount' => 5, 'rate_type' => 'flat', 'rate_value' => 5, 'earned_at' => now()]);

        $perf = app(AnalyticsService::class)->deliveryPerformance($driver->id, now()->subDay()->toDateString(), now()->addDay()->toDateString());

        $this->assertSame(2, $perf['delivery_count']);
        $this->assertSame(10.0, $perf['total_earnings_paid']);
    }

    public function test_affiliate_performance_reports_clicks_conversions_and_commission_by_status(): void
    {
        $affiliate = User::forceCreate([
            'username' => 'affiliate_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'balance' => 0,
        ]);
        $link = app(AffiliateService::class)->createLink($affiliate->id, AffiliateLink::TARGET_PLATFORM);
        $buyer = User::forceCreate([
            'username' => 'buyer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'balance' => 0,
        ]);
        LinkClick::forceCreate(['affiliate_link_id' => $link->id, 'clicked_at' => now()]);
        LinkClick::forceCreate(['affiliate_link_id' => $link->id, 'clicked_at' => now()]);
        CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_PLATFORM, 'scope_id' => null, 'rate_type' => 'flat', 'rate_value' => 20, 'status' => 1]);
        app(AffiliateService::class)->recordConversion($link->code, 5001, $buyer->id, 100.0);
        app(AffiliateService::class)->approveConversionsForOrder(5001);

        $perf = app(AnalyticsService::class)->affiliatePerformance($affiliate->id);

        $this->assertSame(2, $perf['clicks']);
        $this->assertSame(1, $perf['conversions']);
        $this->assertSame(20.0, $perf['approved_commission']);
        $this->assertSame(0.0, $perf['pending_commission']);
    }

    public function test_trial_balance_lists_every_active_account_with_its_live_balance(): void
    {
        app(\App\Services\LedgerService::class)->postEntry('Seed', [
            ['account_code' => '1000', 'debit' => 500],
            ['account_code' => '4000', 'credit' => 500],
        ]);

        $trialBalance = app(AnalyticsService::class)->trialBalance();
        $byCode = collect($trialBalance)->keyBy('code');

        $this->assertSame(500.0, $byCode['1000']['balance']);
        $this->assertSame(500.0, $byCode['4000']['balance']);
        $this->assertGreaterThanOrEqual(9, count($trialBalance)); // at least the Phase 9 seed
    }
}
