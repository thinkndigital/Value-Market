<?php

namespace Tests\Feature\Wholesaler;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Models\Wholesaler;
use App\Models\WholesalerProduct;
use App\Models\WholesalerProductPriceTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Master architecture prompt Phase 6 (Supplier architecture, section 18 "Wholesale" group): quantity-break
 * and seller-specific pricing tiers on a wholesaler's listing - see WholesalerProduct::priceFor() and
 * docs/WHOLESALER_MODULE.md's Phase 6 section.
 */
class WholesalePricingTest extends TestCase
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

        $otherSellerUser = User::forceCreate([
            'username' => 'wh_seller_other_' . uniqid(), 'password' => Hash::make('password'), 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'active' => 1,
        ]);
        $otherSeller = Seller::forceCreate(['user_id' => $otherSellerUser->id, 'disk' => 'public', 'status' => 1]);

        return compact('store', 'category', 'sellerUser', 'seller', 'otherSellerUser', 'otherSeller');
    }

    private function makeWholesaler(): array
    {
        $user = User::forceCreate([
            'username' => 'wholesaler_' . uniqid(), 'mobile' => '9' . random_int(100000000, 999999999),
            'password' => Hash::make('password123'), 'disk' => 'public', 'serviceable_cities' => '',
            'type' => 'phone', 'role_id' => Role::WHOLESALER, 'active' => 1,
        ]);
        $wholesaler = Wholesaler::create([
            'user_id' => $user->id, 'business_name' => 'QA Wholesale Co', 'status' => 1, 'disk' => 'public',
        ]);

        return compact('user', 'wholesaler');
    }

    public function test_price_for_falls_back_to_flat_wholesale_price_with_no_tiers(): void
    {
        $fixtures = $this->baseFixtures();
        ['wholesaler' => $wholesaler] = $this->makeWholesaler();

        $wp = WholesalerProduct::create([
            'wholesaler_id' => $wholesaler->id, 'category_id' => $fixtures['category']->id,
            'name' => json_encode(['en' => 'Widget']), 'wholesale_price' => 10, 'min_order_qty' => 1,
            'status' => 1, 'slug' => 'widget-' . uniqid(),
        ]);

        $this->assertSame(10.0, $wp->priceFor($fixtures['seller']->id, 5));
    }

    public function test_price_for_picks_the_highest_matching_generic_tier(): void
    {
        $fixtures = $this->baseFixtures();
        ['wholesaler' => $wholesaler] = $this->makeWholesaler();

        $wp = WholesalerProduct::create([
            'wholesaler_id' => $wholesaler->id, 'category_id' => $fixtures['category']->id,
            'name' => json_encode(['en' => 'Widget']), 'wholesale_price' => 10, 'min_order_qty' => 1,
            'status' => 1, 'slug' => 'widget-' . uniqid(),
        ]);
        WholesalerProductPriceTier::create(['wholesaler_product_id' => $wp->id, 'seller_id' => null, 'min_quantity' => 10, 'unit_price' => 8]);
        WholesalerProductPriceTier::create(['wholesaler_product_id' => $wp->id, 'seller_id' => null, 'min_quantity' => 50, 'unit_price' => 6]);

        $this->assertSame(10.0, $wp->priceFor($fixtures['seller']->id, 5), 'below every tier -> flat price');
        $this->assertSame(8.0, $wp->priceFor($fixtures['seller']->id, 10), 'exactly at the 10-tier threshold');
        $this->assertSame(8.0, $wp->priceFor($fixtures['seller']->id, 49), 'between the 10 and 50 tiers');
        $this->assertSame(6.0, $wp->priceFor($fixtures['seller']->id, 100), 'above the 50-tier threshold');
    }

    public function test_seller_specific_tier_wins_over_a_generic_tier_at_the_same_quantity(): void
    {
        $fixtures = $this->baseFixtures();
        ['wholesaler' => $wholesaler] = $this->makeWholesaler();

        $wp = WholesalerProduct::create([
            'wholesaler_id' => $wholesaler->id, 'category_id' => $fixtures['category']->id,
            'name' => json_encode(['en' => 'Widget']), 'wholesale_price' => 10, 'min_order_qty' => 1,
            'status' => 1, 'slug' => 'widget-' . uniqid(),
        ]);
        WholesalerProductPriceTier::create(['wholesaler_product_id' => $wp->id, 'seller_id' => null, 'min_quantity' => 10, 'unit_price' => 8]);
        WholesalerProductPriceTier::create(['wholesaler_product_id' => $wp->id, 'seller_id' => $fixtures['seller']->id, 'min_quantity' => 10, 'unit_price' => 5]);

        $this->assertSame(5.0, $wp->priceFor($fixtures['seller']->id, 10), 'the negotiated seller gets its own price');
        $this->assertSame(8.0, $wp->priceFor($fixtures['otherSeller']->id, 10), 'a different seller still gets the generic tier');
    }

    public function test_placing_an_order_uses_the_resolved_tiered_price_not_the_flat_price(): void
    {
        $fixtures = $this->baseFixtures();
        ['wholesaler' => $wholesaler] = $this->makeWholesaler();

        $wp = WholesalerProduct::create([
            'wholesaler_id' => $wholesaler->id, 'category_id' => $fixtures['category']->id,
            'name' => json_encode(['en' => 'Widget']), 'wholesale_price' => 10, 'min_order_qty' => 1,
            'status' => 1, 'slug' => 'widget-' . uniqid(),
        ]);
        WholesalerProductPriceTier::create(['wholesaler_product_id' => $wp->id, 'seller_id' => null, 'min_quantity' => 20, 'unit_price' => 7]);

        $response = $this->actingAs($fixtures['sellerUser'])
            ->withSession(['store_id' => $fixtures['store']->id])
            ->postJson('seller/wholesaler_marketplace/' . $wp->id . '/order', [
                'quantity' => 20, 'retail_price' => 15,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('wholesale_orders', [
            'wholesaler_product_id' => $wp->id, 'quantity' => 20, 'unit_price' => 7, 'total_amount' => 140,
        ]);
    }

    public function test_price_preview_endpoint_reflects_a_seller_specific_tier(): void
    {
        $fixtures = $this->baseFixtures();
        ['wholesaler' => $wholesaler] = $this->makeWholesaler();

        $wp = WholesalerProduct::create([
            'wholesaler_id' => $wholesaler->id, 'category_id' => $fixtures['category']->id,
            'name' => json_encode(['en' => 'Widget']), 'wholesale_price' => 10, 'min_order_qty' => 1,
            'status' => 1, 'slug' => 'widget-' . uniqid(),
        ]);
        WholesalerProductPriceTier::create(['wholesaler_product_id' => $wp->id, 'seller_id' => $fixtures['seller']->id, 'min_quantity' => 5, 'unit_price' => 4.5]);

        $response = $this->actingAs($fixtures['sellerUser'])
            ->getJson('seller/wholesaler_marketplace/' . $wp->id . '/price?quantity=5');

        $response->assertOk();
        $response->assertJson(['unit_price' => 4.5, 'total_amount' => 22.5]);
    }

    public function test_wholesaler_can_add_a_price_tier_only_on_its_own_product(): void
    {
        $fixtures = $this->baseFixtures();
        ['user' => $ownerUser, 'wholesaler' => $owner] = $this->makeWholesaler();
        ['wholesaler' => $stranger] = $this->makeWholesaler();

        $wp = WholesalerProduct::create([
            'wholesaler_id' => $owner->id, 'category_id' => $fixtures['category']->id,
            'name' => json_encode(['en' => 'Widget']), 'wholesale_price' => 10, 'min_order_qty' => 1,
            'status' => 1, 'slug' => 'widget-' . uniqid(),
        ]);

        $ownerResponse = $this->actingAs($ownerUser)->postJson('wholesaler/pricing/' . $wp->id . '/tiers', [
            'min_quantity' => 10, 'unit_price' => 8,
        ]);
        $ownerResponse->assertOk();
        $this->assertDatabaseHas('wholesaler_product_price_tiers', ['wholesaler_product_id' => $wp->id, 'min_quantity' => 10, 'unit_price' => 8]);

        $strangerUser = $stranger->user;
        $strangerResponse = $this->actingAs($strangerUser)->postJson('wholesaler/pricing/' . $wp->id . '/tiers', [
            'min_quantity' => 10, 'unit_price' => 1,
        ]);
        $strangerResponse->assertStatus(404);
    }

    public function test_wholesaler_can_delete_its_own_price_tier(): void
    {
        $fixtures = $this->baseFixtures();
        ['user' => $ownerUser, 'wholesaler' => $owner] = $this->makeWholesaler();

        $wp = WholesalerProduct::create([
            'wholesaler_id' => $owner->id, 'category_id' => $fixtures['category']->id,
            'name' => json_encode(['en' => 'Widget']), 'wholesale_price' => 10, 'min_order_qty' => 1,
            'status' => 1, 'slug' => 'widget-' . uniqid(),
        ]);
        $tier = WholesalerProductPriceTier::create(['wholesaler_product_id' => $wp->id, 'seller_id' => null, 'min_quantity' => 10, 'unit_price' => 8]);

        $response = $this->actingAs($ownerUser)->deleteJson('wholesaler/pricing/' . $wp->id . '/tiers/' . $tier->id);

        $response->assertOk();
        $this->assertDatabaseMissing('wholesaler_product_price_tiers', ['id' => $tier->id]);
    }
}
