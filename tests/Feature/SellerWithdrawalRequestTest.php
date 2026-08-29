<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\PaymentRequest;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * seller.payment_request.withdrawal_requests (the seller sidebar's "Withdrawal Requests" link) ->
 * PaymentRequestController::withdrawal_requests() -> seller.pages.tables.withdrawal_request, which did not
 * exist - another instance of the view('name', [...]) missing-view audit gap. Modeled on the delivery_boy
 * Wallet Transaction page fixed earlier this session (same PaymentRequestController::add_withdrawal_request()
 * submit path, $fromDeliveryBoyApp defaulting to false here so payment_type is correctly recorded as
 * 'seller').
 */
class SellerWithdrawalRequestTest extends TestCase
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

    private function makeSeller(float $balance = 500): array
    {
        $sellerUser = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'balance' => $balance,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public']);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 1,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Test Store', 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);

        return [$sellerUser, $seller];
    }

    public function test_withdrawal_request_page_renders_with_wallet_balance(): void
    {
        $this->shareBaseViewData();
        [$sellerUser] = $this->makeSeller(750);

        $response = $this->actingAs($sellerUser)->get(route('seller.payment_request.withdrawal_requests'));

        $response->assertOk();
        $response->assertSee('750');
    }

    public function test_withdrawal_request_list_endpoint_scopes_to_the_logged_in_seller(): void
    {
        $this->shareBaseViewData();
        [$sellerUser] = $this->makeSeller();
        [$otherSellerUser] = $this->makeSeller();

        PaymentRequest::forceCreate([
            'user_id' => $sellerUser->id, 'payment_address' => 'UPI: seller@bank', 'payment_type' => 'seller',
            'amount_requested' => 100, 'status' => 0,
        ]);
        PaymentRequest::forceCreate([
            'user_id' => $otherSellerUser->id, 'payment_address' => 'UPI: other@bank', 'payment_type' => 'seller',
            'amount_requested' => 200, 'status' => 0,
        ]);

        $response = $this->actingAs($sellerUser)->get(route('seller.payment_request.get_payment_request_list'));

        $response->assertOk();
        $response->assertJsonCount(1, 'rows');
        $response->assertJsonFragment(['payment_address' => 'UPI: seller@bank']);
    }

    public function test_add_withdrawal_request_creates_a_seller_payment_request(): void
    {
        $this->shareBaseViewData();
        [$sellerUser] = $this->makeSeller(300);

        $response = $this->actingAs($sellerUser)->putJson(route('seller.payment_request.add_withdrawal_request'), [
            'user_id' => $sellerUser->id,
            'payment_address' => 'Bank: 12345',
            'amount' => 150,
        ]);

        $response->assertOk();
        $response->assertJsonPath('error', false);
        $this->assertDatabaseHas('payment_requests', [
            'user_id' => $sellerUser->id, 'payment_type' => 'seller', 'amount_requested' => 150,
        ]);
    }
}
