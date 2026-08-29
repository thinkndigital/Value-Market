<?php

namespace Tests\Feature;

use App\Models\ComboProduct;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/CHANGELOG_FEATURE_AUDIT.md (v1.0.11, "Sellers can deactivate empty stores" / "Sellers can delete
 * empty stores"): confirmed genuinely missing - no self-service store deactivate/delete endpoint existed for
 * sellers at all. Both actions are gated on the store having zero products (regular or combo) - a seller
 * with live listings must not be able to make their storefront disappear out from under active
 * orders/customers.
 */
class SellerStoreDeactivateDeleteTest extends TestCase
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

    /** @return array{0: User, 1: Seller, 2: SellerStore} */
    private function makeSeller(): array
    {
        $sellerUser = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public']);
        $sellerStore = SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 1,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Test Store', 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '', 'status' => 1,
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);

        return [$sellerUser, $seller, $sellerStore];
    }

    public function test_a_seller_can_deactivate_their_own_empty_store(): void
    {
        $this->shareBaseViewData();
        [$sellerUser, , $sellerStore] = $this->makeSeller();

        $response = $this->actingAs($sellerUser)->withSession(['store_id' => 1])->get(route('seller.store.deactivate'));

        $response->assertOk();
        $response->assertJsonPath('error', false);
        $this->assertSame(0, $sellerStore->fresh()->status);
    }

    public function test_a_seller_cannot_deactivate_a_store_that_still_has_products(): void
    {
        $this->shareBaseViewData();
        [$sellerUser, $seller, $sellerStore] = $this->makeSeller();
        Product::forceCreate([
            'category_id' => 1, 'seller_id' => $seller->id, 'store_id' => 1,
            'name' => json_encode(['en' => 'Live Product']), 'slug' => 'live-product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'status' => 1,
        ]);

        $response = $this->actingAs($sellerUser)->withSession(['store_id' => 1])->get(route('seller.store.deactivate'));

        $response->assertOk();
        $response->assertJsonPath('error', true);
        $this->assertSame(1, $sellerStore->fresh()->status, 'A store with a live product must not be deactivatable.');
    }

    public function test_a_seller_cannot_deactivate_a_store_that_still_has_combo_products(): void
    {
        $this->shareBaseViewData();
        [$sellerUser, $seller, $sellerStore] = $this->makeSeller();
        ComboProduct::forceCreate([
            'title' => json_encode(['en' => 'Combo Deal']), 'short_description' => json_encode(['en' => 'x']),
            'seller_id' => $seller->id, 'store_id' => 1,
            'product_type' => 'physical_product', 'slug' => 'combo-' . uniqid(), 'image' => '',
        ]);

        $response = $this->actingAs($sellerUser)->withSession(['store_id' => 1])->get(route('seller.store.deactivate'));

        $response->assertOk();
        $response->assertJsonPath('error', true);
        $this->assertSame(1, $sellerStore->fresh()->status);
    }

    public function test_a_seller_can_delete_their_own_empty_store(): void
    {
        $this->shareBaseViewData();
        [$sellerUser, , $sellerStore] = $this->makeSeller();

        $response = $this->actingAs($sellerUser)->withSession(['store_id' => 1])->get(route('seller.store.destroy'));

        $response->assertOk();
        $response->assertJsonPath('error', false);
        $this->assertDatabaseMissing('seller_store', ['id' => $sellerStore->id]);
    }

    public function test_a_seller_cannot_delete_a_store_that_still_has_products(): void
    {
        $this->shareBaseViewData();
        [$sellerUser, $seller, $sellerStore] = $this->makeSeller();
        Product::forceCreate([
            'category_id' => 1, 'seller_id' => $seller->id, 'store_id' => 1,
            'name' => json_encode(['en' => 'Live Product']), 'slug' => 'live-product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'status' => 1,
        ]);

        $response = $this->actingAs($sellerUser)->withSession(['store_id' => 1])->get(route('seller.store.destroy'));

        $response->assertOk();
        $response->assertJsonPath('error', true);
        $this->assertDatabaseHas('seller_store', ['id' => $sellerStore->id]);
    }

    public function test_a_seller_cannot_affect_another_sellers_store(): void
    {
        $this->shareBaseViewData();
        [, , $victimStore] = $this->makeSeller();
        [$attackerUser] = $this->makeSeller();

        $response = $this->actingAs($attackerUser)->withSession(['store_id' => 1])->get(route('seller.store.destroy'));

        // The attacker has their own empty SellerStore row for store_id=1, so this deletes *that* row, not
        // the victim's - proving the scoping is by the authenticated seller's own id, not a client-supplied
        // one, since no id is ever accepted from the request at all.
        $response->assertOk();
        $this->assertDatabaseHas('seller_store', ['id' => $victimStore->id]);
    }
}
