<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\Category;
use App\Models\CategorySliders;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * More instances of the view('name', [...]) missing-view audit gap. Both edit links are reachable from
 * their respective manage tables' row-action dropdowns:
 *  - blog_categories.edit -> BlogController::editCategory() -> update_blog_category (was missing)
 *  - admin.category_sliders.update -> CategoryController::category_slider_edit() -> update_category_slider
 *    (was missing)
 */
class AdminUpdateFormViewsTest extends TestCase
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
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN,
        ]);
    }

    public function test_update_blog_category_page_renders(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();
        $category = BlogCategory::forceCreate([
            'store_id' => 1, 'name' => json_encode(['en' => 'Tech News']), 'slug' => 'tech-news-' . uniqid(),
            'image' => '', 'banner' => '', 'status' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('blog_categories.edit', $category->id));

        $response->assertOk();
        $response->assertSee('Tech News');
    }

    public function test_update_category_slider_page_renders(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();
        $category = Category::forceCreate([
            'store_id' => 1, 'name' => json_encode(['en' => 'Electronics']), 'slug' => 'electronics-' . uniqid(),
            'image' => '', 'banner' => '', 'status' => 1,
        ]);
        $slider = CategorySliders::forceCreate([
            'store_id' => 1, 'title' => json_encode(['en' => 'Popular Categories']),
            'category_ids' => (string) $category->id, 'style' => 'style_1', 'status' => 1,
            'banner_image' => '', 'background_color' => '#e0ffee',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.category_sliders.update', $slider->id));

        $response->assertOk();
        $response->assertSee('Popular Categories');
        $response->assertSee('Electronics');
    }
}
