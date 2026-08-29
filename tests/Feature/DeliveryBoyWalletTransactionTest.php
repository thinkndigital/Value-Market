<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * delivery_boy.walletTransaction (Delivery_boy\UserController::walletTransaction() ->
 * delivery_boy.pages.tables.manage_customer_wallet, the sidebar's own "Wallet Transaction" link - distinct
 * from Fund Transfer/Cash Collection) 500'd like the other missing delivery_boy views: the first,
 * narrower audit only caught view('name') calls, not view('name', [...]) with a second argument, which is
 * how this one (and several others) were called. Matches the reference eShop Plus product's own delivery
 * boy Wallet Transaction page: a balance card, a "Withdraw Money" action (reusing the same
 * Seller\PaymentRequestController::add_withdrawal_request() delivery boys already share with sellers), and
 * a transaction history table.
 */
class DeliveryBoyWalletTransactionTest extends TestCase
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

    private function makeDeliveryBoy(): User
    {
        return User::forceCreate([
            'username' => 'delivery_boy_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::DELIVERY_BOY, 'active' => 1,
            'balance' => 500, 'bonus' => 0,
        ]);
    }

    public function test_wallet_transaction_page_renders_and_shows_balance(): void
    {
        $this->shareBaseViewData();
        $deliveryBoy = $this->makeDeliveryBoy();

        $response = $this->actingAs($deliveryBoy)->get(route('delivery_boy.walletTransaction'));

        $response->assertOk();
        $response->assertSee('500');
        // Same @push('scripts')-into-nowhere class of bug as commission_rules.blade.php - confirm the
        // withdraw-money submit handler actually rendered, not just that the page returned 200.
        $response->assertSee('withdraw_money_form', false);
    }

    public function test_transaction_list_endpoint_returns_this_delivery_boys_own_credit_debit_rows(): void
    {
        $this->shareBaseViewData();
        $deliveryBoy = $this->makeDeliveryBoy();
        Transaction::forceCreate([
            'user_id' => $deliveryBoy->id, 'type' => 'credit', 'amount' => 25, 'status' => 'success',
        ]);
        // A different transaction type must not leak into this list.
        Transaction::forceCreate([
            'user_id' => $deliveryBoy->id, 'type' => 'wallet', 'amount' => 999, 'status' => 'success',
        ]);

        $response = $this->actingAs($deliveryBoy)->getJson(route('delivery_boy.getTransactionList'));

        $response->assertOk();
        $body = $response->json();
        $this->assertCount(1, $body['rows']);
        $this->assertSame('credit', $body['rows'][0]['type']);
    }

    public function test_withdraw_money_creates_a_payment_request_within_balance(): void
    {
        $this->shareBaseViewData();
        $deliveryBoy = $this->makeDeliveryBoy();

        $response = $this->actingAs($deliveryBoy)->put(route('delivery_boy.payment_request.add_withdrawal_request'), [
            'user_id' => $deliveryBoy->id,
            'payment_address' => 'Bank of Example, IBAN 1234',
            'amount' => 100,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payment_requests', [
            'user_id' => $deliveryBoy->id, 'payment_type' => 'delivery_boy', 'amount_requested' => 100,
        ]);
    }
}
