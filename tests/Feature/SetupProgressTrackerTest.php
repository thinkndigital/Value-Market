<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Models\Zone;
use App\Services\SetupProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/CHANGELOG_FEATURE_AUDIT.md (v1.0.9, "Setup Progress Tracker" / "Setup completion tracking in admin
 * dashboard"): confirmed genuinely missing - no controller, model, or view existed under this or an
 * equivalent name. Every step in SetupProgressService checks real, current configuration state (a row
 * exists, a required Setting key is actually filled in), never a stored/cached flag, per this feature's own
 * "do not use fake percentages" requirement - these tests prove the percentage actually moves as real state
 * changes, not that a hardcoded number is returned.
 */
class SetupProgressTrackerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_environment_reports_zero_percent_with_every_step_incomplete(): void
    {
        $progress = app(SetupProgressService::class)->getProgress();

        $this->assertSame(0, $progress['percentage']);
        $this->assertSame(0, $progress['completed_steps']);
        $this->assertGreaterThan(0, $progress['total_steps']);
        foreach ($progress['steps'] as $step) {
            $this->assertFalse($step['completed'], "Step '{$step['key']}' should not be complete in a fresh environment.");
        }
    }

    public function test_percentage_increases_as_real_configuration_is_added(): void
    {
        $before = app(SetupProgressService::class)->getProgress();

        Store::forceCreate([
            'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store-' . uniqid(),
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);
        Currency::forceCreate([
            'name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1,
        ]);
        Language::forceCreate(['language' => 'English', 'code' => 'en', 'is_rtl' => 0]);
        Zone::forceCreate(['name' => json_encode(['en' => 'Zone 1']), 'serviceable_city_ids' => '', 'serviceable_zipcode_ids' => '', 'status' => 1]);

        $after = app(SetupProgressService::class)->getProgress();

        $this->assertGreaterThan($before['percentage'], $after['percentage']);
        $this->assertGreaterThan($before['completed_steps'], $after['completed_steps']);

        $stepsByKey = collect($after['steps'])->keyBy('key');
        $this->assertTrue($stepsByKey['store']['completed']);
        $this->assertTrue($stepsByKey['currency']['completed']);
        $this->assertTrue($stepsByKey['language']['completed']);
        $this->assertTrue($stepsByKey['shipping']['completed']);
        $this->assertFalse($stepsByKey['products']['completed']);
    }

    public function test_bank_transfer_alone_counts_as_a_configured_payment_gateway(): void
    {
        $before = app(SetupProgressService::class)->getProgress();
        $stepsByKeyBefore = collect($before['steps'])->keyBy('key');
        $this->assertFalse($stepsByKeyBefore['payment_gateway']['completed']);

        Setting::forceCreate([
            'variable' => 'payment_method',
            'value' => json_encode(['direct_bank_transfer_method' => 1]),
        ]);

        $after = app(SetupProgressService::class)->getProgress();
        $stepsByKeyAfter = collect($after['steps'])->keyBy('key');
        $this->assertTrue($stepsByKeyAfter['payment_gateway']['completed']);
    }

    public function test_policy_content_step_reflects_real_saved_text(): void
    {
        $before = app(SetupProgressService::class)->getProgress();
        $stepsByKeyBefore = collect($before['steps'])->keyBy('key');
        $this->assertFalse($stepsByKeyBefore['pages']['completed']);

        Setting::forceCreate([
            'variable' => 'privacy_policy',
            'value' => json_encode(['privacy_policy' => '<p>Real policy content</p>']),
        ]);

        $after = app(SetupProgressService::class)->getProgress();
        $stepsByKeyAfter = collect($after['steps'])->keyBy('key');
        $this->assertTrue($stepsByKeyAfter['pages']['completed']);
    }

    public function test_reaching_full_configuration_reports_one_hundred_percent(): void
    {
        $store = Store::forceCreate([
            'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store-' . uniqid(),
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);
        Currency::forceCreate([
            'name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1,
        ]);
        Language::forceCreate(['language' => 'English', 'code' => 'en', 'is_rtl' => 0]);
        Zone::forceCreate(['name' => json_encode(['en' => 'Zone 1']), 'serviceable_city_ids' => '', 'serviceable_zipcode_ids' => '', 'status' => 1]);
        Setting::forceCreate(['variable' => 'payment_method', 'value' => json_encode(['direct_bank_transfer_method' => 1])]);
        Setting::forceCreate(['variable' => 'privacy_policy', 'value' => json_encode(['privacy_policy' => '<p>Policy</p>'])]);
        Setting::forceCreate(['variable' => 'terms_and_conditions', 'value' => json_encode(['terms_and_conditions' => '<p>Terms</p>'])]);
        $category = Category::forceCreate([
            'store_id' => $store->id, 'name' => json_encode(['en' => 'Category']), 'slug' => 'category-' . uniqid(),
            'image' => '', 'banner' => '', 'status' => 1, 'parent_id' => 0,
        ]);
        Brand::forceCreate([
            'store_id' => $store->id, 'name' => json_encode(['en' => 'Brand']), 'slug' => 'brand-' . uniqid(),
            'image' => '', 'status' => 1,
        ]);
        Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => 1, 'store_id' => $store->id,
            'name' => json_encode(['en' => 'Product']), 'slug' => 'product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'status' => 1,
        ]);

        $progress = app(SetupProgressService::class)->getProgress();

        $this->assertSame(100, $progress['percentage']);
        $this->assertSame($progress['total_steps'], $progress['completed_steps']);
    }

    public function test_admin_home_page_renders_the_widget_without_error(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market'])]);
        Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);

        // AppServiceProvider::boot() shares these to every view on a real HTTP request, but only when
        // !runningInConsole() - PHPUnit's own process is itself a console process, so this is the same
        // stand-in used elsewhere in this suite (tests/Feature/DashboardHeadStackingTest.php etc.).
        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'system_settings' => ['app_name' => 'Value Market', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);

        $admin = User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN, 'active' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.home'));

        $response->assertOk();
        $response->assertSee('Store Setup Progress');
    }
}
