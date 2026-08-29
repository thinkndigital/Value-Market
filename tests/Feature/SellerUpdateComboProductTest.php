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
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * seller.pages.forms.update_combo_product was missing - Seller\ComboProductController::edit() (reached from
 * the seller's "Manage Combo Products" list's row-action "Edit" link, $edit_url built in
 * ComboProductController::list(), a real live-linked route: seller.combo_products.edit) rendered this view
 * name but the file never existed on disk, so every "Edit Combo Product" click 500'd. Modeled on
 * admin.pages.forms.update_combo_product (the admin panel's just-shipped edit-form counterpart of this same
 * resource, commit de92f4d) transposed onto seller.pages.forms.combo_products (the seller's own "add combo
 * product" form): no seller-selection field since the seller is implicit, x-seller.breadcrumb, and the
 * seller's own search_seller_combo_product widget class for the "similar products" select.
 */
class SellerUpdateComboProductTest extends TestCase
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

    private function makeSeller(string $storeName): array
    {
        $sellerUser = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'active' => 1,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 1, 'status' => 1,
            'slug' => 'store-' . uniqid(), 'store_name' => $storeName, 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);

        return [$sellerUser, $seller];
    }

    private function makeComboProduct(Seller $seller, string $productName, string $comboTitle): ComboProduct
    {
        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']), 'slug' => 'cat-' . uniqid(),
            'image' => '', 'banner' => '', 'status' => 1,
        ]);
        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id, 'store_id' => 1, 'type' => 'simple_product',
            'name' => json_encode(['en' => $productName]), 'slug' => 'product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'status' => 1,
        ]);
        $variant = Product_variants::forceCreate([
            'product_id' => $product->id, 'price' => 50, 'special_price' => 45,
            'weight' => 1, 'height' => 1, 'breadth' => 1, 'length' => 1,
            'sku' => 'WIDGET-' . uniqid(), 'stock' => 10, 'availability' => 1, 'status' => 1,
        ]);

        return ComboProduct::forceCreate([
            'store_id' => 1,
            'title' => json_encode(['en' => $comboTitle]),
            'slug' => 'combo-' . uniqid(),
            'short_description' => json_encode(['en' => 'A bundle of great products']),
            'description' => 'Full combo description',
            'image' => '', 'seller_id' => $seller->id, 'product_type' => 'physical_product',
            'product_ids' => (string) $product->id, 'product_variant_ids' => (string) $variant->id,
            'price' => 90, 'special_price' => 80, 'tax' => '', 'tags' => 'bundle,deal',
            'deliverable_type' => 0, 'deliverable_cities' => '', 'other_images' => '[]',
            'selected_products' => 1, 'status' => 1,
        ]);
    }

    public function test_seller_edit_combo_product_page_renders_and_prefills_saved_data(): void
    {
        $this->shareBaseViewData();

        [$sellerUser, $seller] = $this->makeSeller('Combo Test Store');
        $comboProduct = $this->makeComboProduct($seller, 'Bundled Widget', 'Combo Deal Bundle');

        $response = $this->actingAs($sellerUser)->get(route('seller.combo_products.edit', $comboProduct->id));

        $response->assertOk();
        $response->assertSee('Combo Deal Bundle', false);
        $response->assertSee('Bundled Widget', false);
        $response->assertSee(route('seller.combo_products.update', $comboProduct->id), false);
    }

    public function test_seller_cannot_load_another_sellers_combo_product_into_the_edit_form(): void
    {
        $this->shareBaseViewData();

        [$ownerUser, $owner] = $this->makeSeller('Owner Store');
        [$attackerUser] = $this->makeSeller('Attacker Store');
        $comboProduct = $this->makeComboProduct($owner, 'Owner Widget', 'Owner Combo Bundle');

        // Seller\ComboProductController::edit()'s ownership-rejection path falls back to
        // admin.pages.views.no_data_found (see the note on the test above) rather than a seller-panel "not
        // found" view. That view extends admin/layout, whose sidebar calls hasPermissionTo(...) for every
        // menu item once the user isn't 'super_admin' - spatie/laravel-permission throws PermissionDoesNotExist
        // when the permission row doesn't exist at all (regardless of whether this seller actually holds it),
        // so every permission the sidebar checks has to exist for a non-admin user to render this fallback
        // view without a 500 - pre-existing behavior of admin.pages.views.no_data_found, not something this
        // view/test introduces.
        foreach ([
            'view address', 'view category_order', 'view combo_product', 'view combo_stock',
            'view customer_transaction', 'view customer_wallet_transaction', 'view customers',
            'view delivery_boy_cash_collection', 'view fund_transfer', 'view orders', 'view payment_request',
            'view product', 'view return_request', 'view seller', 'view seller_wallet_transaction',
            'view stock', 'view store', 'view system_user', 'view tax', 'view tickets',
        ] as $permissionName) {
            Permission::create(['name' => $permissionName, 'guard_name' => 'web']);
        }

        $response = $this->actingAs($attackerUser)->get(route('seller.combo_products.edit', $comboProduct->id));

        $response->assertOk();
        $response->assertDontSee('Owner Combo Bundle', false);
        $response->assertDontSee('Owner Widget', false);
        // The controller's ownership check falls back to admin.pages.views.no_data_found rather than a
        // seller-panel view - assert the real edit form (identified by its submit action) did not render.
        $response->assertDontSee(route('seller.combo_products.update', $comboProduct->id), false);
    }
}
