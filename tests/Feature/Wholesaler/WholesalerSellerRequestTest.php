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
use App\Models\WholesalerSellerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Master architecture prompt Phase 6 (Supplier architecture, section 18 "Sellers" group: Explore Sellers /
 * Seller Requests / Approved Sellers / Pending Sellers). Mirrors the existing seller-managed affiliate
 * program's private-store request flow one level up: a wholesaler can gate its marketplace listing behind
 * approval. `buyer_visibility` defaults to 'public', so every pre-existing wholesaler (and every other
 * wholesaler test in this suite) keeps today's fully-open behavior unchanged.
 */
class WholesalerSellerRequestTest extends TestCase
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

        view()->share([
            'currency_symbol' => '$', 'currency_code' => 'USD',
            'system_settings' => ['app_name' => 'Value Market', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);

        return compact('store', 'category', 'sellerUser', 'seller');
    }

    private function makeWholesaler(string $visibility = 'public'): array
    {
        $user = User::forceCreate([
            'username' => 'wholesaler_' . uniqid(), 'mobile' => '9' . random_int(100000000, 999999999),
            'password' => Hash::make('password123'), 'disk' => 'public', 'serviceable_cities' => '',
            'type' => 'phone', 'role_id' => Role::WHOLESALER, 'active' => 1,
        ]);
        $wholesaler = Wholesaler::create([
            'user_id' => $user->id, 'business_name' => 'QA Wholesale Co', 'status' => 1, 'disk' => 'public',
            'buyer_visibility' => $visibility,
        ]);

        return compact('user', 'wholesaler');
    }

    private function makeProduct(array $fixtures, Wholesaler $wholesaler): WholesalerProduct
    {
        return WholesalerProduct::create([
            'wholesaler_id' => $wholesaler->id, 'category_id' => $fixtures['category']->id,
            'name' => json_encode(['en' => 'Widget']), 'wholesale_price' => 5, 'min_order_qty' => 1,
            'status' => 1, 'slug' => 'widget-' . uniqid(),
        ]);
    }

    public function test_a_public_wholesalers_products_are_visible_and_orderable_with_no_request(): void
    {
        $fixtures = $this->baseFixtures();
        ['wholesaler' => $wholesaler] = $this->makeWholesaler('public');
        $wp = $this->makeProduct($fixtures, $wholesaler);

        $listResponse = $this->actingAs($fixtures['sellerUser'])->getJson('seller/wholesaler_marketplace/list');
        $listResponse->assertOk();
        $listResponse->assertJsonFragment(['id' => $wp->id]);

        $orderResponse = $this->actingAs($fixtures['sellerUser'])
            ->withSession(['store_id' => $fixtures['store']->id])
            ->postJson('seller/wholesaler_marketplace/' . $wp->id . '/order', ['quantity' => 1, 'retail_price' => 10]);
        $orderResponse->assertOk();
    }

    public function test_a_private_wholesalers_products_are_hidden_from_an_unapproved_seller(): void
    {
        $fixtures = $this->baseFixtures();
        ['wholesaler' => $wholesaler] = $this->makeWholesaler('private');
        $wp = $this->makeProduct($fixtures, $wholesaler);

        $listResponse = $this->actingAs($fixtures['sellerUser'])->getJson('seller/wholesaler_marketplace/list');
        $listResponse->assertOk();
        $listResponse->assertJsonMissing(['id' => $wp->id]);

        $orderResponse = $this->actingAs($fixtures['sellerUser'])
            ->withSession(['store_id' => $fixtures['store']->id])
            ->postJson('seller/wholesaler_marketplace/' . $wp->id . '/order', ['quantity' => 1, 'retail_price' => 10]);
        $orderResponse->assertStatus(404);
    }

    public function test_a_private_wholesalers_products_become_visible_and_orderable_once_approved(): void
    {
        $fixtures = $this->baseFixtures();
        ['wholesaler' => $wholesaler] = $this->makeWholesaler('private');
        $wp = $this->makeProduct($fixtures, $wholesaler);

        WholesalerSellerRequest::create([
            'wholesaler_id' => $wholesaler->id, 'seller_id' => $fixtures['seller']->id,
            'status' => WholesalerSellerRequest::STATUS_APPROVED,
        ]);

        $listResponse = $this->actingAs($fixtures['sellerUser'])->getJson('seller/wholesaler_marketplace/list');
        $listResponse->assertJsonFragment(['id' => $wp->id]);

        $orderResponse = $this->actingAs($fixtures['sellerUser'])
            ->withSession(['store_id' => $fixtures['store']->id])
            ->postJson('seller/wholesaler_marketplace/' . $wp->id . '/order', ['quantity' => 1, 'retail_price' => 10]);
        $orderResponse->assertOk();
    }

    public function test_seller_can_request_access_to_a_private_wholesaler_and_it_starts_pending(): void
    {
        $fixtures = $this->baseFixtures();
        ['wholesaler' => $wholesaler] = $this->makeWholesaler('private');

        $response = $this->actingAs($fixtures['sellerUser'])->postJson('seller/wholesaler_marketplace/requests', [
            'wholesaler_id' => $wholesaler->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', false);
        $this->assertDatabaseHas('wholesaler_seller_requests', [
            'wholesaler_id' => $wholesaler->id, 'seller_id' => $fixtures['seller']->id, 'status' => 'pending',
        ]);
    }

    public function test_wholesaler_can_approve_a_pending_request_scoped_to_its_own_wholesaler(): void
    {
        $fixtures = $this->baseFixtures();
        ['user' => $ownerUser, 'wholesaler' => $owner] = $this->makeWholesaler('private');
        ['wholesaler' => $stranger] = $this->makeWholesaler('private');

        $request = WholesalerSellerRequest::create([
            'wholesaler_id' => $owner->id, 'seller_id' => $fixtures['seller']->id,
            'status' => WholesalerSellerRequest::STATUS_PENDING,
        ]);

        // A different wholesaler can't touch this request.
        $strangerUser = $stranger->user;
        $strangerResponse = $this->actingAs($strangerUser)->putJson('wholesaler/seller_requests/respond', [
            'request_id' => $request->id, 'status' => 'approved',
        ]);
        $strangerResponse->assertJsonPath('error', true);
        $this->assertDatabaseHas('wholesaler_seller_requests', ['id' => $request->id, 'status' => 'pending']);

        $ownerResponse = $this->actingAs($ownerUser)->putJson('wholesaler/seller_requests/respond', [
            'request_id' => $request->id, 'status' => 'approved',
        ]);
        $ownerResponse->assertJsonPath('error', false);
        $this->assertDatabaseHas('wholesaler_seller_requests', ['id' => $request->id, 'status' => 'approved']);
    }

    public function test_wholesaler_can_toggle_its_own_marketplace_visibility(): void
    {
        $this->baseFixtures();
        ['user' => $user, 'wholesaler' => $wholesaler] = $this->makeWholesaler('public');

        $response = $this->actingAs($user)->putJson('wholesaler/seller_requests/visibility', [
            'buyer_visibility' => 'private',
        ]);

        $response->assertJsonPath('error', false);
        $this->assertSame('private', $wholesaler->fresh()->buyer_visibility);
    }
}
