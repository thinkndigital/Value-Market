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
 * The affiliate portal's auto-listed catalog (AffiliateController::availableProducts()) and the private-
 * store request flow behind it - the counterpart to SellerAffiliateProgramTest's seller-side coverage.
 * Visibility gating is the security-relevant part here: a private store's products must never leak to an
 * affiliate who hasn't been approved, the same way every other cross-tenant boundary in this app is tested.
 */
class AffiliateAvailableProductsTest extends TestCase
{
    use RefreshDatabase;

    private function shareBaseViewData(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market', 'favicon' => ''])]);
    }

    private function makeAffiliate(): User
    {
        return User::forceCreate([
            'username' => 'affiliate_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
    }

    private function makeStoreWithProduct(string $visibility, float $rate = 10.0): array
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
            'name' => json_encode(['en' => 'Affiliate-Enabled Product']), 'slug' => 'product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'status' => 1,
        ]);
        CommissionRule::forceCreate([
            'scope' => CommissionRule::SCOPE_PRODUCT, 'scope_id' => $product->id,
            'rate_type' => 'percentage', 'rate_value' => $rate, 'status' => CommissionRule::STATUS_ACTIVE,
        ]);

        return [$store, $product];
    }

    public function test_a_public_stores_enabled_product_is_visible_to_any_affiliate(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        [, $product] = $this->makeStoreWithProduct('public');

        $response = $this->actingAs($affiliate)->getJson(route('affiliate.available_products.list'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($product->id));
    }

    public function test_a_private_stores_product_is_hidden_from_an_unapproved_affiliate(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        [, $product] = $this->makeStoreWithProduct('private');

        $response = $this->actingAs($affiliate)->getJson(route('affiliate.available_products.list'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($product->id));
    }

    public function test_an_approved_affiliate_sees_the_private_stores_product(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        [$store, $product] = $this->makeStoreWithProduct('private');
        StoreAffiliateRequest::forceCreate([
            'store_id' => $store->id, 'user_id' => $affiliate->id, 'status' => StoreAffiliateRequest::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($affiliate)->getJson(route('affiliate.available_products.list'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($product->id));
    }

    public function test_a_disabled_products_commission_removes_it_from_the_catalog(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        [, $product] = $this->makeStoreWithProduct('public');
        CommissionRule::where('scope_id', $product->id)->update(['status' => CommissionRule::STATUS_INACTIVE]);

        $response = $this->actingAs($affiliate)->getJson(route('affiliate.available_products.list'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($product->id));
    }

    public function test_each_listed_product_carries_a_ready_to_copy_link(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        [, $product] = $this->makeStoreWithProduct('public');

        $response = $this->actingAs($affiliate)->getJson(route('affiliate.available_products.list'));

        $row = collect($response->json('data'))->firstWhere('id', $product->id);
        $this->assertNotNull($row['link_url']);
        $this->assertStringContainsString('/r/', $row['link_url']);
    }

    public function test_requesting_access_to_a_private_store_creates_a_pending_request(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        [$store] = $this->makeStoreWithProduct('private');

        $response = $this->actingAs($affiliate)->postJson(route('affiliate.stores.request'), ['store_id' => $store->id]);

        $response->assertOk();
        $response->assertJsonPath('error', false);
        $this->assertDatabaseHas('store_affiliate_requests', [
            'store_id' => $store->id, 'user_id' => $affiliate->id, 'status' => StoreAffiliateRequest::STATUS_PENDING,
        ]);
    }

    public function test_requesting_access_twice_does_not_create_a_duplicate_row(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        [$store] = $this->makeStoreWithProduct('private');

        $this->actingAs($affiliate)->postJson(route('affiliate.stores.request'), ['store_id' => $store->id]);
        $this->actingAs($affiliate)->postJson(route('affiliate.stores.request'), ['store_id' => $store->id]);

        $this->assertSame(1, StoreAffiliateRequest::where('store_id', $store->id)->where('user_id', $affiliate->id)->count());
    }

    public function test_cannot_request_access_to_a_public_store(): void
    {
        $this->shareBaseViewData();
        $affiliate = $this->makeAffiliate();
        [$store] = $this->makeStoreWithProduct('public');

        $response = $this->actingAs($affiliate)->postJson(route('affiliate.stores.request'), ['store_id' => $store->id]);

        $response->assertOk();
        $response->assertJsonPath('error', true);
        $this->assertDatabaseMissing('store_affiliate_requests', ['store_id' => $store->id, 'user_id' => $affiliate->id]);
    }
}
