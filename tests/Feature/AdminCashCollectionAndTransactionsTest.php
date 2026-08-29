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
 * Two more instances of the view('name', [...]) missing-view audit gap, both under Delivery Boys / Customers
 * in the sidebar:
 *  - admin.get_cash_collection.index (sidebar "Cash Collection") -> CashCollectionController::index() ->
 *    admin.pages.tables.manage_cash_collection, which did not exist.
 *  - admin.customers.viewTransactions (sidebar "Manage Transactions" + each customer row's "View
 *    Transactions" action) -> UserController::viewTransactions() -> admin.pages.tables.manage_transactions,
 *    which did not exist.
 *
 * Building the cash collection page surfaced a real bug in CashCollectionController::list(): the row loop
 * was `foreach ($$txnSearchRes as $row)` (a PHP variable-variable, `$$txnSearchRes` dereferences the value
 * of $txnSearchRes as a variable NAME - there is no such variable, so this always threw "Undefined variable"
 * before ever iterating a single row). Fixed to `foreach ($txnSearchRes as $row)`. Without this fix the
 * page would render fine but its data table's AJAX endpoint would always 500.
 */
class AdminCashCollectionAndTransactionsTest extends TestCase
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

    public function test_manage_cash_collection_page_renders(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->get(route('admin.get_cash_collection.index'));

        $response->assertOk();
    }

    public function test_cash_collection_list_endpoint_does_not_500_and_returns_rows(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();
        $deliveryBoy = User::forceCreate([
            'username' => 'delivery_boy_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::DELIVERY_BOY, 'active' => 1,
            'mobile' => '9999999999', 'cash_received' => 500,
        ]);
        Transaction::forceCreate([
            'user_id' => $deliveryBoy->id, 'type' => 'delivery_boy_cash', 'amount' => 200, 'status' => 1,
            'message' => 'Cash received', 'transaction_date' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.get_cash_collection'));

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Cash received']);
    }

    public function test_manage_transactions_page_renders_with_user_id(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();
        $customer = User::forceCreate([
            'username' => 'customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.customers.viewTransactions', ['user_id' => $customer->id]));

        $response->assertOk();
        $response->assertSee((string) $customer->id, false);
    }
}
