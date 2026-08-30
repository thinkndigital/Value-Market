<?php

namespace Tests\Feature\Storefront;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Customer storefront (Phase 21 storefront build) smoke test - real HTTP kernel, real fixtures, same
 * discipline as this session's other route sweeps. Covers the v1 build order's happy paths: home, product
 * listing/detail render for a guest; register auto-logs-in; login works; cart/checkout/account are
 * login-gated for guests and reachable once authenticated.
 */
class StorefrontSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalog(): array
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market'])]);
        Setting::forceCreate(['variable' => 'web_settings', 'value' => json_encode([])]);

        $store = Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1, 'is_default_store' => 1,
        ]);

        $sellerUser = User::forceCreate([
            'username' => 'storefront_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'active' => 1,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => $store->id,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'disk' => 'public', 'status' => 1,
            'category_ids' => '',
        ]);

        $category = Category::forceCreate(['name' => json_encode(['en' => 'Cat']), 'store_id' => $store->id, 'slug' => 'cat-store-' . uniqid(), 'image' => '', 'banner' => '', 'status' => 1]);
        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id, 'store_id' => $store->id,
            'name' => json_encode(['en' => 'Storefront Product']), 'slug' => 'storefront-product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '0', 'status' => 1, 'stock' => 10, 'availability' => 1,
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 20, 'status' => 1, 'stock' => 5]);

        return compact('store', 'product', 'variant');
    }

    public function test_home_page_renders_for_a_guest(): void
    {
        $this->seedCatalog();

        $this->get('/')->assertOk()->assertSee('Storefront Product');
    }

    public function test_product_listing_and_detail_pages_render_for_a_guest(): void
    {
        ['product' => $product] = $this->seedCatalog();

        $this->get('/products')->assertOk()->assertSee('Storefront Product');
        $this->get('/products/' . $product->slug)->assertOk()->assertSee('Storefront Product');
    }

    public function test_cart_checkout_and_account_redirect_a_guest_to_login(): void
    {
        $this->seedCatalog();

        $this->get('/cart')->assertRedirect(route('customer.login'));
        $this->get('/checkout')->assertRedirect(route('customer.login'));
        $this->get('/my-account')->assertRedirect(route('customer.login'));
    }

    public function test_register_creates_a_customer_and_logs_them_in(): void
    {
        $this->seedCatalog();

        $response = $this->post('/register', [
            'name' => 'New Customer',
            'email' => 'new_customer_' . uniqid() . '@example.com',
            'mobile' => '9994441234',
            'country_code' => '+1',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated('web');
        $user = User::where('mobile', '9994441234')->firstOrFail();
        $this->assertEquals(Role::CUSTOMER, $user->role_id);
    }

    public function test_login_authenticates_an_existing_customer(): void
    {
        $this->seedCatalog();

        $user = User::forceCreate([
            'username' => 'existing_customer', 'password' => bcrypt('password123'), 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'mobile' => '9994445678',
        ]);

        $response = $this->post('/login', ['mobile' => '9994445678', 'password' => 'password123']);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_authenticated_customer_can_add_to_cart_and_view_it(): void
    {
        ['variant' => $variant] = $this->seedCatalog();

        $user = User::forceCreate([
            'username' => 'cart_customer', 'password' => bcrypt('password123'), 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'mobile' => '9994449999',
        ]);

        $this->actingAs($user, 'web');

        $this->post('/cart/add', ['product_variant_id' => $variant->id, 'qty' => 1])->assertRedirect();
        $this->get('/cart')->assertOk()->assertSee('Storefront Product');
    }
}
