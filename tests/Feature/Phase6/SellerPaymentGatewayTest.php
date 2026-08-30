<?php

namespace Tests\Feature\Phase6;

use App\Libraries\Razorpay;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerPaymentGateway;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\SellerPaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 32-phase SaaS brief, Phase 6 (docs/PHASE_6_PAYMENT_GATEWAYS.md): a seller can store their own gateway
 * credentials (encrypted at rest via SellerPaymentGateway's `encrypted:array` cast), which take priority
 * over the platform-global default. Mirrors this app's established seller-panel IDOR-test pattern
 * (tests/Feature/SellerAffiliateProgramTest.php) - a seller must never read, edit, or benefit from
 * another seller's credentials.
 */
class SellerPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    /** Only the two tests that render a real Blade view (seller.payment_gateways.index) need this. */
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

    public function test_a_seller_can_save_and_enable_their_own_razorpay_credentials(): void
    {
        [$user, $seller] = $this->makeSeller('0791000000');

        $response = $this->actingAs($user)->putJson(route('seller.payment_gateways.update'), [
            'gateway' => 'razorpay', 'enabled' => 1,
            'razorpay_key_id' => 'rzp_test_key', 'razorpay_secret_key' => 'rzp_test_secret',
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', false);
        $this->assertDatabaseHas('seller_payment_gateways', ['seller_id' => $seller->id, 'gateway' => 'razorpay', 'is_enabled' => 1]);

        $row = SellerPaymentGateway::where('seller_id', $seller->id)->where('gateway', 'razorpay')->first();
        $this->assertSame('rzp_test_key', $row->credentials['razorpay_key_id']);
        $this->assertSame('rzp_test_secret', $row->credentials['razorpay_secret_key']);
    }

    public function test_credentials_are_encrypted_at_rest_not_stored_as_plaintext(): void
    {
        [$user, $seller] = $this->makeSeller('0791000001');
        $this->actingAs($user)->putJson(route('seller.payment_gateways.update'), [
            'gateway' => 'razorpay', 'enabled' => 1,
            'razorpay_key_id' => 'rzp_plaintext_probe', 'razorpay_secret_key' => 'super_secret_value',
        ]);

        $rawColumn = \DB::table('seller_payment_gateways')->where('seller_id', $seller->id)->value('credentials');

        $this->assertStringNotContainsString('rzp_plaintext_probe', $rawColumn);
        $this->assertStringNotContainsString('super_secret_value', $rawColumn);
    }

    public function test_the_index_page_never_echoes_a_previously_saved_secret(): void
    {
        $this->shareBaseViewData();
        [$user] = $this->makeSeller('0791000002');
        $this->actingAs($user)->putJson(route('seller.payment_gateways.update'), [
            'gateway' => 'razorpay', 'enabled' => 1,
            'razorpay_key_id' => 'rzp_should_not_render', 'razorpay_secret_key' => 'secret_should_not_render',
        ]);

        $response = $this->actingAs($user)->get(route('seller.payment_gateways.index'));

        $response->assertOk();
        $response->assertDontSee('rzp_should_not_render');
        $response->assertDontSee('secret_should_not_render');
    }

    public function test_a_seller_cannot_read_or_overwrite_another_sellers_gateway_row(): void
    {
        $this->shareBaseViewData();
        [$victim, $victimSeller] = $this->makeSeller('0791000003');
        $this->actingAs($victim)->putJson(route('seller.payment_gateways.update'), [
            'gateway' => 'razorpay', 'enabled' => 1,
            'razorpay_key_id' => 'victim_key', 'razorpay_secret_key' => 'victim_secret',
        ]);

        [$attacker] = $this->makeSeller('0791000004');
        $this->actingAs($attacker)->putJson(route('seller.payment_gateways.update'), [
            'gateway' => 'razorpay', 'enabled' => 1,
            'razorpay_key_id' => 'attacker_key', 'razorpay_secret_key' => 'attacker_secret',
        ]);

        // The attacker's own update must not have touched the victim's row (scoped by their own seller_id,
        // resolved server-side from Auth::id() - never a client-suppliable seller_id).
        $victimRow = SellerPaymentGateway::where('seller_id', $victimSeller->id)->where('gateway', 'razorpay')->first();
        $this->assertSame('victim_key', $victimRow->credentials['razorpay_key_id']);

        $attackerView = $this->actingAs($attacker)->get(route('seller.payment_gateways.index'));
        $attackerView->assertDontSee('victim_key');
    }

    public function test_disabling_does_not_wipe_out_the_previously_saved_credentials(): void
    {
        [$user, $seller] = $this->makeSeller('0791000005');
        $this->actingAs($user)->putJson(route('seller.payment_gateways.update'), [
            'gateway' => 'razorpay', 'enabled' => 1,
            'razorpay_key_id' => 'keep_me_key', 'razorpay_secret_key' => 'keep_me_secret',
        ]);

        $response = $this->actingAs($user)->putJson(route('seller.payment_gateways.update'), [
            'gateway' => 'razorpay', 'enabled' => 0,
        ]);

        $response->assertJsonPath('error', false);
        $row = SellerPaymentGateway::where('seller_id', $seller->id)->where('gateway', 'razorpay')->first();
        $this->assertFalse((bool) $row->is_enabled);
        $this->assertSame('keep_me_key', $row->credentials['razorpay_key_id'], 'Disabling must not blank out saved credentials.');
    }

    public function test_a_seller_can_remove_their_own_gateway_row(): void
    {
        [$user, $seller] = $this->makeSeller('0791000006');
        $this->actingAs($user)->putJson(route('seller.payment_gateways.update'), [
            'gateway' => 'razorpay', 'enabled' => 1,
            'razorpay_key_id' => 'x', 'razorpay_secret_key' => 'y',
        ]);

        $response = $this->actingAs($user)->deleteJson(route('seller.payment_gateways.destroy'), ['gateway' => 'razorpay']);

        $response->assertJsonPath('error', false);
        $this->assertDatabaseMissing('seller_payment_gateways', ['seller_id' => $seller->id, 'gateway' => 'razorpay']);
    }

    public function test_service_returns_seller_credentials_only_when_enabled(): void
    {
        [$user, $seller] = $this->makeSeller('0791000007');
        SellerPaymentGateway::forceCreate([
            'seller_id' => $seller->id, 'gateway' => 'razorpay',
            'credentials' => ['razorpay_key_id' => 'k', 'razorpay_secret_key' => 's'], 'is_enabled' => false,
        ]);

        $service = app(SellerPaymentGatewayService::class);
        $this->assertNull($service->credentialsFor($seller->id, 'razorpay'), 'A disabled row must fall back to the platform default.');

        SellerPaymentGateway::where('seller_id', $seller->id)->update(['is_enabled' => true]);
        $this->assertSame('k', $service->credentialsFor($seller->id, 'razorpay')['razorpay_key_id']);
    }

    public function test_service_falls_back_to_null_when_no_seller_or_no_row_exists(): void
    {
        $service = app(SellerPaymentGatewayService::class);
        $this->assertNull($service->credentialsFor(null, 'razorpay'));
        $this->assertNull($service->credentialsFor(999999, 'razorpay'));
    }

    public function test_resolve_seller_id_for_store_maps_a_store_to_its_owning_seller(): void
    {
        [, $seller, $store] = $this->makeSeller('0791000008');

        $service = app(SellerPaymentGatewayService::class);
        $this->assertSame($seller->id, $service->resolveSellerIdForStore($store->id));
        $this->assertNull($service->resolveSellerIdForStore(999999));
    }

    public function test_razorpay_library_uses_the_sellers_own_credentials_when_configured(): void
    {
        [, $seller] = $this->makeSeller('0791000009');
        SellerPaymentGateway::forceCreate([
            'seller_id' => $seller->id, 'gateway' => 'razorpay',
            'credentials' => ['razorpay_key_id' => 'seller_specific_key', 'razorpay_secret_key' => 'seller_specific_secret'],
            'is_enabled' => true,
        ]);

        $razorpay = new Razorpay($seller->id);

        $this->assertSame('seller_specific_key', $razorpay->key_id);
        $this->assertSame('seller_specific_secret', $razorpay->secret_key);
    }

    public function test_razorpay_library_falls_back_to_the_platform_default_when_no_seller_override_exists(): void
    {
        \App\Models\Setting::forceCreate([
            'variable' => 'payment_method',
            'value' => json_encode(['razorpay_key_id' => 'platform_key', 'razorpay_secret_key' => 'platform_secret']),
        ]);

        $razorpayNoSeller = new Razorpay();
        $this->assertSame('platform_key', $razorpayNoSeller->key_id);

        [, $seller] = $this->makeSeller('0791000010');
        $razorpayUnconfiguredSeller = new Razorpay($seller->id);
        $this->assertSame('platform_key', $razorpayUnconfiguredSeller->key_id, 'A seller with no override row must still get the platform default.');
    }
}
