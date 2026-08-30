<?php

namespace Tests\Feature\Phase6;

use App\Http\Controllers\Admin\SettingController;
use App\Libraries\HyperPay;
use App\Libraries\PayTabs;
use App\Libraries\TapPayments;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Follow-up to docs/PHASE_6B_JORDAN_GULF_GATEWAYS.md, which left "no admin-level platform-wide settings
 * UI" as an explicit gap for HyperPay/PayTabs/Tap - the constructors already read this platform-default
 * fallback, there was just no form to populate it. This closes that: Admin\SettingController::
 * storePaymentSetting() now validates and saves the 3 gateways' fields, and the payment_settings.blade.php
 * form gained 3 new tabs mirroring the existing Razorpay/Paystack ones exactly.
 */
class AdminGatewaySettingsTest extends TestCase
{
    use RefreshDatabase;

    private function loginAdmin(): void
    {
        $admin = User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN,
        ]);
        Auth::login($admin);
    }

    public function test_saving_hyperpay_settings_persists_them_and_the_library_picks_them_up_as_the_platform_default(): void
    {
        $this->loginAdmin();

        app(SettingController::class)->storePaymentSetting(new Request([
            'hyperpay_method' => '1', 'hyperpay_entity_id' => 'entity_xyz',
            'hyperpay_access_token' => 'token_xyz', 'hyperpay_mode' => 'live',
        ]));

        $saved = json_decode(Setting::where('variable', 'payment_method')->value('value'), true);
        $this->assertSame('entity_xyz', $saved['hyperpay_entity_id']);
        $this->assertSame(1, $saved['hyperpay_method']);

        $hyperpay = new HyperPay(); // no seller override - must fall back to what was just saved
        $this->assertSame('entity_xyz', $hyperpay->entity_id);
        $this->assertSame('live', $hyperpay->mode);
    }

    public function test_saving_paytabs_settings_persists_them_and_the_library_picks_them_up(): void
    {
        $this->loginAdmin();

        app(SettingController::class)->storePaymentSetting(new Request([
            'paytabs_method' => '1', 'paytabs_profile_id' => 'profile_xyz',
            'paytabs_server_key' => 'server_xyz', 'paytabs_region' => 'JOR',
        ]));

        $paytabs = new PayTabs();
        $this->assertSame('profile_xyz', $paytabs->profile_id);
        $this->assertSame('JOR', $paytabs->region);
    }

    public function test_saving_tap_settings_persists_them_and_the_library_picks_them_up(): void
    {
        $this->loginAdmin();

        app(SettingController::class)->storePaymentSetting(new Request([
            'tap_method' => '1', 'tap_secret_key' => 'sk_live_xyz', 'tap_publishable_key' => 'pk_live_xyz',
        ]));

        $tap = new TapPayments();
        $this->assertSame('sk_live_xyz', $tap->secret_key);
    }

    public function test_enabling_hyperpay_without_required_fields_is_rejected(): void
    {
        $this->loginAdmin();
        $request = new Request(['hyperpay_method' => '1']);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = app(SettingController::class)->storePaymentSetting($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertNull(Setting::where('variable', 'payment_method')->first());
    }

    public function test_a_sellers_own_override_still_wins_over_the_new_platform_hyperpay_default(): void
    {
        $this->loginAdmin();
        app(SettingController::class)->storePaymentSetting(new Request([
            'hyperpay_method' => '1', 'hyperpay_entity_id' => 'platform_entity',
            'hyperpay_access_token' => 'platform_token', 'hyperpay_mode' => 'live',
        ]));

        $sellerUser = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = \App\Models\Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        \App\Models\SellerPaymentGateway::forceCreate([
            'seller_id' => $seller->id, 'gateway' => 'hyperpay',
            'credentials' => ['hyperpay_entity_id' => 'seller_entity', 'hyperpay_access_token' => 'seller_token', 'hyperpay_mode' => 'test'],
            'is_enabled' => true,
        ]);

        $hyperpay = new HyperPay($seller->id);
        $this->assertSame('seller_entity', $hyperpay->entity_id, 'The seller override must still win over the new platform default.');
    }

    public function test_the_payment_settings_page_renders_the_three_new_tabs(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market', 'favicon' => ''])]);
        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'system_settings' => ['app_name' => 'Value Market', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);
        $this->loginAdmin();

        $response = app(SettingController::class)->paymentSettings();
        $rendered = $response->render();

        $this->assertStringContainsString('id="hyperpay"', $rendered);
        $this->assertStringContainsString('id="paytabs"', $rendered);
        $this->assertStringContainsString('id="tap"', $rendered);
    }
}
