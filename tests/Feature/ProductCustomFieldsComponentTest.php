<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CustomField;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductCustomFieldValue;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * components.product.custom_fields and components.product.update_custom_fields (rendering each store's
 * configured "Custom Fields" - Task: an eShop Plus feature for store-defined extra product attributes) were
 * @include'd, unconditionally, by all four "add/edit product" forms in both the admin and seller panels
 * (products.blade.php, update_product.blade.php, combo_products.blade.php - regular and combo, both panels)
 * but the component files themselves never existed. Every single one of those pages - the core "add a
 * product" workflow of the whole platform - 500'd on load. Not a secondary admin page like the other missing
 * views fixed elsewhere: this is confirmed to be the highest-severity view-missing bug found in this app.
 */
class ProductCustomFieldsComponentTest extends TestCase
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

    private function makeSeller(): User
    {
        $sellerUser = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'active' => 1,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 1, 'status' => 1,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Test Store', 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);
        return $sellerUser;
    }

    /** Every field type this component must render, so the @switch is exercised end-to-end. */
    private function makeOneCustomFieldPerType(): void
    {
        foreach (['text', 'number', 'file', 'date', 'radio', 'dropdown', 'checkbox', 'color', 'textarea'] as $type) {
            CustomField::create([
                'store_id' => 1, 'name' => ucfirst($type) . ' Field', 'type' => $type,
                'field_length' => 50, 'min' => 0, 'max' => 100, 'required' => false, 'active' => true,
                'options' => in_array($type, ['radio', 'dropdown', 'checkbox']) ? ['Option A', 'Option B'] : null,
            ]);
        }
    }

    /**
     * Note: the sidebar's own "Add Product(s)" links use the .index route names below, not the .create
     * ones - Admin\ProductController's "build the add-product form" logic lives in index(), not create()
     * (create() doesn't exist at all - Route::resource()'s 'create' action is registered but dead/unlinked
     * from anywhere in the UI, a separate, harmless, pre-existing naming quirk, not exercised here).
     *
     * @return array<string, array{0: string}>
     */
    public static function adminCreateRoutesProvider(): array
    {
        return [
            'add product' => ['admin.products.index'],
            'add combo product' => ['admin.combo_products.index'],
        ];
    }

    #[DataProvider('adminCreateRoutesProvider')]
    public function test_admin_add_product_page_renders_with_every_custom_field_type(string $routeName): void
    {
        $this->shareBaseViewData();
        $this->makeOneCustomFieldPerType();
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->get(route($routeName));

        $response->assertOk();
        $response->assertSee('Custom Fields');
        $response->assertSee('name="custom_fields[', false);
    }

    /** @return array<string, array{0: string}> */
    public static function sellerCreateRoutesProvider(): array
    {
        return [
            'add product' => ['seller.products.index'],
            'add combo product' => ['seller.combo_products.index'],
        ];
    }

    #[DataProvider('sellerCreateRoutesProvider')]
    public function test_seller_add_product_page_renders_with_every_custom_field_type(string $routeName): void
    {
        $this->shareBaseViewData();
        $this->makeOneCustomFieldPerType();
        $seller = $this->makeSeller();

        $response = $this->actingAs($seller)->get(route($routeName));

        $response->assertOk();
        $response->assertSee('Custom Fields');
    }

    public function test_admin_edit_product_page_renders_and_prefills_a_saved_custom_field_value(): void
    {
        $this->shareBaseViewData();
        $this->makeOneCustomFieldPerType();
        $admin = $this->makeSuperAdmin();

        $sellerUser = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'active' => 1,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']), 'slug' => 'cat-' . uniqid(),
            'image' => '', 'banner' => '', 'status' => 1,
        ]);
        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id, 'store_id' => 1,
            'name' => json_encode(['en' => 'Product']), 'slug' => 'product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'status' => 1,
        ]);
        $textField = CustomField::where('type', 'text')->first();
        ProductCustomFieldValue::create([
            'product_id' => $product->id, 'custom_field_id' => $textField->id, 'value' => 'Saved Value',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.products.edit', $product->id));

        $response->assertOk();
        $response->assertSee('Saved Value', false);
    }
}
