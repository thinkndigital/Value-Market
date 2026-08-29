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
 * Seller-facing side of the affiliate engine (2025_02_09_000000 migration, docs/PHASE_7_AFFILIATE_ENGINE.md):
 * a seller opts their own products into the program with their own commission rate, and chooses whether
 * their catalog is public (any affiliate sees it) or private (request + approval first). Every scenario
 * here mirrors this app's established IDOR-test pattern - a seller must never be able to touch another
 * seller's products, store settings, or join requests.
 */
class SellerAffiliateProgramTest extends TestCase
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

    private function makeSeller(string $mobile): array
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'mobile' => $mobile,
        ]);
        $seller = Seller::forceCreate(['user_id' => $user->id, 'disk' => 'public', 'status' => 1]);
        $store = Store::forceCreate([
            'name' => json_encode(['en' => 'Store ' . $mobile]), 'slug' => 'store-' . uniqid(),
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);
        $sellerStore = SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $user->id, 'store_id' => $store->id,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store ' . $mobile, 'disk' => 'public', 'status' => 1,
        ]);

        return [$user, $seller, $store, $sellerStore];
    }

    private function makeProduct(int $sellerId, int $storeId): Product
    {
        return Product::forceCreate([
            'category_id' => 1, 'seller_id' => $sellerId, 'store_id' => $storeId,
            'name' => json_encode(['en' => 'Product ' . uniqid()]), 'slug' => 'product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'status' => 1,
        ]);
    }

    public function test_the_affiliate_program_page_renders_with_products_and_requests(): void
    {
        $this->shareBaseViewData();
        [$user, $seller, $store] = $this->makeSeller('0790000000');
        $product = $this->makeProduct($seller->id, $store->id);
        $this->actingAs($user)->postJson(route('seller.affiliate_program.products.toggle'), [
            'product_id' => $product->id, 'enabled' => 1, 'rate_type' => 'percentage', 'rate_value' => 8,
        ]);
        $affiliateUser = User::forceCreate([
            'username' => 'affiliate_page_test', 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
        StoreAffiliateRequest::forceCreate([
            'store_id' => $store->id, 'user_id' => $affiliateUser->id, 'status' => StoreAffiliateRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($user)->get(route('seller.affiliate_program.index'));

        $response->assertOk();
        $response->assertSee('affiliate_page_test');
    }

    public function test_a_seller_can_enable_commission_on_their_own_product(): void
    {
        $this->shareBaseViewData();
        [$user, $seller, , ] = $this->makeSeller('0790000001');
        $product = $this->makeProduct($seller->id, 1);

        $response = $this->actingAs($user)->postJson(route('seller.affiliate_program.products.toggle'), [
            'product_id' => $product->id, 'enabled' => 1, 'rate_type' => 'percentage', 'rate_value' => 10,
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', false);
        $this->assertDatabaseHas('commission_rules', [
            'scope' => CommissionRule::SCOPE_PRODUCT, 'scope_id' => $product->id,
            'rate_type' => 'percentage', 'rate_value' => 10, 'status' => CommissionRule::STATUS_ACTIVE,
        ]);
    }

    public function test_a_seller_cannot_enable_commission_on_another_sellers_product(): void
    {
        [$attacker] = $this->makeSeller('0790000002');
        [, $victimSeller] = $this->makeSeller('0790000003');
        $victimProduct = $this->makeProduct($victimSeller->id, 1);

        $response = $this->actingAs($attacker)->postJson(route('seller.affiliate_program.products.toggle'), [
            'product_id' => $victimProduct->id, 'enabled' => 1, 'rate_type' => 'percentage', 'rate_value' => 50,
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', true);
        $this->assertDatabaseMissing('commission_rules', ['scope' => CommissionRule::SCOPE_PRODUCT, 'scope_id' => $victimProduct->id]);
    }

    public function test_disabling_keeps_the_rate_row_but_flips_it_inactive(): void
    {
        [$user, $seller] = $this->makeSeller('0790000004');
        $product = $this->makeProduct($seller->id, 1);
        $this->actingAs($user)->postJson(route('seller.affiliate_program.products.toggle'), [
            'product_id' => $product->id, 'enabled' => 1, 'rate_type' => 'flat', 'rate_value' => 5,
        ]);

        $response = $this->actingAs($user)->postJson(route('seller.affiliate_program.products.toggle'), [
            'product_id' => $product->id, 'enabled' => 0,
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', false);
        $this->assertDatabaseHas('commission_rules', [
            'scope_id' => $product->id, 'rate_value' => 5, 'status' => CommissionRule::STATUS_INACTIVE,
        ]);
    }

    public function test_a_seller_can_switch_their_own_store_to_private(): void
    {
        [$user, , $store] = $this->makeSeller('0790000005');

        $response = $this->actingAs($user)->postJson(route('seller.affiliate_program.visibility'), [
            'affiliate_visibility' => 'private',
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', false);
        $this->assertDatabaseHas('seller_store', ['store_id' => $store->id, 'affiliate_visibility' => 'private']);
    }

    public function test_a_seller_can_approve_a_join_request_for_their_own_store_only(): void
    {
        [$owner, , $store] = $this->makeSeller('0790000006');
        [$otherSeller, , $otherStore] = $this->makeSeller('0790000007');
        $affiliateUser = User::forceCreate([
            'username' => 'affiliate_x', 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
        $request = StoreAffiliateRequest::forceCreate([
            'store_id' => $store->id, 'user_id' => $affiliateUser->id, 'status' => StoreAffiliateRequest::STATUS_PENDING,
        ]);
        $otherStoreRequest = StoreAffiliateRequest::forceCreate([
            'store_id' => $otherStore->id, 'user_id' => $affiliateUser->id, 'status' => StoreAffiliateRequest::STATUS_PENDING,
        ]);

        // The store owner can approve a request against their own store.
        $ok = $this->actingAs($owner)->postJson(route('seller.affiliate_program.requests.respond'), [
            'request_id' => $request->id, 'status' => 'approved',
        ]);
        $ok->assertJsonPath('error', false);
        $this->assertSame('approved', $request->fresh()->status);

        // A seller cannot approve a request that belongs to a different store.
        $blocked = $this->actingAs($owner)->postJson(route('seller.affiliate_program.requests.respond'), [
            'request_id' => $otherStoreRequest->id, 'status' => 'approved',
        ]);
        $blocked->assertJsonPath('error', true);
        $this->assertSame('pending', $otherStoreRequest->fresh()->status);
    }
}
