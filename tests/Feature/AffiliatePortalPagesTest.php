<?php

namespace Tests\Feature;

use App\Models\CommissionRule;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StoreAffiliateRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The affiliate portal used to be a single dashboard page; it's now a real multi-page panel (sidebar,
 * matching the admin/seller/delivery_boy layout shape) with a dedicated Products page and per-product
 * detail page. productShow()'s visibility check is the one with real security weight here - it must apply
 * the exact same public/approved-private rule as availableProducts() (AffiliateAvailableProductsTest), or
 * an affiliate could reach a private store's product page by guessing its id even though it never appears
 * in their catalog.
 */
class AffiliatePortalPagesTest extends TestCase
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

    private function makeStoreWithProduct(string $visibility): array
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
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'disk' => 'public', 'status' => 1,
            'affiliate_visibility' => $visibility,
        ]);
        $product = Product::forceCreate([
            'category_id' => 1, 'seller_id' => $seller->id, 'store_id' => $store->id,
            'name' => json_encode(['en' => 'Detail Page Product']),
            'short_description' => json_encode(['en' => 'A short pitch.']),
            'description' => '<p onclick="alert(1)">Rich <b>description</b> <script>alert(2)</script></p>',
            'slug' => 'product-' . uniqid(), 'image' => '', 'deliverable_cities' => '', 'status' => 1,
        ]);
        CommissionRule::forceCreate([
            'scope' => CommissionRule::SCOPE_PRODUCT, 'scope_id' => $product->id,
            'rate_type' => 'percentage', 'rate_value' => 15, 'status' => CommissionRule::STATUS_ACTIVE,
        ]);

        return [$store, $product];
    }

    public function test_products_page_renders(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();

        $response = $this->actingAs($affiliate)->get(route('affiliate.products.page'));

        $response->assertOk();
    }

    public function test_commissions_withdrawals_and_stores_pages_render(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();

        $this->actingAs($affiliate)->get(route('affiliate.commissions.page'))->assertOk();
        $this->actingAs($affiliate)->get(route('affiliate.withdrawals.page'))->assertOk();
        $this->actingAs($affiliate)->get(route('affiliate.stores.page'))->assertOk();
    }

    public function test_product_detail_page_shows_name_price_and_commission_for_a_public_product(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        [, $product] = $this->makeStoreWithProduct('public');

        $response = $this->actingAs($affiliate)->get(route('affiliate.product.show', $product->id));

        $response->assertOk();
        $response->assertSee('Detail Page Product');
        $response->assertSee('15');
    }

    public function test_product_detail_page_strips_script_tags_from_the_description(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        [, $product] = $this->makeStoreWithProduct('public');

        $response = $this->actingAs($affiliate)->get(route('affiliate.product.show', $product->id));

        $response->assertOk();
        // The page's own layout legitimately has <script src="..."> asset tags - check for the exact
        // malicious fragments the seeded description carried, not the bare word "script".
        $response->assertDontSee('<script>alert(2)', false);
        $response->assertDontSee('onclick="alert(1)"', false);
        $response->assertSee('<b>description</b>', false);
    }

    public function test_product_detail_page_is_404_for_a_private_stores_product_when_unapproved(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        [, $product] = $this->makeStoreWithProduct('private');

        $response = $this->actingAs($affiliate)->get(route('affiliate.product.show', $product->id));

        $response->assertNotFound();
    }

    public function test_product_detail_page_is_reachable_once_the_affiliate_is_approved(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        [$store, $product] = $this->makeStoreWithProduct('private');
        StoreAffiliateRequest::forceCreate([
            'store_id' => $store->id, 'user_id' => $affiliate->id, 'status' => StoreAffiliateRequest::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($affiliate)->get(route('affiliate.product.show', $product->id));

        $response->assertOk();
        $response->assertSee('Detail Page Product');
    }

    public function test_product_detail_page_is_404_for_a_product_with_no_commission_rule(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        [, $product] = $this->makeStoreWithProduct('public');
        CommissionRule::where('scope_id', $product->id)->delete();

        $response = $this->actingAs($affiliate)->get(route('affiliate.product.show', $product->id));

        $response->assertNotFound();
    }
}
