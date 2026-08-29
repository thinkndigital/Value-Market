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
 * settings.blade.php's "Notification & Contact Settings" card links to
 * admin.pages.forms.notification_and_contact_settings, which did not exist - another instance of the
 * view('name', [...]) missing-view audit gap. Renders Firebase project ID + service account upload
 * (storeNotificationSettings) plus Contact Us / About Us text (both routed through the existing
 * storePoliciesAndContactSetting() helper, same [key => html] shape as the policy pages fixed earlier
 * this batch).
 */
class NotificationAndContactSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_and_contact_settings_page_renders_with_saved_values(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market', 'favicon' => ''])]);
        Setting::forceCreate(['variable' => 'firebase_project_id', 'value' => 'my-firebase-project']);
        Setting::forceCreate(['variable' => 'contact_us', 'value' => json_encode(['contact_us' => 'Reach us at support@example.com'])]);
        Setting::forceCreate(['variable' => 'about_us', 'value' => json_encode(['about_us' => 'We are Value Market'])]);
        Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);
        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'system_settings' => ['app_name' => 'Value Market', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);

        $admin = User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN,
        ]);

        $response = $this->actingAs($admin)->get(route('notification_and_contact_settings'));

        $response->assertOk();
        $response->assertSee('my-firebase-project');
        $response->assertSee('Reach us at support@example.com');
        $response->assertSee('We are Value Market');
    }
}
