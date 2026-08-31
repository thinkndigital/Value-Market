<?php

namespace Tests\Feature;

use App\Models\AffiliateLink;
use App\Models\AffiliateStore;
use App\Models\AffiliateStoreProduct;
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
 * Master architecture prompt Phase 7 (Affiliate architecture, section 26 "Affiliate Store" - a
 * mini-store/landing page, per the section 80 final acceptance criteria "Affiliate can create a
 * mini-store/landing page"). A featured product is really just an existing AffiliateLink the affiliate
 * already generated (their "My Products" list), so the public page's clicks reuse the same tracked
 * redirect as everywhere else - no separate tracking mechanism.
 */
class AffiliateStoreTest extends TestCase
{
    use RefreshDatabase;

    private function shareBaseViewData(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market', 'favicon' => ''])]);
        Setting::forceCreate(['variable' => 'web_settings', 'value' => json_encode([])]);
        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'system_settings' => ['app_name' => 'Value Market', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);
    }

    private function makeAffiliate(): User
    {
        return User::forceCreate([
            'username' => 'aff_store_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
    }

    private function makeProduct(): Product
    {
        $sellerUser = User::forceCreate([
            'username' => 'aff_store_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        $store = Store::forceCreate([
            'name' => json_encode(['en' => 'Store ' . uniqid()]), 'slug' => 'store-' . uniqid(),
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => $store->id,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Featured Seller', 'disk' => 'public', 'status' => 1,
        ]);

        return Product::forceCreate([
            'category_id' => 1, 'seller_id' => $seller->id, 'store_id' => $store->id,
            'name' => json_encode(['en' => 'Featurable Product']), 'slug' => 'product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'status' => 1,
        ]);
    }

    public function test_saving_store_settings_creates_a_draft_store_with_a_slug(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();

        $response = $this->actingAs($affiliate)->post(route('affiliate.my_store.update'), [
            'name' => 'My Cool Store', 'description' => 'Best deals!',
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', false);
        $this->assertDatabaseHas('affiliate_stores', ['user_id' => $affiliate->id, 'name' => 'My Cool Store', 'status' => AffiliateStore::STATUS_DRAFT]);
    }

    public function test_a_draft_store_404s_publicly_even_with_the_right_slug(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        $store = AffiliateStore::create(['user_id' => $affiliate->id, 'slug' => 'my-draft-store', 'name' => 'Draft', 'status' => AffiliateStore::STATUS_DRAFT]);

        $this->get(route('affiliate.store.show', $store->slug))->assertNotFound();
    }

    public function test_publishing_makes_the_store_reachable_publicly_with_its_featured_product(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        $product = $this->makeProduct();
        $link = app(AffiliateService::class)->getOrCreateProductLink($affiliate->id, $product->id);

        $store = AffiliateStore::create(['user_id' => $affiliate->id, 'slug' => 'live-store', 'name' => 'Live Store', 'status' => AffiliateStore::STATUS_PUBLISHED]);
        AffiliateStoreProduct::create(['affiliate_store_id' => $store->id, 'affiliate_link_id' => $link->id, 'sort_order' => 0]);

        $response = $this->get(route('affiliate.store.show', $store->slug));

        $response->assertOk();
        $response->assertSee('Live Store');
        $response->assertSee('Featurable Product');
        $response->assertSee(route('affiliate.track', ['code' => $link->code]), false);
    }

    public function test_only_the_affiliates_own_link_can_be_featured(): void
    {
        $this->shareBaseViewData();
        $owner = $this->makeAffiliate();
        $stranger = $this->makeAffiliate();
        $product = $this->makeProduct();
        $strangersLink = app(AffiliateService::class)->getOrCreateProductLink($stranger->id, $product->id);

        AffiliateStore::create(['user_id' => $owner->id, 'slug' => 'owner-store', 'name' => 'Owner Store', 'status' => AffiliateStore::STATUS_DRAFT]);

        $response = $this->actingAs($owner)->postJson(route('affiliate.my_store.products.add'), ['link_id' => $strangersLink->id]);

        $response->assertJsonPath('error', true);
        $this->assertDatabaseMissing('affiliate_store_products', ['affiliate_link_id' => $strangersLink->id]);
    }

    public function test_toggle_publish_only_affects_the_logged_in_affiliates_own_store(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        $store = AffiliateStore::create(['user_id' => $affiliate->id, 'slug' => 'toggle-store', 'name' => 'Toggle Store', 'status' => AffiliateStore::STATUS_DRAFT]);

        $response = $this->actingAs($affiliate)->putJson(route('affiliate.my_store.publish'), ['status' => 1]);

        $response->assertJsonPath('error', false);
        $this->assertSame(AffiliateStore::STATUS_PUBLISHED, $store->fresh()->status);
    }

    public function test_removing_a_featured_product_takes_it_off_the_public_page(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        $product = $this->makeProduct();
        $link = app(AffiliateService::class)->getOrCreateProductLink($affiliate->id, $product->id);
        $store = AffiliateStore::create(['user_id' => $affiliate->id, 'slug' => 'remove-store', 'name' => 'Remove Store', 'status' => AffiliateStore::STATUS_PUBLISHED]);
        AffiliateStoreProduct::create(['affiliate_store_id' => $store->id, 'affiliate_link_id' => $link->id, 'sort_order' => 0]);

        $this->actingAs($affiliate)->deleteJson(route('affiliate.my_store.products.remove'), ['link_id' => $link->id])
            ->assertJsonPath('error', false);

        $response = $this->get(route('affiliate.store.show', $store->slug));
        $response->assertOk();
        $response->assertDontSee('Featurable Product');
    }

    public function test_manage_page_renders_with_no_store_yet(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();

        $this->actingAs($affiliate)->get(route('affiliate.my_store.page'))->assertOk();
    }
}
