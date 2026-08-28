<?php

namespace Tests\Feature\Phase19;

use App\Http\Controllers\Admin\HomeController;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * docs/PHASE_19_ADMIN_HOME_QUERY_PROFILING.md: empirically measured (DB::listen() timings + EXPLAIN, not
 * just query counts) where /admin/home's real response time goes. Two dominant, unrelated causes:
 *
 * 1. home.blade.php called app(OrderService::class)->ordersCount($status, '', '', $store_id) 24 times
 *    inline in the template (received/processed/shipped/delivered/cancelled/returned - each read 2-5 times
 *    across the visible number, a "current" @php variable, and an aria-valuenow - plus the '' / "all
 *    statuses" total re-run identically 6 times, once per status block). Fixed by computing each of the 7
 *    distinct (status, store_id) results once in HomeController::index() and having the view read from
 *    that array - same values per call site, including the pre-existing "received" mislabeling on two of
 *    the aria-valuenow attributes (cancelled/returned reused the receieved count - left as-is, not part of
 *    this performance pass).
 * 2. The top_sellers block eager-loaded every order_items row (unbounded, not scoped by store_id or date)
 *    for every seller of the store, then summed sub_total/seller_commission_amount in PHP via
 *    Collection::sum(). Fixed with one GROUP BY seller_id query computing both sums in SQL.
 */
class AdminHomeQueryProfilingTest extends TestCase
{
    use RefreshDatabase;

    private function makeSellerInStore(int $storeId, string $storeName = 'Seller Store'): array
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $user->id, 'disk' => 'public', 'status' => 1]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $user->id, 'store_id' => $storeId,
            'slug' => 'store-' . uniqid(), 'store_name' => $storeName, 'store_description' => 'Store',
            'logo' => 'logo.png', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);

        return [$user, $seller];
    }

    private function makeOrderItem(int $sellerId, int $storeId, float $subTotal, float $commission, string $activeStatus): OrderItems
    {
        $order = Order::forceCreate([
            'user_id' => 1, 'store_id' => $storeId, 'mobile' => (string) random_int(6000000000, 6999999999),
            'total' => $subTotal, 'payment_method' => 'cod', 'order_payment_currency_id' => 1,
            'order_payment_currency_code' => 'USD', 'base_currency_code' => 'USD',
            'order_payment_currency_conversion_rate' => 1,
        ]);

        return OrderItems::forceCreate([
            'user_id' => 1, 'store_id' => $storeId, 'order_id' => $order->id, 'seller_id' => $sellerId,
            'product_variant_id' => 1, 'quantity' => 1, 'price' => $subTotal, 'sub_total' => $subTotal,
            'seller_commission_amount' => $commission, 'status' => 'delivered', 'active_status' => $activeStatus,
            'order_type' => 'regular_order',
        ]);
    }

    private function loginAsAdmin(int $storeId): void
    {
        $admin = User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN,
        ]);
        Auth::login($admin);
        session(['store_id' => $storeId]);
    }

    public function test_orders_status_counts_matches_ordercount_called_directly_per_status(): void
    {
        $store = Store::forceCreate(['name' => json_encode(['en' => 'Store']), 'slug' => 'store-' . uniqid(), 'status' => 1]);
        [, $seller] = $this->makeSellerInStore($store->id);
        $this->makeOrderItem($seller->id, $store->id, 100, 10, 'received');
        $this->makeOrderItem($seller->id, $store->id, 200, 20, 'delivered');
        $this->loginAsAdmin($store->id);

        $view = app(HomeController::class)->index();
        $counts = $view->getData()['orders_status_counts'];

        $orderService = app(\App\Services\OrderService::class);
        foreach (['received', 'processed', 'shipped', 'delivered', 'cancelled', 'returned'] as $status) {
            $this->assertSame(
                $orderService->ordersCount($status, '', '', $store->id),
                $counts[$status],
                "orders_status_counts['$status'] must match a direct ordersCount('$status', ...) call"
            );
        }
        $this->assertSame($orderService->ordersCount('', '', '', $store->id), $counts['all']);
    }

    public function test_rendering_admin_home_fires_far_fewer_orderscount_queries_than_the_24_call_baseline(): void
    {
        $store = Store::forceCreate(['name' => json_encode(['en' => 'Store']), 'slug' => 'store-' . uniqid(), 'status' => 1]);
        [, $seller] = $this->makeSellerInStore($store->id);
        $this->makeOrderItem($seller->id, $store->id, 100, 10, 'delivered');
        $this->loginAsAdmin($store->id);

        // AppServiceProvider::boot() only shares system_settings/etc to views when !runningInConsole() - a
        // deliberate guard (see the comment at that call site) that also applies to PHPUnit itself running
        // in console. include_css.blade.php (part of the admin layout every page extends) needs it to
        // render at all, so this test - the only one here that calls ->render() - provides the same minimal
        // stand-in tests/Feature/Phase18 already didn't need (it never rendered the view, only called the
        // controller).
        view()->share(['system_settings' => ['favicon' => '', 'app_name' => 'Test'], 'web_settings' => [], 'currency_symbol' => '', 'currency_code' => '', 'version' => 1]);

        $ordersCountQueries = 0;
        DB::listen(function ($event) use (&$ordersCountQueries) {
            if (str_contains($event->sql, 'count(distinct `order_id`)')) {
                $ordersCountQueries++;
            }
        });

        $view = app(HomeController::class)->index();
        $view->render();

        // Baseline was 24 (one per inline ordersCount() call in home.blade.php); this must land at the 7
        // distinct (status, store_id) pairs the page actually needs, not just "fewer than 24".
        $this->assertSame(7, $ordersCountQueries, 'Rendering /admin/home should run exactly 7 ordersCount() queries (6 named statuses + the all-statuses total), not 24.');
    }

    public function test_top_sellers_totals_match_the_original_php_side_sum_semantics(): void
    {
        $store = Store::forceCreate(['name' => json_encode(['en' => 'Store']), 'slug' => 'store-' . uniqid(), 'status' => 1]);
        [, $sellerA] = $this->makeSellerInStore($store->id, 'Seller A Store');
        [, $sellerB] = $this->makeSellerInStore($store->id, 'Seller B Store');

        // Seller A: only 'delivered' counts toward total_sales (100), but total_commission sums ALL
        // statuses regardless (10 + 5 = 15) - matches the original code's asymmetric filtering exactly.
        $this->makeOrderItem($sellerA->id, $store->id, 100, 10, 'delivered');
        $this->makeOrderItem($sellerA->id, $store->id, 50, 5, 'cancelled');
        // Seller B: higher total_sales, should sort first.
        $this->makeOrderItem($sellerB->id, $store->id, 300, 30, 'delivered');

        $this->loginAsAdmin($store->id);

        $view = app(HomeController::class)->index();
        $topSellers = $view->getData()['top_sellers']->keyBy('seller_id');

        $this->assertSame(300, $topSellers[$sellerB->id]['total_sales']);
        $this->assertSame(30, $topSellers[$sellerB->id]['total_commission']);
        $this->assertSame(100, $topSellers[$sellerA->id]['total_sales'], 'total_sales must only include delivered items.');
        $this->assertSame(15, $topSellers[$sellerA->id]['total_commission'], 'total_commission must sum every status, not just delivered.');

        // sortByDesc('total_sales')->take(6) - seller B (300) must come before seller A (100).
        $this->assertSame([$sellerB->id, $sellerA->id], $view->getData()['top_sellers']->pluck('seller_id')->all());
    }

    public function test_top_sellers_runs_one_aggregate_query_instead_of_pulling_every_order_item_into_php(): void
    {
        $store = Store::forceCreate(['name' => json_encode(['en' => 'Store']), 'slug' => 'store-' . uniqid(), 'status' => 1]);
        [, $seller] = $this->makeSellerInStore($store->id);
        for ($i = 0; $i < 10; $i++) {
            $this->makeOrderItem($seller->id, $store->id, 10, 1, 'delivered');
        }
        $this->loginAsAdmin($store->id);

        $orderItemsRowsReturned = 0;
        DB::listen(function ($event) use (&$orderItemsRowsReturned) {
            // The old code's eager-load selected raw columns per row (seller_id, sub_total,
            // seller_commission_amount, active_status) with no aggregate function - this new GROUP BY
            // query is unmistakably different in shape (has SUM(...) in the select list).
            if (str_contains($event->sql, 'from `order_items`') && str_contains($event->sql, 'group by `seller_id`')) {
                $orderItemsRowsReturned++;
            }
        });

        app(HomeController::class)->index();

        $this->assertSame(1, $orderItemsRowsReturned, 'top_sellers must compute sums via one GROUP BY seller_id query, not by pulling every order_items row into PHP.');
    }
}
