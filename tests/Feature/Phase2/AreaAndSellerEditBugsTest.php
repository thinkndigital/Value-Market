<?php

namespace Tests\Feature\Phase2;

use App\Http\Controllers\Admin\AreaController;
use App\Models\Area;
use App\Models\City;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Store;
use App\Models\User;
use App\Models\Zipcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two real bugs found by tests/Feature/Phase2/ParamRouteSweepBatch1Test.php (32-phase SaaS brief Phase 2,
 * continuing docs/PHASE_2_ROUTE_SWEEP_REPORT.md's deferred param-route scope), fixed in the same pass and
 * given direct, targeted regression coverage here.
 */
class AreaAndSellerEditBugsTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        return User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN, 'active' => 1,
        ]);
    }

    /** admin/area/edit/{id} (AreaController::areaEdit) was wired in routes/admin_routes.php to a method that never existed - a BadMethodCallException on every hit. */
    public function test_area_edit_returns_the_area_data_instead_of_a_missing_method_error(): void
    {
        $this->actingAs($this->makeSuperAdmin());
        $city = City::forceCreate(['name' => json_encode(['en' => 'City']), 'minimum_free_delivery_order_amount' => 0, 'delivery_charges' => 0]);
        $zipcode = Zipcode::forceCreate(['zipcode' => '11937', 'city_id' => $city->id, 'minimum_free_delivery_order_amount' => 0, 'delivery_charges' => 0]);
        $area = Area::forceCreate(['name' => json_encode(['en' => 'Area']), 'city_id' => $city->id, 'zipcode_id' => $zipcode->id, 'minimum_free_delivery_order_amount' => 0, 'delivery_charges' => 0]);

        $response = app(AreaController::class)->areaEdit($area->id);
        $data = json_decode($response->getContent(), true);

        $this->assertSame($area->id, $data['id']);
    }

    public function test_area_edit_returns_a_clean_404_for_an_unknown_id_not_a_crash(): void
    {
        $this->actingAs($this->makeSuperAdmin());

        $response = app(AreaController::class)->areaEdit(999999);

        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * Admin\SellerController::edit() used to unconditionally do $selected_zipcode_text[0]->zipcode even
     * when the branch just above it had already left $selected_zipcode_text as null (no zipcode set on
     * the seller's store) - "Trying to access array offset on null" on every such seller's edit page.
     * Hit through the real HTTP kernel (not a direct controller call) so SetDefaultStore's middleware
     * resolves session('store_id') the same way a real admin request would.
     */
    public function test_admin_can_open_a_sellers_edit_page_when_their_store_has_no_zipcode_set(): void
    {
        \App\Models\Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        \App\Models\Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market', 'favicon' => ''])]);
        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'system_settings' => ['app_name' => 'Value Market', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);

        Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store-' . uniqid(),
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);
        $sellerUser = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        // Deliberately no 'zipcode' set - the exact case that used to crash.
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 1,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'disk' => 'public', 'status' => 1,
            'category_ids' => '',
        ]);

        $response = $this->actingAs($this->makeSuperAdmin())->get("/admin/sellers/edit/{$sellerUser->id}");

        $response->assertOk();
    }
}
