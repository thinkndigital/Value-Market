<?php

namespace Tests\Feature;

use App\Models\AffiliateLink;
use App\Models\Currency;
use App\Models\ReferralConversion;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The reference eShop Plus product has a dedicated affiliate portal - its own login page, separate from
 * admin/seller/delivery_boy, where any affiliate (a customer or a seller) signs in and sees their own link
 * and earnings. Phase 7 (docs/PHASE_7_AFFILIATE_ENGINE.md) built the tracking/commission engine but never
 * this front door to it - only a JSON self-service API existed. Adds the login page
 * (AffiliateAuthController, unrestricted by role unlike Admin\UserController::authenticate()) and the
 * dashboard (AffiliateController::dashboard(), auto-creating a platform-wide link on first visit).
 */
class AffiliatePortalTest extends TestCase
{
    use RefreshDatabase;

    private function shareBaseViewData(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market'])]);
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

    private function makeCustomer(string $mobile = '0799181518', string $password = 'a-strong-password'): User
    {
        return User::forceCreate([
            'username' => 'customer_' . uniqid(), 'password' => Hash::make($password), 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'active' => 1,
            'mobile' => $mobile,
        ]);
    }

    public function test_login_page_renders(): void
    {
        $this->shareBaseViewData();

        $response = $this->get(route('affiliate.login'));

        $response->assertOk();
    }

    public function test_a_customer_can_log_in_to_the_affiliate_portal(): void
    {
        $this->shareBaseViewData();
        $this->makeCustomer('0799181518', 'a-strong-password');

        $response = $this->post(route('affiliate.authenticate'), [
            'mobile' => '0799181518',
            'password' => 'a-strong-password',
        ]);

        $response->assertOk();
        $response->assertJson(['message' => 'Login successful']);
        $this->assertAuthenticated();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->shareBaseViewData();
        $this->makeCustomer('0799181518', 'a-strong-password');

        $response = $this->post(route('affiliate.authenticate'), [
            'mobile' => '0799181518',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_dashboard_auto_creates_a_platform_link_on_first_visit(): void
    {
        $this->shareBaseViewData();
        $customer = $this->makeCustomer();

        $this->assertSame(0, AffiliateLink::where('user_id', $customer->id)->count());

        $response = $this->actingAs($customer)->get(route('affiliate.dashboard'));

        $response->assertOk();
        $link = AffiliateLink::where('user_id', $customer->id)->first();
        $this->assertNotNull($link);
        $this->assertSame(AffiliateLink::TARGET_PLATFORM, $link->target_type);
        $response->assertSee($link->code);
    }

    public function test_dashboard_reuses_an_existing_link_instead_of_creating_a_second_one(): void
    {
        $this->shareBaseViewData();
        $customer = $this->makeCustomer();
        $existingLink = AffiliateLink::forceCreate([
            'user_id' => $customer->id, 'target_type' => AffiliateLink::TARGET_PLATFORM, 'target_id' => null,
            'code' => 'existingcode', 'status' => AffiliateLink::STATUS_ACTIVE,
        ]);

        $this->actingAs($customer)->get(route('affiliate.dashboard'));

        $this->assertSame(1, AffiliateLink::where('user_id', $customer->id)->count());
    }

    public function test_dashboard_shows_approved_and_pending_commission_totals(): void
    {
        $this->shareBaseViewData();
        $customer = $this->makeCustomer();
        $link = AffiliateLink::forceCreate([
            'user_id' => $customer->id, 'target_type' => AffiliateLink::TARGET_PLATFORM, 'target_id' => null,
            'code' => 'codeforcommission', 'status' => AffiliateLink::STATUS_ACTIVE,
        ]);
        ReferralConversion::forceCreate([
            'affiliate_link_id' => $link->id, 'order_id' => 1, 'buyer_user_id' => null, 'order_total' => 100,
            'commission_rate_type' => 'percentage', 'commission_rate_value' => 5, 'commission_amount' => 5,
            'status' => ReferralConversion::STATUS_APPROVED,
        ]);
        ReferralConversion::forceCreate([
            'affiliate_link_id' => $link->id, 'order_id' => 2, 'buyer_user_id' => null, 'order_total' => 40,
            'commission_rate_type' => 'percentage', 'commission_rate_value' => 5, 'commission_amount' => 2,
            'status' => ReferralConversion::STATUS_PENDING,
        ]);

        $response = $this->actingAs($customer)->get(route('affiliate.dashboard'));

        $response->assertOk();
        $response->assertSee('5', false);
        $response->assertSee('2', false);
    }
}
