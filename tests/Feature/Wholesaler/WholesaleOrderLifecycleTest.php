<?php

namespace Tests\Feature\Wholesaler;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Models\WholesaleOrder;
use App\Models\Wholesaler;
use App\Models\WholesalerProduct;
use App\Services\WholesaleOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Wholesaler module v2 (docs/WHOLESALER_MODULE.md): the real purchase-order workflow that replaced v1's
 * one-click import - a seller places an order (WholesalerModuleTest covers that half), the wholesaler
 * accepts/rejects it and, once shipped, marks it delivered - only that last step actually creates or
 * restocks the seller's own Product (App\Services\WholesaleOrderService::fulfill()), matching a real B2B
 * flow where the seller's catalog reflects stock they've actually received, not stock they've merely
 * ordered. Also covers the wholesaler-side "Create Order" POS-style shortcut, stock adjustment, and the
 * sales/clients reports being non-empty once an order is delivered.
 */
class WholesaleOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function baseFixtures(): array
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market'])]);
        // AppServiceProvider normally shares this for every real request; under RefreshDatabase it can run
        // before this fixture's own Setting row exists, so the two report-page tests below (the only ones
        // in this module that render a full Blade layout, not just a JSON endpoint) need it shared directly
        // - same workaround RouteSweepTest::shareBaseViewData() already uses for the same gap.
        view()->share(['system_settings' => ['app_name' => 'Value Market', 'favicon' => '']]);

        $store = Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1, 'is_default_store' => 1,
        ]);

        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Widgets']), 'store_id' => $store->id, 'slug' => 'widgets-' . uniqid(),
            'image' => '', 'banner' => '', 'status' => 1,
        ]);

        $sellerUser = User::forceCreate([
            'username' => 'wh_seller_' . uniqid(), 'password' => Hash::make('password'), 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'active' => 1,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        $sellerStore = SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => $store->id,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'disk' => 'public', 'status' => 1,
            'category_ids' => '',
        ]);

        $wholesalerUser = User::forceCreate([
            'username' => 'wholesaler_' . uniqid(), 'mobile' => '9' . random_int(100000000, 999999999),
            'password' => Hash::make('password123'), 'disk' => 'public', 'serviceable_cities' => '',
            'type' => 'phone', 'role_id' => Role::WHOLESALER, 'active' => 1,
        ]);
        $wholesaler = Wholesaler::create([
            'user_id' => $wholesalerUser->id, 'business_name' => 'QA Wholesale Co', 'status' => 1, 'disk' => 'public',
        ]);

        $wholesalerProduct = WholesalerProduct::create([
            'wholesaler_id' => $wholesaler->id, 'category_id' => $category->id,
            'name' => json_encode(['en' => 'Bulk Widget']), 'wholesale_price' => 5, 'min_order_qty' => 1,
            'status' => 1, 'slug' => 'bulk-widget-' . uniqid(),
        ]);

        return compact('store', 'category', 'sellerUser', 'seller', 'sellerStore', 'wholesalerUser', 'wholesaler', 'wholesalerProduct');
    }

    private function makeOrder(array $fixtures, array $overrides = []): WholesaleOrder
    {
        return WholesaleOrder::create(array_merge([
            'wholesaler_id' => $fixtures['wholesaler']->id,
            'wholesaler_product_id' => $fixtures['wholesalerProduct']->id,
            'seller_id' => $fixtures['seller']->id,
            'store_id' => $fixtures['store']->id,
            'quantity' => 20,
            'unit_price' => 5,
            'total_amount' => 100,
            'retail_price' => 15.99,
            'status' => WholesaleOrder::STATUS_PENDING,
        ], $overrides));
    }

    public function test_wholesaler_can_accept_then_ship_then_deliver_an_order_and_delivery_creates_the_product(): void
    {
        $fixtures = $this->baseFixtures();
        $order = $this->makeOrder($fixtures);

        $this->actingAs($fixtures['wholesalerUser'])->get('wholesaler/orders/' . $order->id . '/transition?to=accept')->assertOk();
        $this->assertSame(WholesaleOrder::STATUS_ACCEPTED, $order->fresh()->status);

        $this->actingAs($fixtures['wholesalerUser'])->get('wholesaler/orders/' . $order->id . '/transition?to=ship')->assertOk();
        $this->assertSame(WholesaleOrder::STATUS_SHIPPED, $order->fresh()->status);

        // Not fulfilled yet - the seller's catalog must stay untouched until delivery.
        $this->assertSame(0, Product::where('wholesaler_product_id', $fixtures['wholesalerProduct']->id)->count());

        $this->actingAs($fixtures['wholesalerUser'])->get('wholesaler/orders/' . $order->id . '/transition?to=deliver')->assertOk();
        $order->refresh();
        $this->assertSame(WholesaleOrder::STATUS_DELIVERED, $order->status);
        $this->assertNotNull($order->fulfilled_product_id);

        $product = Product::find($order->fulfilled_product_id);
        $this->assertNotNull($product);
        $this->assertSame($fixtures['seller']->id, $product->seller_id);
        $this->assertSame(20, (int) $product->stock);

        $variant = Product_variants::where('product_id', $product->id)->first();
        $this->assertEquals(15.99, $variant->price);
        $this->assertEquals(20, $variant->stock);
    }

    public function test_a_repeat_order_for_an_already_fulfilled_listing_tops_up_stock_instead_of_creating_a_second_product(): void
    {
        $fixtures = $this->baseFixtures();

        $first = $this->makeOrder($fixtures, ['quantity' => 20]);
        app(WholesaleOrderService::class)->fulfill($first);

        $second = $this->makeOrder($fixtures, ['quantity' => 15]);
        app(WholesaleOrderService::class)->fulfill($second);

        $this->assertSame(1, Product::where('wholesaler_product_id', $fixtures['wholesalerProduct']->id)->count());
        $product = Product::where('wholesaler_product_id', $fixtures['wholesalerProduct']->id)->first();
        $this->assertSame(35, (int) $product->stock);

        $variant = Product_variants::where('product_id', $product->id)->first();
        $this->assertEquals(35, $variant->stock);
    }

    public function test_fulfilling_the_same_order_twice_is_a_no_op(): void
    {
        $fixtures = $this->baseFixtures();
        $order = $this->makeOrder($fixtures, ['quantity' => 10]);

        $service = app(WholesaleOrderService::class);
        $product1 = $service->fulfill($order);
        $product2 = $service->fulfill($order->fresh());

        $this->assertSame($product1->id, $product2->id);
        $this->assertSame(10, (int) $product1->fresh()->stock);
    }

    public function test_wholesaler_cannot_ship_an_order_that_is_still_pending(): void
    {
        $fixtures = $this->baseFixtures();
        $order = $this->makeOrder($fixtures);

        $response = $this->actingAs($fixtures['wholesalerUser'])->get('wholesaler/orders/' . $order->id . '/transition?to=ship');

        $response->assertStatus(422);
        $this->assertSame(WholesaleOrder::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_a_wholesaler_cannot_transition_another_wholesalers_order(): void
    {
        $fixtures = $this->baseFixtures();
        $order = $this->makeOrder($fixtures);

        $otherWholesalerUser = User::forceCreate([
            'username' => 'other_wholesaler_' . uniqid(), 'mobile' => '9' . random_int(100000000, 999999999),
            'password' => Hash::make('password123'), 'disk' => 'public', 'serviceable_cities' => '',
            'type' => 'phone', 'role_id' => Role::WHOLESALER, 'active' => 1,
        ]);
        Wholesaler::create(['user_id' => $otherWholesalerUser->id, 'business_name' => 'Other Co', 'status' => 1, 'disk' => 'public']);

        $this->actingAs($otherWholesalerUser)->get('wholesaler/orders/' . $order->id . '/transition?to=accept')->assertStatus(404);
        $this->assertSame(WholesaleOrder::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_seller_can_cancel_their_own_pending_order(): void
    {
        $fixtures = $this->baseFixtures();
        $order = $this->makeOrder($fixtures);

        $response = $this->actingAs($fixtures['sellerUser'])
            ->get('seller/wholesaler_marketplace/orders/' . $order->id . '/cancel');

        $response->assertOk();
        $this->assertSame(WholesaleOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_wholesaler_pos_style_create_order_is_pre_accepted(): void
    {
        $fixtures = $this->baseFixtures();

        $response = $this->actingAs($fixtures['wholesalerUser'])->postJson('wholesaler/orders', [
            'wholesaler_product_id' => $fixtures['wholesalerProduct']->id,
            'seller_store_id' => $fixtures['sellerStore']->id,
            'quantity' => 5,
            'retail_price' => 19.99,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('wholesale_orders', [
            'wholesaler_id' => $fixtures['wholesaler']->id,
            'seller_id' => $fixtures['seller']->id,
            'quantity' => 5,
            'status' => WholesaleOrder::STATUS_ACCEPTED,
        ]);
    }

    public function test_wholesaler_can_adjust_stock_up_and_down_but_never_below_zero(): void
    {
        $fixtures = $this->baseFixtures();
        $product = $fixtures['wholesalerProduct'];
        $product->stock = 10;
        $product->save();

        $this->actingAs($fixtures['wholesalerUser'])
            ->postJson('wholesaler/stock/' . $product->id . '/adjust', ['delta' => 5])
            ->assertOk();
        $this->assertSame(15, (int) $product->fresh()->stock);

        $this->actingAs($fixtures['wholesalerUser'])
            ->postJson('wholesaler/stock/' . $product->id . '/adjust', ['delta' => -100])
            ->assertOk();
        $this->assertSame(0, (int) $product->fresh()->stock);
    }

    public function test_sales_report_reflects_a_delivered_order_but_not_a_pending_one(): void
    {
        $fixtures = $this->baseFixtures();
        $delivered = $this->makeOrder($fixtures, ['quantity' => 10, 'total_amount' => 50]);
        app(WholesaleOrderService::class)->fulfill($delivered);
        $this->makeOrder($fixtures, ['quantity' => 3, 'total_amount' => 15]);

        $response = $this->actingAs($fixtures['wholesalerUser'])->get('wholesaler/reports/sales');

        $response->assertOk();
        $response->assertSee('50.00');
    }

    public function test_clients_list_aggregates_a_sellers_delivered_orders(): void
    {
        $fixtures = $this->baseFixtures();
        $order = $this->makeOrder($fixtures, ['quantity' => 10, 'total_amount' => 50]);
        app(WholesaleOrderService::class)->fulfill($order);

        $response = $this->actingAs($fixtures['wholesalerUser'])->getJson('wholesaler/clients/list');

        $response->assertOk();
        $rows = $response->json('rows');
        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows[0]['orders_count']);
        $this->assertEquals(50, $rows[0]['total_spent']);
    }
}
