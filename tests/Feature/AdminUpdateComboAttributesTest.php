<?php

namespace Tests\Feature;

use App\Models\ComboProductAttribute;
use App\Models\ComboProductAttributeValue;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * admin.combo_product_attributes.update (the "Edit" row action on Manage Attributes, under Combo Products)
 * -> ComboProductAttributeController::edit() -> admin.pages.forms.update_combo_attributes, which did not
 * exist. Another instance of the view('name', [...]) missing-view audit gap.
 */
class AdminUpdateComboAttributesTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_combo_attribute_page_renders_with_existing_values(): void
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

        $attribute = ComboProductAttribute::forceCreate(['name' => 'Size', 'store_id' => 1, 'status' => 1]);
        ComboProductAttributeValue::forceCreate(['combo_product_attribute_id' => $attribute->id, 'value' => 'Small', 'store_id' => 1]);
        ComboProductAttributeValue::forceCreate(['combo_product_attribute_id' => $attribute->id, 'value' => 'Medium', 'store_id' => 1]);

        $response = $this->actingAs($admin)->get(route('admin.combo_product_attributes.update', $attribute->id));

        $response->assertOk();
        $response->assertSee('Size', false);
        $response->assertSee('Small,Medium', false);
    }
}
