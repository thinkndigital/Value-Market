<?php

namespace Tests\Feature\Phase14;

use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Role;
use App\Models\Seller;
use App\Models\StockItem;
use App\Models\User;
use App\Services\AnalyticsInsightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsInsightServiceTest extends TestCase
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

    private function makeOrderItem(int $sellerId, float $subTotal, string $createdAt): void
    {
        $order = Order::forceCreate([
            'user_id' => 1, 'mobile' => (string) random_int(6000000000, 6999999999), 'total' => $subTotal,
            'payment_method' => 'cod', 'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD',
            'base_currency_code' => 'USD', 'order_payment_currency_conversion_rate' => 1,
        ]);
        OrderItems::forceCreate([
            'user_id' => 1, 'order_id' => $order->id, 'seller_id' => $sellerId,
            'product_variant_id' => 1, 'quantity' => 1, 'price' => $subTotal, 'sub_total' => $subTotal,
            'status' => json_encode([['delivered', $createdAt]]),
            'active_status' => 'delivered', 'order_type' => 'regular_order', 'created_at' => $createdAt,
        ]);
    }

    public function test_period_over_period_revenue_computes_a_correct_percentage_increase(): void
    {
        $seller = $this->makeSeller();
        // Previous 3-day period: 2026-01-01..03, current: 2026-01-04..06
        $this->makeOrderItem($seller->id, 100, '2026-01-02 10:00:00');
        $this->makeOrderItem($seller->id, 150, '2026-01-05 10:00:00');

        $result = app(AnalyticsInsightService::class)->periodOverPeriodRevenue($seller->id, '2026-01-04', '2026-01-06');

        $this->assertSame(150.0, $result['current_revenue']);
        $this->assertSame(100.0, $result['previous_revenue']);
        $this->assertSame(50.0, $result['change_percent']); // (150-100)/100 * 100
    }

    public function test_period_over_period_revenue_is_null_not_a_crash_when_the_previous_period_had_no_revenue(): void
    {
        $seller = $this->makeSeller();
        $this->makeOrderItem($seller->id, 150, '2026-02-05 10:00:00');

        $result = app(AnalyticsInsightService::class)->periodOverPeriodRevenue($seller->id, '2026-02-04', '2026-02-06');

        $this->assertSame(150.0, $result['current_revenue']);
        $this->assertSame(0.0, $result['previous_revenue']);
        $this->assertNull($result['change_percent']);
    }

    public function test_low_stock_alerts_flags_only_variants_within_the_threshold_and_above_zero(): void
    {
        $seller = $this->makeSeller();
        StockItem::forceCreate(['seller_id' => $seller->id, 'branch_id' => null, 'product_variant_id' => 1, 'quantity' => 2]);  // low
        StockItem::forceCreate(['seller_id' => $seller->id, 'branch_id' => null, 'product_variant_id' => 2, 'quantity' => 50]); // plenty
        StockItem::forceCreate(['seller_id' => $seller->id, 'branch_id' => null, 'product_variant_id' => 3, 'quantity' => 0]);  // out of stock, not "low"

        $alerts = app(AnalyticsInsightService::class)->lowStockAlerts($seller->id, threshold: 5);

        $variantIds = array_column($alerts, 'product_variant_id');
        $this->assertContains(1, $variantIds);
        $this->assertNotContains(2, $variantIds);
        $this->assertNotContains(3, $variantIds);
    }

    public function test_low_stock_alerts_is_scoped_to_the_requested_seller(): void
    {
        $sellerA = $this->makeSeller();
        $sellerB = $this->makeSeller();
        StockItem::forceCreate(['seller_id' => $sellerA->id, 'branch_id' => null, 'product_variant_id' => 1, 'quantity' => 1]);
        StockItem::forceCreate(['seller_id' => $sellerB->id, 'branch_id' => null, 'product_variant_id' => 2, 'quantity' => 1]);

        $alerts = app(AnalyticsInsightService::class)->lowStockAlerts($sellerA->id, threshold: 5);

        $this->assertCount(1, $alerts);
        $this->assertSame(1, $alerts[0]['product_variant_id']);
    }
}
