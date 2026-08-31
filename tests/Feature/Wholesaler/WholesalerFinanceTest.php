<?php

namespace Tests\Feature\Wholesaler;

use App\Models\Category;
use App\Models\Currency;
use App\Models\PaymentRequest;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Models\WholesaleOrder;
use App\Models\Wholesaler;
use App\Models\WholesalerProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Master architecture prompt Phase 6 (Supplier architecture, section 65 "Finance"): a wholesaler's wallet
 * reuses the exact same App\Services\WalletService + PaymentRequest withdrawal flow every other role
 * already runs on - see Wholesaler\OrderController::markPaid() (the wallet credit trigger) and
 * Wholesaler\FinanceController.
 */
class WholesalerFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function baseFixtures(): array
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market'])]);

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
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => $store->id,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'disk' => 'public', 'status' => 1,
            'category_ids' => '',
        ]);

        view()->share([
            'currency_symbol' => '$', 'currency_code' => 'USD',
            'system_settings' => ['app_name' => 'Value Market', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);

        return compact('store', 'category', 'sellerUser', 'seller');
    }

    private function makeWholesaler(float $balance = 0): array
    {
        $user = User::forceCreate([
            'username' => 'wholesaler_' . uniqid(), 'mobile' => '9' . random_int(100000000, 999999999),
            'password' => Hash::make('password123'), 'disk' => 'public', 'serviceable_cities' => '',
            'type' => 'phone', 'role_id' => Role::WHOLESALER, 'active' => 1, 'balance' => $balance,
        ]);
        $wholesaler = Wholesaler::create([
            'user_id' => $user->id, 'business_name' => 'QA Wholesale Co', 'status' => 1, 'disk' => 'public',
        ]);

        return compact('user', 'wholesaler');
    }

    private function makeOrder(array $fixtures, $wholesaler, float $unitPrice = 5, int $qty = 10): WholesaleOrder
    {
        $wp = WholesalerProduct::create([
            'wholesaler_id' => $wholesaler->id, 'category_id' => $fixtures['category']->id,
            'name' => json_encode(['en' => 'Widget']), 'wholesale_price' => $unitPrice, 'min_order_qty' => 1,
            'status' => 1, 'slug' => 'widget-' . uniqid(),
        ]);

        return WholesaleOrder::create([
            'wholesaler_id' => $wholesaler->id, 'wholesaler_product_id' => $wp->id,
            'seller_id' => $fixtures['seller']->id, 'store_id' => $fixtures['store']->id,
            'quantity' => $qty, 'unit_price' => $unitPrice, 'total_amount' => $unitPrice * $qty,
            'retail_price' => 20, 'status' => WholesaleOrder::STATUS_DELIVERED, 'payment_status' => 0,
        ]);
    }

    public function test_marking_an_order_paid_credits_the_wholesalers_wallet(): void
    {
        $fixtures = $this->baseFixtures();
        ['user' => $user, 'wholesaler' => $wholesaler] = $this->makeWholesaler();
        $order = $this->makeOrder($fixtures, $wholesaler, unitPrice: 5, qty: 10);

        $response = $this->actingAs($user)->get(route('wholesaler.orders.mark_paid', ['id' => $order->id]));

        $response->assertOk();
        $this->assertSame(50.0, (float) $user->fresh()->balance);
        $this->assertDatabaseHas('transactions', ['user_id' => $user->id, 'type' => 'credit', 'amount' => 50]);
    }

    public function test_marking_an_already_paid_order_paid_again_does_not_double_credit(): void
    {
        $fixtures = $this->baseFixtures();
        ['user' => $user, 'wholesaler' => $wholesaler] = $this->makeWholesaler();
        $order = $this->makeOrder($fixtures, $wholesaler, unitPrice: 5, qty: 10);

        $this->actingAs($user)->get(route('wholesaler.orders.mark_paid', ['id' => $order->id]));
        $second = $this->actingAs($user)->get(route('wholesaler.orders.mark_paid', ['id' => $order->id]));

        $second->assertStatus(422);
        $this->assertSame(50.0, (float) $user->fresh()->balance);
    }

    public function test_wallet_page_renders_with_the_current_balance(): void
    {
        $this->baseFixtures();
        ['user' => $user] = $this->makeWholesaler(balance: 275);

        $response = $this->actingAs($user)->get(route('wholesaler.wallet.index'));

        $response->assertOk();
        $response->assertSee('275');
    }

    public function test_transaction_list_only_shows_the_logged_in_wholesalers_own_transactions(): void
    {
        $fixtures = $this->baseFixtures();
        ['user' => $user, 'wholesaler' => $wholesaler] = $this->makeWholesaler();
        ['user' => $otherUser, 'wholesaler' => $otherWholesaler] = $this->makeWholesaler();

        $this->actingAs($user)->get(route('wholesaler.orders.mark_paid', [
            'id' => $this->makeOrder($fixtures, $wholesaler)->id,
        ]));
        $this->actingAs($otherUser)->get(route('wholesaler.orders.mark_paid', [
            'id' => $this->makeOrder($fixtures, $otherWholesaler)->id,
        ]));

        $response = $this->actingAs($user)->get(route('wholesaler.wallet.transactions'));

        $response->assertOk();
        $response->assertJsonCount(1, 'rows');
    }

    public function test_wholesaler_can_submit_a_withdrawal_request_recorded_with_its_own_payment_type(): void
    {
        $this->baseFixtures();
        ['user' => $user] = $this->makeWholesaler(balance: 300);

        $response = $this->actingAs($user)->putJson(route('wholesaler.wallet.withdraw'), [
            'payment_address' => 'Bank: 99999', 'amount' => 100,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payment_requests', [
            'user_id' => $user->id, 'payment_type' => 'wholesaler', 'amount_requested' => 100,
        ]);
        $this->assertSame(200.0, (float) $user->fresh()->balance);
    }
}
