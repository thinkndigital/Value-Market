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
use App\Models\Wholesaler;
use App\Models\WholesalerProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Wholesaler module (SaaS re-architecture brief): a platform-level entity distinct from the existing
 * seller-scoped Supplier model (see database/migrations/2025_02_21_000000_create_wholesaler_module.php's
 * own doc comment). Covers registration/login/RBAC, a wholesaler listing a product (starts pending), admin
 * approval, and a seller placing an order against an approved listing. The order's own fulfillment lifecycle
 * (accept/reject/ship/deliver, which is what actually creates the seller's Product) is covered by
 * WholesaleOrderLifecycleTest instead - see docs/WHOLESALER_MODULE.md's v2 section for why importing was
 * replaced with a real order workflow.
 */
class WholesalerModuleTest extends TestCase
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

        return compact('store', 'category', 'sellerUser', 'seller');
    }

    private function makeWholesaler(int $status = 1): array
    {
        $user = User::forceCreate([
            'username' => 'wholesaler_' . uniqid(), 'mobile' => '9' . random_int(100000000, 999999999),
            'password' => Hash::make('password123'), 'disk' => 'public', 'serviceable_cities' => '',
            'type' => 'phone', 'role_id' => Role::WHOLESALER, 'active' => 1,
        ]);
        $wholesaler = Wholesaler::create([
            'user_id' => $user->id, 'business_name' => 'QA Wholesale Co', 'status' => $status, 'disk' => 'public',
        ]);

        return compact('user', 'wholesaler');
    }

    public function test_registration_creates_a_user_and_wholesaler_row(): void
    {
        $this->baseFixtures();

        $response = $this->post('wholesaler/store', [
            'name' => 'Owner Name', 'mobile' => '9887766554', 'email' => 'owner@example.com',
            'password' => 'password123', 'confirm_password' => 'password123',
            'business_name' => 'Acme Wholesale', 'address' => '1 Main St',
        ]);

        $response->assertOk();
        $response->assertJson(['location' => route('wholesaler.login')]);

        $user = User::where('mobile', '9887766554')->first();
        $this->assertNotNull($user);
        $this->assertSame(Role::WHOLESALER, (int) $user->role_id);
        $this->assertDatabaseHas('wholesalers', ['user_id' => $user->id, 'business_name' => 'Acme Wholesale', 'status' => 1]);
    }

    public function test_login_succeeds_for_an_active_wholesaler_and_redirects_to_its_own_dashboard(): void
    {
        $this->baseFixtures();
        ['user' => $user] = $this->makeWholesaler(status: 1);

        $response = $this->post('wholesaler/authenticate', ['mobile' => $user->mobile, 'password' => 'password123']);

        $response->assertOk();
        $response->assertJson(['location' => route('wholesaler.home')]);
    }

    public function test_login_is_rejected_when_the_wholesaler_account_is_suspended(): void
    {
        $this->baseFixtures();
        ['user' => $user] = $this->makeWholesaler(status: 0);

        $response = $this->post('wholesaler/authenticate', ['mobile' => $user->mobile, 'password' => 'password123']);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_a_seller_cannot_reach_wholesaler_only_routes(): void
    {
        ['sellerUser' => $sellerUser] = $this->baseFixtures();

        $this->actingAs($sellerUser)->get('wholesaler/home')->assertStatus(403);
    }

    public function test_wholesaler_can_create_a_product_and_it_starts_pending_admin_approval(): void
    {
        $fixtures = $this->baseFixtures();
        ['user' => $user, 'wholesaler' => $wholesaler] = $this->makeWholesaler();

        $response = $this->actingAs($user)->post('wholesaler/products', [
            'name' => 'Bulk Widgets', 'category_id' => $fixtures['category']->id,
            'wholesale_price' => 5.5, 'min_order_qty' => 10, 'stock' => 1000,
            'image' => UploadedFile::fake()->image('widget.jpg'),
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('wholesaler_products', [
            'wholesaler_id' => $wholesaler->id, 'status' => 0, 'wholesale_price' => 5.5,
        ]);
    }

    public function test_a_wholesaler_cannot_manage_another_wholesalers_product(): void
    {
        $fixtures = $this->baseFixtures();
        ['wholesaler' => $ownerWholesaler] = $this->makeWholesaler();
        ['user' => $otherUser] = $this->makeWholesaler();

        $product = WholesalerProduct::create([
            'wholesaler_id' => $ownerWholesaler->id, 'category_id' => $fixtures['category']->id,
            'name' => json_encode(['en' => 'Owner Product']), 'wholesale_price' => 1, 'slug' => 'owner-product-' . uniqid(),
        ]);

        $this->actingAs($otherUser)->delete('wholesaler/products/' . $product->id)->assertStatus(404);
        $this->assertDatabaseHas('wholesaler_products', ['id' => $product->id]);
    }

    public function test_seller_marketplace_only_lists_admin_approved_products(): void
    {
        $fixtures = $this->baseFixtures();
        ['wholesaler' => $wholesaler] = $this->makeWholesaler();

        WholesalerProduct::create([
            'wholesaler_id' => $wholesaler->id, 'category_id' => $fixtures['category']->id,
            'name' => json_encode(['en' => 'Pending Product']), 'wholesale_price' => 1, 'status' => 0,
            'slug' => 'pending-' . uniqid(),
        ]);
        $approved = WholesalerProduct::create([
            'wholesaler_id' => $wholesaler->id, 'category_id' => $fixtures['category']->id,
            'name' => json_encode(['en' => 'Approved Product']), 'wholesale_price' => 1, 'status' => 1,
            'slug' => 'approved-' . uniqid(),
        ]);

        $response = $this->actingAs($fixtures['sellerUser'])
            ->withSession(['store_id' => $fixtures['store']->id])
            ->getJson('seller/wholesaler_marketplace/list');

        $response->assertOk();
        $names = collect($response->json('rows'))->pluck('name');
        $this->assertTrue($names->contains('Approved Product'));
        $this->assertFalse($names->contains('Pending Product'));
        $this->assertSame(1, $response->json('total'));
    }

    public function test_seller_can_place_a_wholesale_order_for_an_approved_product(): void
    {
        $fixtures = $this->baseFixtures();
        ['wholesaler' => $wholesaler] = $this->makeWholesaler();

        $wp = WholesalerProduct::create([
            'wholesaler_id' => $wholesaler->id, 'category_id' => $fixtures['category']->id,
            'name' => json_encode(['en' => 'Orderable Widget']), 'wholesale_price' => 5, 'min_order_qty' => 3,
            'status' => 1, 'slug' => 'orderable-' . uniqid(),
        ]);

        $response = $this->actingAs($fixtures['sellerUser'])
            ->withSession(['store_id' => $fixtures['store']->id])
            ->postJson('seller/wholesaler_marketplace/' . $wp->id . '/order', [
                'quantity' => 10, 'retail_price' => 24.99,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('wholesale_orders', [
            'wholesaler_product_id' => $wp->id, 'seller_id' => $fixtures['seller']->id, 'quantity' => 10,
            'unit_price' => 5, 'total_amount' => 50, 'retail_price' => 24.99, 'status' => 0,
        ]);
        // Placing an order must not touch the seller's catalog until the wholesaler fulfills it.
        $this->assertSame(0, Product::where('wholesaler_product_id', $wp->id)->count());
    }

    public function test_admin_can_approve_a_pending_wholesaler_product(): void
    {
        $fixtures = $this->baseFixtures();
        ['wholesaler' => $wholesaler] = $this->makeWholesaler();
        $admin = User::forceCreate([
            'username' => 'super_admin_' . uniqid(), 'password' => Hash::make('password'), 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN, 'active' => 1,
        ]);

        $wp = WholesalerProduct::create([
            'wholesaler_id' => $wholesaler->id, 'category_id' => $fixtures['category']->id,
            'name' => json_encode(['en' => 'Widget']), 'wholesale_price' => 5, 'status' => 0,
            'slug' => 'widget-' . uniqid(),
        ]);

        $response = $this->actingAs($admin)->get('admin/wholesalers/products/' . $wp->id . '/approve');

        $response->assertOk();
        $this->assertSame(1, $wp->fresh()->status);
    }
}
