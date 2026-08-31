<?php

namespace Tests\Feature\Storefront;

use App\Models\Address;
use App\Models\AffiliateLink;
use App\Models\Cart;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\ReferralConversion;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\FirebaseNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Master architecture prompt Phase 7 bug fix: an affiliate link had been dead on the real Customer
 * Storefront since it was built (docs/STOREFRONT_V1.md) - AffiliateController::trackAndRedirect() sent
 * visitors to '/product/{id}' and '/category/{id}', neither of which exist (the storefront's real routes
 * are slug-based, '/products/{slug}'), and even a fixed redirect's affiliate_code query string was never
 * captured anywhere, so it could never survive into a later checkout request. This means every affiliate
 * click that ever reached the real storefront (as opposed to the mobile API, which
 * tests/Feature/Phase7/OrderPlacementAffiliateAttributionTest.php already covers) resulted in zero
 * commission attribution. Covers the full chain: click -> real redirect -> session capture -> checkout
 * -> attributed order, through the actual HTTP routes, not just the services in isolation.
 */
class AffiliateAttributionTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalog(): array
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market', 'single_seller_order_system' => '0'])]);
        Setting::forceCreate(['variable' => 'web_settings', 'value' => json_encode([])]);

        $store = Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1, 'is_default_store' => 1,
        ]);

        $sellerUser = User::forceCreate([
            'username' => 'aff_attr_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'active' => 1,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => $store->id,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'disk' => 'public', 'status' => 1,
            'category_ids' => '',
        ]);

        $category = Category::forceCreate(['name' => json_encode(['en' => 'Cat']), 'store_id' => $store->id, 'slug' => 'cat-attr-' . uniqid(), 'image' => '', 'banner' => '', 'status' => 1]);
        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id, 'store_id' => $store->id,
            'name' => json_encode(['en' => 'Attribution Product']), 'slug' => 'attribution-product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '0', 'status' => 1, 'stock' => 10, 'availability' => 1,
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 20, 'status' => 1, 'stock' => 5]);

        return compact('store', 'product', 'variant');
    }

    public function test_a_product_affiliate_link_redirects_to_the_real_storefront_product_page(): void
    {
        ['product' => $product] = $this->seedCatalog();
        $affiliate = User::forceCreate([
            'username' => 'aff_attr_click_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
        $link = app(AffiliateService::class)->getOrCreateProductLink($affiliate->id, $product->id);

        $response = $this->get('/r/' . $link->code);

        $response->assertRedirect(route('customer.product.show', $product->slug) . '?affiliate_code=' . $link->code);
    }

    public function test_visiting_a_product_page_with_an_affiliate_code_stores_it_in_session(): void
    {
        ['product' => $product] = $this->seedCatalog();

        $this->get('/products/' . $product->slug . '?affiliate_code=SOME-CODE')->assertOk();

        $this->assertSame('SOME-CODE', session('affiliate_code'));
    }

    public function test_checkout_after_an_affiliate_click_attributes_the_order(): void
    {
        ['store' => $store, 'product' => $product, 'variant' => $variant] = $this->seedCatalog();
        $affiliate = User::forceCreate([
            'username' => 'aff_attr_checkout_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'balance' => 0,
        ]);
        CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_PLATFORM, 'scope_id' => null, 'rate_type' => 'percentage', 'rate_value' => 5, 'status' => 1]);
        $link = app(AffiliateService::class)->getOrCreateProductLink($affiliate->id, $product->id);

        $customer = User::forceCreate([
            'username' => 'aff_attr_customer_' . uniqid(), 'password' => Hash::make('password'), 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
            'mobile' => (string) random_int(6000000000, 6999999999),
        ]);
        $address = Address::forceCreate([
            'user_id' => $customer->id, 'name' => 'Test', 'type' => 'home', 'mobile' => $customer->mobile,
            'address' => '1 Main St', 'landmark' => '', 'city' => 'City', 'area' => 'Area', 'pincode' => '12345',
            'state' => 'State', 'country' => 'Country', 'latitude' => '0', 'longitude' => '0',
        ]);
        Cart::forceCreate([
            'user_id' => $customer->id, 'store_id' => $store->id, 'product_variant_id' => $variant->id,
            'qty' => 1, 'is_saved_for_later' => 0, 'product_type' => 'regular',
        ]);

        $this->mock(FirebaseNotificationService::class, function ($mock) {
            $mock->shouldReceive('sendNotification')->andReturn(null);
        });
        Mail::fake();

        // Follow the real path: click -> product page (stashes affiliate_code in session) -> logged-in
        // checkout submission, exactly as a real customer would experience it.
        $this->get(route('customer.product.show', $product->slug) . '?affiliate_code=' . $link->code)->assertOk();

        $response = $this->actingAs($customer, 'web')->post(route('customer.checkout.store'), [
            'address_id' => $address->id,
        ]);

        $response->assertRedirect(route('customer.account.orders'));

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame(Order::CHANNEL_AFFILIATE, $order->channel);

        $conversion = ReferralConversion::where('order_id', $order->id)->first();
        $this->assertNotNull($conversion);
        $this->assertSame($link->id, $conversion->affiliate_link_id);
    }
}
