<?php

namespace Tests\Feature\Seller;

use App\Models\Category;
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
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Live QA finding: seller/manage_stock/get_stock_list (the AJAX call the seller dashboard's own stock
 * widget makes on every page load) crashed with "Attempt to read property category_id on array" the moment
 * a seller had any real product - $product from ProductService::fetchProduct()'s 'product' key is an array
 * (confirmed by createRow(), which reads it with array syntax throughout, and by the identical
 * Admin\ManageStockController::get_stock_List() call site, which already used array access correctly),
 * but Seller\StockController::get_stock_List() read it with object syntax ($product->category_id,
 * $product->stock_type, $product->id). RouteSweepTest.php already knew this route 500'd (Category 5,
 * "needs deeper individual investigation") but its no-product fixture can't reach this exact crash path -
 * this test supplies a real product so the regression is actually exercised.
 */
class StockListDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_list_does_not_crash_when_the_seller_has_a_real_product(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market'])]);

        $store = Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1, 'is_default_store' => 1,
        ]);

        $sellerUser = User::forceCreate([
            'username' => 'stock_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'active' => 1,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => $store->id,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'disk' => 'public', 'status' => 1,
            'category_ids' => '',
        ]);

        $category = Category::forceCreate(['name' => json_encode(['en' => 'Cat']), 'store_id' => $store->id, 'slug' => 'cat-stock-' . uniqid(), 'image' => '', 'banner' => '', 'status' => 1]);
        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id, 'store_id' => $store->id,
            'name' => json_encode(['en' => 'Stock Product']), 'slug' => 'stock-product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '0', 'status' => 1, 'stock' => 10, 'availability' => 1,
        ]);
        Product_variants::forceCreate(['product_id' => $product->id, 'price' => 20, 'status' => 1, 'stock' => 5]);

        Auth::login($sellerUser);
        session(['store_id' => $store->id]);

        $response = $this->get('seller/manage_stock/get_stock_list');

        $response->assertOk();
        $response->assertJsonStructure(['rows', 'total']);
    }
}
