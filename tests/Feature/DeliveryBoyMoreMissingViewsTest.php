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
 * More instances of the view('name', [...]) gap the first missing-view audit's regex couldn't see (fixed
 * for admin.manage_system_users and friends using view('name') with no second argument only) -
 * delivery_boy.cash.collection (the sidebar's "Cash Collection" link) and delivery_boy.orders.index (the
 * sidebar's "Orders Management" link) both 500'd the same way.
 */
class DeliveryBoyMoreMissingViewsTest extends TestCase
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
            'balance' => 0, 'bonus' => 0, 'cash_received' => 0,
        ]);
    }

    public function test_cash_collection_page_renders(): void
    {
        $this->shareBaseViewData();
        $deliveryBoy = $this->makeDeliveryBoy();

        $response = $this->actingAs($deliveryBoy)->get(route('delivery_boy.cash.collection'));

        $response->assertOk();
    }

    public function test_manage_orders_page_renders(): void
    {
        $this->shareBaseViewData();
        $deliveryBoy = $this->makeDeliveryBoy();

        $response = $this->actingAs($deliveryBoy)->get(route('delivery_boy.orders.index'));

        $response->assertOk();
    }
}
