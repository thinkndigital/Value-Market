<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\PaymentRequest;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/CHANGELOG_FEATURE_AUDIT.md (v1.0.7, "Admin can process affiliate payouts"): confirmed genuinely
 * missing - no affiliate-facing withdrawal submission existed (the admin side already fully supports any
 * user_id via PaymentRequest, so only the affiliate self-service submission needed building).
 * AffiliateController::requestWithdrawal() mirrors Seller\PaymentRequestController::
 * add_withdrawal_request()'s pattern, including the IDOR fix applied to that method in the same pass: the
 * authenticated user's own id is always used, never a client-supplied one.
 */
class AffiliateWithdrawalTest extends TestCase
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

    private function makeCustomer(float $balance = 500): User
    {
        return User::forceCreate([
            'username' => 'affiliate_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'balance' => $balance,
        ]);
    }

    public function test_a_customer_can_request_a_withdrawal_from_their_affiliate_balance(): void
    {
        $this->shareBaseViewData();
        $customer = $this->makeCustomer(300);

        $response = $this->actingAs($customer)->postJson(route('affiliate.withdrawal.request'), [
            'payment_address' => 'UPI: affiliate@bank',
            'amount' => 150,
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', false);
        $this->assertDatabaseHas('payment_requests', [
            'user_id' => $customer->id, 'payment_type' => 'affiliate', 'amount_requested' => 150,
        ]);
        $this->assertEquals(150, $customer->fresh()->balance);
    }

    public function test_a_withdrawal_request_over_the_available_balance_is_rejected(): void
    {
        $this->shareBaseViewData();
        $customer = $this->makeCustomer(50);

        $response = $this->actingAs($customer)->postJson(route('affiliate.withdrawal.request'), [
            'payment_address' => 'UPI: affiliate@bank',
            'amount' => 500,
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', true);
        $this->assertDatabaseMissing('payment_requests', ['user_id' => $customer->id]);
        $this->assertEquals(50, $customer->fresh()->balance, 'A rejected request must not touch the balance.');
    }

    public function test_a_withdrawal_request_cannot_be_submitted_for_another_users_balance(): void
    {
        $this->shareBaseViewData();
        $attacker = $this->makeCustomer(10);
        $victim = $this->makeCustomer(1000);

        // Even if a client sends a user_id for someone else, the authenticated caller's own id is what
        // gets used - mirrors the seller-panel IDOR fix applied in the same pass.
        $response = $this->actingAs($attacker)->postJson(route('affiliate.withdrawal.request'), [
            'user_id' => $victim->id,
            'payment_address' => 'UPI: attacker@bank',
            'amount' => 500,
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', true);
        $this->assertEquals(1000, $victim->fresh()->balance, "The attacker's request must never touch the victim's balance.");
        $this->assertDatabaseMissing('payment_requests', ['user_id' => $victim->id]);
    }

    public function test_withdrawal_history_scopes_to_the_logged_in_affiliate(): void
    {
        $this->shareBaseViewData();
        $customer = $this->makeCustomer();
        $otherCustomer = $this->makeCustomer();

        PaymentRequest::forceCreate([
            'user_id' => $customer->id, 'payment_address' => 'UPI: mine@bank', 'payment_type' => 'affiliate',
            'amount_requested' => 100, 'status' => 0,
        ]);
        PaymentRequest::forceCreate([
            'user_id' => $otherCustomer->id, 'payment_address' => 'UPI: other@bank', 'payment_type' => 'affiliate',
            'amount_requested' => 200, 'status' => 0,
        ]);

        $response = $this->actingAs($customer)->getJson(route('affiliate.withdrawal.history'));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('UPI: mine@bank', $data[0]['payment_address']);
    }
}
