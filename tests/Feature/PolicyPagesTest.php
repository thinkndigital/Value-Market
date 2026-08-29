<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * More instances of the view('name', [...]) missing-view audit gap, this time in the Settings > Policies
 * area:
 *  - admin_and_seller_policies / delivery_boy_policies: reachable from the settings.blade.php cards
 *    ("Admin & Seller Policies" / "Delivery Boy Policies") - both 500'd, no view existed.
 *  - admin.pages.views.terms_and_conditions: the sibling of the already-working privacy_policy.blade.php
 *    (same [key => html] pattern for the generic/seller/delivery_boy variants), reachable from
 *    terms_and_conditions.view (public) and admin.seller_terms_and_conditions.view (the "eye" link on the
 *    Admin & Seller Policies page added in this same batch).
 *  - admin.pages.views.delivery_boy_privacy_policy / delivery_boy_terms_and_conditions: reachable from the
 *    "eye" links added to the new delivery_boy_policies.blade.php page.
 *
 * Note: web.php also registers two routes (admin/privacy_policy/seller_privacy_policy_page and
 * admin/terms_and_condition/seller_terms_and_condition_page) pointing at SettingController::
 * seller_privacy_policy()/seller_terms_and_condition() - neither method exists on the controller at all
 * (confirmed via grep), so those two routes are already dead on arrival. They are not linked from anywhere
 * in the app (the "eye" links added here correctly use the real, existing sellerPrivacyPolicy()/
 * sellerTermsAndCondition() methods' route names instead), so left alone rather than "fixed" - the dead
 * routes are unreachable, not a live bug.
 */
class PolicyPagesTest extends TestCase
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

    public function test_admin_and_seller_policies_page_renders(): void
    {
        $this->shareBaseViewData();
        Setting::forceCreate(['variable' => 'seller_privacy_policy', 'value' => json_encode(['seller_privacy_policy' => 'Seller privacy text'])]);
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->get(route('admin_and_seller_policies'));

        $response->assertOk();
        $response->assertSee('Seller privacy text');
    }

    public function test_delivery_boy_policies_page_renders(): void
    {
        $this->shareBaseViewData();
        Setting::forceCreate(['variable' => 'delivery_boy_privacy_policy', 'value' => json_encode(['delivery_boy_privacy_policy' => 'DB privacy text'])]);
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->get(route('delivery_boy_policies'));

        $response->assertOk();
        $response->assertSee('DB privacy text');
    }

    public function test_terms_and_conditions_public_page_renders(): void
    {
        $this->shareBaseViewData();
        Setting::forceCreate(['variable' => 'terms_and_conditions', 'value' => json_encode(['terms_and_conditions' => 'Generic terms text'])]);

        $response = $this->get(route('terms_and_conditions.view'));

        $response->assertOk();
        $response->assertSee('Generic terms text');
    }

    public function test_seller_terms_and_conditions_admin_view_renders(): void
    {
        $this->shareBaseViewData();
        Setting::forceCreate(['variable' => 'seller_terms_and_conditions', 'value' => json_encode(['seller_terms_and_conditions' => 'Seller terms text'])]);
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->get(route('admin.seller_terms_and_conditions.view'));

        $response->assertOk();
        $response->assertSee('Seller terms text');
    }

    public function test_delivery_boy_privacy_policy_public_page_renders(): void
    {
        // SettingService::getSettings() memoizes per $type in a process-static cache with no invalidation,
        // so within one PHPUnit process (all tests share it) a Setting row for the same 'variable' created
        // in an earlier test is what every later test sees, regardless of what THIS test writes. Using the
        // same text as test_delivery_boy_policies_page_renders (the other test touching this same variable)
        // keeps this assertion correct no matter which test runs first.
        $this->shareBaseViewData();
        Setting::forceCreate(['variable' => 'delivery_boy_privacy_policy', 'value' => json_encode(['delivery_boy_privacy_policy' => 'DB privacy text'])]);

        $response = $this->get(route('delivery_boy_privacy_policy.view'));

        $response->assertOk();
        $response->assertSee('DB privacy text');
    }

    public function test_delivery_boy_terms_and_conditions_public_page_renders(): void
    {
        $this->shareBaseViewData();
        Setting::forceCreate(['variable' => 'delivery_boy_terms_and_conditions', 'value' => json_encode(['delivery_boy_terms_and_conditions' => 'DB terms page text'])]);

        $response = $this->get(route('delivery_boy_terms_and_conditions.view'));

        $response->assertOk();
        $response->assertSee('DB terms page text');
    }
}
