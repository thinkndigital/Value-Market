<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ComboProduct;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Role;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * feature_section.edit (the "Edit" row action on Manage Section, under Featured Section)
 * -> FeaturedSectionsController::edit() -> admin.pages.forms.update_featured_section, which did not exist.
 * Another instance of the view('name', [...]) missing-view audit gap. Covers all three product_type
 * variants (category-based, custom_products, custom_combo_products) since each renders a different
 * conditional block server-side (matching custom.js's own .product_type change-handler class toggles, so
 * the initial page load shows the right section without needing a page interaction first).
 */
class AdminUpdateFeaturedSectionTest extends TestCase
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

    public function test_update_featured_section_page_renders_for_category_based_type(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();
        $category = Category::forceCreate([
            'store_id' => 1, 'name' => json_encode(['en' => 'Electronics']), 'slug' => 'electronics-' . uniqid(),
            'image' => '', 'banner' => '', 'status' => 1,
        ]);
        $section = Section::forceCreate([
            'store_id' => 1, 'title' => json_encode(['en' => 'Best Deals']),
            'short_description' => json_encode(['en' => 'Top picks']), 'product_type' => 'new_added_products',
            'categories' => (string) $category->id, 'product_ids' => null, 'style' => 'style_1',
            'header_style' => 'header_style_1', 'banner_image' => '', 'background_color' => '#e0ffee',
        ]);

        $response = $this->actingAs($admin)->get(route('feature_section.edit', $section->id));

        $response->assertOk();
        $response->assertSee('Best Deals');
        $response->assertSee('Electronics');
    }

    public function test_update_featured_section_page_renders_for_custom_products_type(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();
        $category = Category::forceCreate([
            'store_id' => 1, 'name' => json_encode(['en' => 'Category']), 'slug' => 'cat-' . uniqid(),
            'image' => '', 'banner' => '',
        ]);
        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => 1, 'store_id' => 1,
            'name' => json_encode(['en' => 'Wireless Mouse']), 'slug' => 'wireless-mouse-' . uniqid(),
            'image' => '', 'deliverable_cities' => '',
        ]);
        $section = Section::forceCreate([
            'store_id' => 1, 'title' => json_encode(['en' => 'Custom Picks']),
            'short_description' => json_encode(['en' => 'Hand picked']), 'product_type' => 'custom_products',
            'categories' => null, 'product_ids' => (string) $product->id, 'style' => 'style_1',
            'header_style' => 'header_style_1', 'banner_image' => '', 'background_color' => '#e0ffee',
        ]);

        $response = $this->actingAs($admin)->get(route('feature_section.edit', $section->id));

        $response->assertOk();
        $response->assertSee('Wireless Mouse');
    }

    public function test_update_featured_section_page_renders_for_custom_combo_products_type(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();
        $comboProduct = ComboProduct::forceCreate([
            'store_id' => 1, 'title' => json_encode(['en' => 'Snack Combo']), 'slug' => 'snack-combo-' . uniqid(),
            'seller_id' => 1, 'image' => '', 'status' => 1,
        ]);
        $section = Section::forceCreate([
            'store_id' => 1, 'title' => json_encode(['en' => 'Combo Picks']),
            'short_description' => json_encode(['en' => 'Combos']), 'product_type' => 'custom_combo_products',
            'categories' => null, 'product_ids' => (string) $comboProduct->id, 'style' => 'style_1',
            'header_style' => 'header_style_1', 'banner_image' => '', 'background_color' => '#e0ffee',
        ]);

        $response = $this->actingAs($admin)->get(route('feature_section.edit', $section->id));

        $response->assertOk();
        $response->assertSee('Snack Combo');
    }
}
