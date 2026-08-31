<?php

namespace Tests\Feature;

use App\Models\AffiliateLink;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\AffiliateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master architecture prompt Phase 7 (Affiliate architecture, section 24 "Affiliate My Products"): "My
 * Products" needed no new schema - AffiliateService::getOrCreateProductLink() already persists one
 * AffiliateLink row per (user, product) the moment a link is generated (via Copy Link or the Marketplace),
 * complete with click/conversion counts - this is just the first dedicated view onto that existing data.
 */
class AffiliateMyProductsTest extends TestCase
{
    use RefreshDatabase;

    private function shareBaseViewData(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market', 'favicon' => ''])]);
        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'system_settings' => ['app_name' => 'Value Market', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);
    }

    private function makeAffiliate(): User
    {
        return User::forceCreate([
            'username' => 'affiliate_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
    }

    private function makeProduct(): Product
    {
        $sellerUser = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        $store = Store::forceCreate([
            'name' => json_encode(['en' => 'Store ' . uniqid()]), 'slug' => 'store-' . uniqid(),
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => $store->id,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Cool Store', 'disk' => 'public', 'status' => 1,
        ]);

        return Product::forceCreate([
            'category_id' => 1, 'seller_id' => $seller->id, 'store_id' => $store->id,
            'name' => json_encode(['en' => 'My Saved Product']), 'slug' => 'product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'status' => 1,
        ]);
    }

    public function test_generating_a_product_link_makes_it_appear_in_my_products(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        $product = $this->makeProduct();

        app(AffiliateService::class)->getOrCreateProductLink($affiliate->id, $product->id);

        $response = $this->actingAs($affiliate)->getJson(route('affiliate.my_products.list'));

        $response->assertOk();
        $response->assertJsonFragment(['product_id' => $product->id, 'name' => 'My Saved Product', 'store_name' => 'Cool Store']);
    }

    public function test_my_products_only_shows_the_logged_in_affiliates_own_links(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        $otherAffiliate = $this->makeAffiliate();
        $product = $this->makeProduct();

        app(AffiliateService::class)->getOrCreateProductLink($otherAffiliate->id, $product->id);

        $response = $this->actingAs($affiliate)->getJson(route('affiliate.my_products.list'));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_my_products_reflects_click_and_conversion_counts_already_tracked_on_the_link(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        $product = $this->makeProduct();

        $link = app(AffiliateService::class)->getOrCreateProductLink($affiliate->id, $product->id);
        $link->update(['clicks_count' => 7, 'conversions_count' => 2]);

        $response = $this->actingAs($affiliate)->getJson(route('affiliate.my_products.list'));

        $response->assertOk();
        $response->assertJsonFragment(['clicks_count' => 7, 'conversions_count' => 2]);
    }

    public function test_my_products_only_lists_product_links_not_store_or_platform_links(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        AffiliateLink::forceCreate([
            'user_id' => $affiliate->id, 'target_type' => AffiliateLink::TARGET_PLATFORM, 'target_id' => 0,
            'code' => 'platform-' . uniqid(), 'status' => AffiliateLink::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($affiliate)->getJson(route('affiliate.my_products.list'));

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_my_products_page_renders(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();

        $response = $this->actingAs($affiliate)->get(route('affiliate.my_products.page'));

        $response->assertOk();
    }
}
