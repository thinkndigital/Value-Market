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
 * routes/admin_routes.php's admin.manage_system_users route (the sidebar's "Manage System Users" link, the
 * only place in the whole admin panel to see the list of admin/editor/super_admin accounts and their roles)
 * pointed at view('admin.pages.tables.manage_system_users') - a Blade file that never existed. Every real
 * install hit a 500 on this exact page: only admin.pages.forms.system_users (the "add" form) and
 * admin.pages.forms.update_system_users (the "edit" form) were ever created. Added the missing list view,
 * following the same bootstrap-table pattern as manage_sellers.blade.php and wired to the already-working
 * UserPermissionController::systemUsersList() AJAX endpoint (id/username/email/mobile/role/operate columns).
 */
class ManageSystemUsersPageTest extends TestCase
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

    private function makeSuperAdmin(): User
    {
        return User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN, 'active' => 1,
        ]);
    }

    public function test_manage_system_users_page_renders_instead_of_500(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->get(route('admin.manage_system_users'));

        $response->assertOk();
        $response->assertSee(route('system_users.list'), false);
    }

    public function test_manage_system_users_list_endpoint_still_returns_expected_columns(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->getJson(route('system_users.list'));

        $response->assertOk();
        $response->assertJsonStructure(['rows', 'total']);
        $response->assertJsonFragment(['username' => $admin->username]);
    }
}
