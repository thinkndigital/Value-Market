<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/CHANGELOG_FEATURE_AUDIT.md (v1.1.2, "Interactive map added to address form"): the backend already
 * fully supported latitude/longitude (Address model/table, App\v1\ApiController's add_address/update_address
 * - all confirmed working, correcting this audit's own earlier draft claim that lat/lng was missing at the
 * data layer). What was actually missing is any UI to pick a location - this repo has no customer-facing
 * web storefront at all (no Blade views, and the React/Inertia packages in package.json have zero source
 * files under resources/js - vestigial, not a real frontend), so there was no "address form" in this repo to
 * attach a map to. Admin's Manage Customer Address page (manage_address.blade.php) was the one address-
 * editing surface that exists here - and it was read-only (no edit UI at all; AddressController's
 * index/create/edit/update are still-empty resource-controller stubs). Built a full edit flow on it: a
 * Leaflet/OpenStreetMap picker (vendored locally, no API key), a new admin.customers.address.update route
 * reusing the existing, already-validated AddressController::store(), and the "Edit" row action +
 * user_id/latitude/longitude fields added to getCustomersAddressesList()'s response to drive it.
 */
class AdminAddressMapTest extends TestCase
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

    private function makeSuperAdmin(): User
    {
        return User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN,
        ]);
    }

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'username' => 'customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
    }

    public function test_manage_address_page_renders_with_the_map_component_and_edit_action(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->get(route('admin.customers.getCustomersAddresses'));

        $response->assertOk();
        $response->assertSee('address_map_container', false);
        $response->assertSee('edit-address', false);
        $response->assertSee('initAddressMap', false);
    }

    public function test_addresses_list_includes_user_id_and_coordinates_for_the_edit_form(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();
        $customer = $this->makeCustomer();
        Address::forceCreate([
            'user_id' => $customer->id, 'name' => 'Home', 'type' => 'home', 'mobile' => '9999999999',
            'address' => '123 Test St', 'city' => 'Testville', 'state' => 'TS', 'country' => 'Testland',
            'latitude' => '12.9716', 'longitude' => '77.5946',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.customers.getCustomersAddressesList'));

        $response->assertOk();
        $response->assertJsonFragment(['user_id' => $customer->id, 'latitude' => '12.9716', 'longitude' => '77.5946']);
    }

    public function test_admin_can_update_a_customer_address_including_coordinates(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();
        $customer = $this->makeCustomer();
        $address = Address::forceCreate([
            'user_id' => $customer->id, 'name' => 'Home', 'type' => 'home', 'mobile' => '9999999999',
            'address' => '123 Test St', 'city' => 'Testville', 'state' => 'TS', 'country' => 'Testland',
            'latitude' => '', 'longitude' => '',
        ]);

        $response = $this->actingAs($admin)->putJson(route('admin.customers.address.update'), [
            'id' => $address->id,
            'user_id' => $customer->id,
            'name' => 'Updated Home',
            'latitude' => '12.9716',
            'longitude' => '77.5946',
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', false);
        $address->refresh();
        $this->assertSame('Updated Home', $address->name);
        $this->assertSame('12.9716', $address->latitude);
        $this->assertSame('77.5946', $address->longitude);
    }

    public function test_admin_update_rejects_a_user_id_that_does_not_own_the_address(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();
        $owner = $this->makeCustomer();
        $attacker = $this->makeCustomer();
        $address = Address::forceCreate([
            'user_id' => $owner->id, 'name' => 'Home', 'type' => 'home', 'mobile' => '9999999999',
            'address' => '123 Test St', 'city' => 'Testville', 'state' => 'TS', 'country' => 'Testland',
        ]);

        // Confirms the pre-existing ownership guard inside AddressController::store() still holds when
        // reached through the new admin route - a mismatched user_id must not silently move someone else's
        // address data around.
        $this->actingAs($admin)->putJson(route('admin.customers.address.update'), [
            'id' => $address->id,
            'user_id' => $attacker->id,
            'name' => 'Hijacked',
        ]);

        $this->assertSame('Home', $address->fresh()->name, 'A mismatched user_id must not update the address.');
    }
}
