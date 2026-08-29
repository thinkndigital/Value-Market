<?php

namespace Tests\Feature;

use App\Models\CustomMessage;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * custom_message.edit (the "Edit" row action on Manage Custom Message) -> CustomMessageController::edit()
 * -> admin.pages.forms.update_custom_message, which did not exist - another instance of the
 * view('name', [...]) missing-view audit gap. The page also ships a dead in-page #edit_modal (confirmed via
 * grep - custom.js never wires up a click handler that opens it or sets #edit_message_id) so the real edit
 * path is genuinely this separate GET page, not the modal.
 */
class AdminUpdateCustomMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_custom_message_page_renders_with_existing_values(): void
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

        $admin = User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN,
        ]);

        $message = CustomMessage::forceCreate([
            'type' => 'wallet_transaction', 'title' => 'Wallet Credited', 'message' => 'Your wallet has been credited',
        ]);

        $response = $this->actingAs($admin)->get(route('custom_message.edit', $message->id));

        $response->assertOk();
        $response->assertSee('Wallet Credited');
        $response->assertSee('Your wallet has been credited');
    }
}
