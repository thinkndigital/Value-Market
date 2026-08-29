<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ComboProduct;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * admin.pages.forms.update_combo_product was missing - ComboProductController::edit() (reached from the
 * "Manage Combo Products" list's row-action "Edit" link, ComboProductController::list()'s $edit_url, a real
 * live-linked route: admin.combo_products.edit) rendered this view name but the file never existed on disk,
 * so every "Edit Combo Product" click 500'd. Modeled on admin.pages.forms.combo_products (the create form
 * for this same resource) with the same edit-form transformation update_product.blade.php already applies
 * over products.blade.php: value="" pre-fills, Select2 multi-selects pre-rendered as <option selected>,
 * components.product.update_custom_fields instead of the create form's custom_fields include, and the
 * edit_combo_product_id hidden input that drives custom.js's existing attribute-tab ajax fetch.
 */
class AdminUpdateComboProductTest extends TestCase
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

    public function test_admin_edit_combo_product_page_renders_and_prefills_saved_data(): void
    {
        $this->shareBaseViewData();

        $admin = User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN, 'active' => 1,
        ]);

        $sellerUser = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'active' => 1,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 1, 'status' => 1,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Combo Test Store', 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);

        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']), 'slug' => 'cat-' . uniqid(),
            'image' => '', 'banner' => '', 'status' => 1,
        ]);
        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id, 'store_id' => 1, 'type' => 'simple_product',
            'name' => json_encode(['en' => 'Bundled Widget']), 'slug' => 'bundled-widget-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'status' => 1,
        ]);
        $variant = Product_variants::forceCreate([
            'product_id' => $product->id, 'price' => 50, 'special_price' => 45,
            'weight' => 1, 'height' => 1, 'breadth' => 1, 'length' => 1,
            'sku' => 'WIDGET-1', 'stock' => 10, 'availability' => 1, 'status' => 1,
        ]);

        $comboProduct = ComboProduct::forceCreate([
            'store_id' => 1,
            'title' => json_encode(['en' => 'Combo Deal Bundle']),
            'slug' => 'combo-deal-bundle-' . uniqid(),
            'short_description' => json_encode(['en' => 'A bundle of great products']),
            'description' => 'Full combo description',
            'image' => '', 'seller_id' => $seller->id, 'product_type' => 'physical_product',
            'product_ids' => (string) $product->id, 'product_variant_ids' => (string) $variant->id,
            'price' => 90, 'special_price' => 80, 'tax' => '', 'tags' => 'bundle,deal',
            'deliverable_type' => 0, 'deliverable_cities' => '', 'other_images' => '[]',
            'selected_products' => 1, 'status' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.combo_products.edit', $comboProduct->id));

        $response->assertOk();
        $response->assertSee('Combo Deal Bundle', false);
        $response->assertSee('Bundled Widget', false);
        $response->assertSee('Combo Test Store', false);
        $response->assertSee(route('admin.combo_products.update', $comboProduct->id), false);
    }
}
