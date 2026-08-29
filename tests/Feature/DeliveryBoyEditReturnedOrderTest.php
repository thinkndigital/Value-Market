<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Order;
use App\Models\OrderCharges;
use App\Models\OrderItems;
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
 * delivery_boy.pages.forms.edit_returned_orders (the "Edit" link on every row of the Returned Orders table
 * fixed earlier) was missing - same view('name', [...]) audit gap. Reachable the moment a delivery boy has
 * any order item in a return-in-progress status assigned to them.
 */
class DeliveryBoyEditReturnedOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_returned_order_page_renders(): void
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

        $sellerUser = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public']);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 1,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Test Store', 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);
        $deliveryBoy = User::forceCreate([
            'username' => 'delivery_boy_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::DELIVERY_BOY, 'active' => 1,
        ]);
        $customer = User::forceCreate([
            'username' => 'customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'address' => '123 Test St',
        ]);
        $address = Address::forceCreate([
            'user_id' => $customer->id, 'name' => 'Home', 'mobile' => '9999999999',
            'address' => '123 Test St', 'type' => 'home',
        ]);
        $order = Order::forceCreate([
            'user_id' => $customer->id, 'store_id' => 1, 'mobile' => '9999999999', 'total' => 100,
            'payment_method' => 'cod', 'address_id' => $address->id, 'order_payment_currency_id' => 1,
            'order_payment_currency_code' => 'USD', 'base_currency_code' => 'USD',
            'order_payment_currency_conversion_rate' => 1,
        ]);
        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']), 'slug' => 'cat-' . uniqid(), 'image' => '', 'banner' => '',
        ]);
        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id, 'store_id' => 1,
            'name' => json_encode(['en' => 'Product']), 'slug' => 'product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '',
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 100]);
        $orderItem = OrderItems::forceCreate([
            'user_id' => $customer->id, 'store_id' => 1, 'order_id' => $order->id, 'seller_id' => $seller->id,
            'product_variant_id' => $variant->id, 'quantity' => 2, 'price' => 50, 'discounted_price' => 0,
            'tax_percent' => 0, 'tax_amount' => 0, 'sub_total' => 100, 'status' => json_encode([['return_request_approved', now()->toDateTimeString()]]),
            'active_status' => 'return_request_approved', 'order_type' => 'regular_order',
            'delivery_boy_id' => $deliveryBoy->id,
        ]);
        OrderCharges::forceCreate([
            'seller_id' => $seller->id, 'product_variant_ids' => (string) $variant->id,
            'order_id' => $order->id, 'order_item_ids' => (string) $orderItem->id,
        ]);

        $response = $this->actingAs($deliveryBoy)->get(route('delivery_boy.returned_orders.edit', [
            'order_id' => $order->id, 'order_item_id' => $orderItem->id,
        ]));

        $response->assertOk();
    }
}
