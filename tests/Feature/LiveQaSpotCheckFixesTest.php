<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Regression coverage for real, server-side bugs found during a live, browser-driven QA pass requested by
 * the product owner ("check admin pages actually work, especially settings; check how a seller adds a
 * product; POS doesn't respond to clicking products; check every page for real, not just that it exists").
 * Reproduced and fixed live against a running dev server (php artisan serve + Playwright), not just via
 * static route hits - see docs/LIVE_QA_SPOT_CHECK_FIXES.md for the full list, including the client-side JS
 * fixes this file cannot cover (no PHPUnit harness for browser JS in this app).
 */
class LiveQaSpotCheckFixesTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        return User::forceCreate([
            'username' => 'qa_admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN, 'active' => 1,
        ]);
    }

    /**
     * admin/settings/system_settings crashed with "Undefined array key 'version_system_status'" (and the
     * same shape for ~13 other toggle fields on the same page) whenever the system_settings Setting row
     * didn't already have every one of those keys saved - a real, live 500 reproduced against a fresh dev
     * DB. Every other field on this page already used the isKeySetAndNotEmpty() guard; these were the only
     * ones reading $settings[...] directly. Fixed by applying the same established guard.
     */
    public function test_system_settings_page_renders_when_the_row_is_missing_toggle_keys(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store-' . uniqid(),
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1, 'is_default_store' => 1,
        ]);
        // Deliberately sparse - only app_name is set, matching a real fresh-install/incomplete Setting row.
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'QA Store'])]);
        Setting::forceCreate(['variable' => 'payment_method', 'value' => json_encode([])]);
        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'system_settings' => ['app_name' => 'QA Store', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);

        $response = $this->actingAs($this->makeSuperAdmin())->get('/admin/settings/system_settings');

        $response->assertOk();
        $response->assertSee('QA Store');
    }

    /**
     * demo:create-seller / demo:seed-all seeded each demo product's stock onto product_variants.stock, but
     * every stock_type=0 product (what this command creates) actually reads/writes its real stock on
     * products.stock instead (see ProductService::getStock()/updateStock() - both keyed off product_id for
     * stock_type 0, variant_id only for stock_type 1/2). products.stock was left NULL, so every demo
     * product showed as out-of-stock/disabled everywhere availability is checked (reproduced live in POS -
     * the "Add" button was disabled for every demo product).
     */
    public function test_demo_seller_command_seeds_stock_on_the_product_row_for_stock_type_zero(): void
    {
        Artisan::call('demo:create-seller', ['--mobile' => '9995550001', '--password' => 'Test@12345']);

        $products = Product::where('stock_type', '0')->get();

        $this->assertNotEmpty($products);
        foreach ($products as $product) {
            $this->assertNotNull($product->stock, "Product {$product->id} has NULL stock - would show as unavailable everywhere.");
            $this->assertGreaterThan(0, $product->stock);
            $this->assertEquals(1, $product->availability);
        }
    }
}
