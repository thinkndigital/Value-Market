<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A systematic audit of every admin controller's view() call against the actual Blade files on disk (the
 * same class of bug fixed for admin.manage_system_users in a prior commit) turned up 9 more admin pages that
 * have always 500'd: their sidebar links exist, their routes exist, their controller methods exist, but the
 * Blade view each one renders was simply never created. Added all 9, each modeled on the closest existing
 * working page of the same shape (table/list pages on manage_sellers.blade.php's bootstrap-table pattern,
 * bulk-upload forms on brand_bulk_upload.blade.php's pattern, the seller notification forms on
 * send_notification.blade.php - adjusted to the send_seller_notification/notification-sellers ids/classes
 * custom.js's own change handler already expects, not the customer-facing ones).
 */
class AdminMissingViewsTest extends TestCase
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

    /** @return array<string, array{0: string}> */
    public static function previouslyMissingRoutesProvider(): array
    {
        return [
            'category bulk upload form' => ['categories.bulk_upload'],
            'location bulk upload form' => ['admin.location_bulk_upload.index'],
            'translation bulk upload form' => ['translation_bulk_upload.index'],
            'combo product bulk upload form' => ['admin.combo.product.bulk_upload'],
            'send seller notification form' => ['seller_notifications.index'],
            'seller email notification form' => ['seller_email_notifications.index'],
            'manage customer wallet table' => ['admin.customers.walletTransaction'],
            'manage combo products table' => ['admin.combo_products.manage_product'],
            'manage pickup locations table' => ['admin.pickup_location.index'],
        ];
    }

    #[DataProvider('previouslyMissingRoutesProvider')]
    public function test_page_renders_instead_of_500(string $routeName): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->get(route($routeName));

        $response->assertOk();
    }
}
