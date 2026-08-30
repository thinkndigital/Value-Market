<?php

namespace Tests\Feature\Phase6;

use App\Libraries\HyperPay;
use App\Libraries\PayTabs;
use App\Libraries\TapPayments;
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
 * Follow-up to docs/PHASE_6_PAYMENT_GATEWAYS.md: the product owner deprioritized Paystack/Phonepe (and
 * further Razorpay work) in favor of gateways real for Jordan/the Gulf - HyperPay, PayTabs, and Tap
 * Payments (docs/PHASE_6B_JORDAN_GULF_GATEWAYS.md). Same credential-resolution and seller-ownership
 * coverage as tests/Feature/Phase6/SellerPaymentGatewayTest.php's Razorpay tests, one per gateway, plus
 * the store->seller resolution these three (unlike Razorpay, which also supports the order-id path) rely
 * on exclusively for their CartController wiring.
 */
class JordanGulfGatewaysTest extends TestCase
{
    use RefreshDatabase;

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
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $user->id, 'store_id' => $store->id,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store ' . $mobile, 'disk' => 'public', 'status' => 1,
        ]);

        return [$user, $seller, $store];
    }

    // ---- HyperPay ----

    public function test_a_seller_can_save_hyperpay_credentials_via_the_shared_crud(): void
    {
        [$user, $seller] = $this->makeSeller('0792000000');

        $response = $this->actingAs($user)->putJson(route('seller.payment_gateways.update'), [
            'gateway' => 'hyperpay', 'enabled' => 1,
            'hyperpay_entity_id' => 'entity_123', 'hyperpay_access_token' => 'token_abc', 'hyperpay_mode' => 'test',
        ]);

        $response->assertJsonPath('error', false);
        $row = SellerPaymentGateway::where('seller_id', $seller->id)->where('gateway', 'hyperpay')->first();
        $this->assertSame('entity_123', $row->credentials['hyperpay_entity_id']);
    }

    public function test_hyperpay_library_uses_the_sellers_own_credentials_when_configured(): void
    {
        [, $seller] = $this->makeSeller('0792000001');
        SellerPaymentGateway::forceCreate([
            'seller_id' => $seller->id, 'gateway' => 'hyperpay',
            'credentials' => ['hyperpay_entity_id' => 'seller_entity', 'hyperpay_access_token' => 'seller_token', 'hyperpay_mode' => 'live'],
            'is_enabled' => true,
        ]);

        $hyperpay = new HyperPay($seller->id);

        $this->assertSame('seller_entity', $hyperpay->entity_id);
        $this->assertSame('live', $hyperpay->mode);
    }

    public function test_hyperpay_falls_back_to_the_platform_default_with_no_seller_override(): void
    {
        Setting::forceCreate([
            'variable' => 'payment_method',
            'value' => json_encode(['hyperpay_entity_id' => 'platform_entity', 'hyperpay_access_token' => 'platform_token']),
        ]);

        $this->assertSame('platform_entity', (new HyperPay())->entity_id);
    }

    public function test_hyperpay_is_successful_matches_the_documented_success_code_family(): void
    {
        $hyperpay = new HyperPay();
        $this->assertTrue($hyperpay->is_successful('000.100.110'));
        $this->assertTrue($hyperpay->is_successful('000.000.000'));
        $this->assertFalse($hyperpay->is_successful('800.100.150'));
        $this->assertFalse($hyperpay->is_successful(''));
    }

    // ---- PayTabs ----

    public function test_a_seller_can_save_paytabs_credentials_via_the_shared_crud(): void
    {
        [$user, $seller] = $this->makeSeller('0792000002');

        $response = $this->actingAs($user)->putJson(route('seller.payment_gateways.update'), [
            'gateway' => 'paytabs', 'enabled' => 1,
            'paytabs_profile_id' => 'profile_123', 'paytabs_server_key' => 'server_abc', 'paytabs_region' => 'JOR',
        ]);

        $response->assertJsonPath('error', false);
        $row = SellerPaymentGateway::where('seller_id', $seller->id)->where('gateway', 'paytabs')->first();
        $this->assertSame('JOR', $row->credentials['paytabs_region']);
    }

    public function test_paytabs_library_uses_the_sellers_own_credentials_and_resolves_the_regional_host(): void
    {
        [, $seller] = $this->makeSeller('0792000003');
        SellerPaymentGateway::forceCreate([
            'seller_id' => $seller->id, 'gateway' => 'paytabs',
            'credentials' => ['paytabs_profile_id' => 'seller_profile', 'paytabs_server_key' => 'seller_key', 'paytabs_region' => 'JOR'],
            'is_enabled' => true,
        ]);

        $paytabs = new PayTabs($seller->id);

        $this->assertSame('seller_profile', $paytabs->profile_id);
        $this->assertSame('JOR', $paytabs->region);
    }

    public function test_paytabs_falls_back_to_the_global_region_for_an_unrecognized_code(): void
    {
        Setting::forceCreate([
            'variable' => 'payment_method',
            'value' => json_encode(['paytabs_region' => 'NOT_A_REAL_REGION']),
        ]);

        $paytabs = new PayTabs();
        $this->assertSame('NOT_A_REAL_REGION', $paytabs->region);
    }

    public function test_paytabs_is_successful_only_for_the_authorised_response_status(): void
    {
        $paytabs = new PayTabs();
        $this->assertTrue($paytabs->is_successful(['response_status' => 'A']));
        $this->assertFalse($paytabs->is_successful(['response_status' => 'D']));
        $this->assertFalse($paytabs->is_successful(null));
    }

    // ---- Tap Payments ----

    public function test_a_seller_can_save_tap_credentials_via_the_shared_crud(): void
    {
        [$user, $seller] = $this->makeSeller('0792000004');

        $response = $this->actingAs($user)->putJson(route('seller.payment_gateways.update'), [
            'gateway' => 'tap', 'enabled' => 1,
            'tap_secret_key' => 'sk_test_123', 'tap_publishable_key' => 'pk_test_123',
        ]);

        $response->assertJsonPath('error', false);
        $row = SellerPaymentGateway::where('seller_id', $seller->id)->where('gateway', 'tap')->first();
        $this->assertSame('sk_test_123', $row->credentials['tap_secret_key']);
    }

    public function test_tap_library_uses_the_sellers_own_credentials_when_configured(): void
    {
        [, $seller] = $this->makeSeller('0792000005');
        SellerPaymentGateway::forceCreate([
            'seller_id' => $seller->id, 'gateway' => 'tap',
            'credentials' => ['tap_secret_key' => 'seller_secret', 'tap_publishable_key' => 'seller_pub'],
            'is_enabled' => true,
        ]);

        $tap = new TapPayments($seller->id);

        $this->assertSame('seller_secret', $tap->secret_key);
    }

    public function test_tap_is_successful_only_for_captured_status(): void
    {
        $tap = new TapPayments();
        $this->assertTrue($tap->is_successful('CAPTURED'));
        $this->assertFalse($tap->is_successful('DECLINED'));
        $this->assertFalse($tap->is_successful(null));
    }

    // ---- IDOR / ownership, shared across all three gateways via the same controller ----

    public function test_a_seller_cannot_overwrite_another_sellers_hyperpay_row(): void
    {
        [$victim, $victimSeller] = $this->makeSeller('0792000006');
        $this->actingAs($victim)->putJson(route('seller.payment_gateways.update'), [
            'gateway' => 'hyperpay', 'enabled' => 1,
            'hyperpay_entity_id' => 'victim_entity', 'hyperpay_access_token' => 'victim_token', 'hyperpay_mode' => 'test',
        ]);

        [$attacker] = $this->makeSeller('0792000007');
        $this->actingAs($attacker)->putJson(route('seller.payment_gateways.update'), [
            'gateway' => 'hyperpay', 'enabled' => 1,
            'hyperpay_entity_id' => 'attacker_entity', 'hyperpay_access_token' => 'attacker_token', 'hyperpay_mode' => 'test',
        ]);

        $victimRow = SellerPaymentGateway::where('seller_id', $victimSeller->id)->where('gateway', 'hyperpay')->first();
        $this->assertSame('victim_entity', $victimRow->credentials['hyperpay_entity_id']);
    }

    // ---- Store -> seller resolution, the path CartController::pre_payment_setup() relies on for all three ----

    public function test_resolve_seller_id_for_store_works_for_the_checkout_time_gateway_wiring(): void
    {
        [, $seller, $store] = $this->makeSeller('0792000008');

        $resolved = app(SellerPaymentGatewayService::class)->resolveSellerIdForStore($store->id);

        $this->assertSame($seller->id, $resolved);
    }
}
