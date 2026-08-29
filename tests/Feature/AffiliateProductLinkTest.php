<?php

namespace Tests\Feature;

use App\Models\AffiliateLink;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/CHANGELOG_FEATURE_AUDIT.md (v1.0.7, "Generate unique product referral links"): the backend already
 * fully supported this - AffiliateService::createLink()/AffiliateController::store() already accept
 * target_type='product' - what was confirmed missing was any way for an affiliate to find a product id to
 * generate a link for, since this repo has no customer-facing web storefront to browse products from.
 * AffiliateController::searchProducts() adds a minimal name-search endpoint for the affiliate portal's own
 * "Generate a Product Link" widget; these tests cover the search endpoint and the full generate-a-link flow
 * it feeds into (the store() endpoint itself was already covered by Phase 7's own tests).
 */
class AffiliateProductLinkTest extends TestCase
{
    use RefreshDatabase;

    private function shareBaseViewData(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market', 'favicon' => ''])]);
        Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);
        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'system_settings' => ['app_name' => 'Value Market', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);
    }

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'username' => 'affiliate_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
    }

    private function makeProduct(string $name, bool $active = true): Product
    {
        return Product::forceCreate([
            'category_id' => 1, 'seller_id' => 1, 'store_id' => 1,
            'name' => json_encode(['en' => $name]), 'slug' => 'product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'status' => $active ? 1 : 0,
        ]);
    }

    public function test_search_returns_matching_active_products(): void
    {
        $this->shareBaseViewData();
        $customer = $this->makeCustomer();
        $wireless = $this->makeProduct('Wireless Mouse');
        $this->makeProduct('Desk Lamp');

        $response = $this->actingAs($customer)->getJson(route('affiliate.products.search', ['search' => 'Wireless']));

        $response->assertOk();
        $response->assertJsonPath('error', false);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($wireless->id, $data[0]['id']);
        $this->assertSame('Wireless Mouse', $data[0]['name']);
    }

    public function test_search_excludes_inactive_products(): void
    {
        $this->shareBaseViewData();
        $customer = $this->makeCustomer();
        $this->makeProduct('Hidden Gadget', active: false);

        $response = $this->actingAs($customer)->getJson(route('affiliate.products.search', ['search' => 'Hidden']));

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_an_affiliate_can_generate_a_product_specific_link_and_it_lists_in_their_links(): void
    {
        $this->shareBaseViewData();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct('Bluetooth Speaker');

        $response = $this->actingAs($customer)->postJson(route('affiliate.links.store'), [
            'target_type' => 'product',
            'target_id' => $product->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', false);
        $this->assertDatabaseHas('affiliate_links', [
            'user_id' => $customer->id, 'target_type' => AffiliateLink::TARGET_PRODUCT, 'target_id' => $product->id,
        ]);

        $listResponse = $this->actingAs($customer)->getJson(route('affiliate.links.list'));
        $listResponse->assertOk();
        $productLinks = collect($listResponse->json('data'))->where('target_type', AffiliateLink::TARGET_PRODUCT);
        $this->assertCount(1, $productLinks);
        $this->assertSame($product->id, $productLinks->first()['target_id']);
    }

    public function test_generating_a_second_link_for_the_same_product_creates_a_distinct_code(): void
    {
        $this->shareBaseViewData();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct('Bluetooth Speaker');

        $first = $this->actingAs($customer)->postJson(route('affiliate.links.store'), [
            'target_type' => 'product',
            'target_id' => $product->id,
        ])->json('data');

        $second = $this->actingAs($customer)->postJson(route('affiliate.links.store'), [
            'target_type' => 'product',
            'target_id' => $product->id,
        ])->json('data');

        $this->assertNotSame($first['code'], $second['code']);
    }
}
