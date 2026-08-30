<?php

namespace Tests\Feature\Phase21;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Order;
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
 * Phase 21 (32-phase SaaS brief): route sweep for routes/delivery_boy_api.php (the delivery-boy mobile-app
 * API, backed by Delivery_boy\v1\ApiController - 1,458 lines per docs/TECHNICAL_DEBT.md). Same methodology
 * as CustomerApiRouteSweepTest/SellerApiRouteSweepTest. Covers this file's 15 GET routes.
 */
class DeliveryBoyApiRouteSweepTest extends TestCase
{
    use RefreshDatabase;

    private array $failures = [];

    private function hit(string $uri): void
    {
        try {
            $response = $this->get($uri, ['Accept' => 'application/json']);
            if ($response->getStatusCode() >= 500) {
                $body = json_decode($response->getContent(), true);
                $this->failures[$uri] = ($body['exception'] ?? 'Unknown') . ': ' . ($body['message'] ?? $response->getStatusCode())
                    . ' @ ' . ($body['file'] ?? '?') . ':' . ($body['line'] ?? '?');
            }
        } catch (\Throwable $e) {
            $this->failures[$uri] = get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
        }
    }

    public function test_delivery_boy_api_get_routes_render_without_a_server_error(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market'])]);
        Setting::forceCreate(['variable' => 'payment_method', 'value' => json_encode([])]);

        Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1, 'is_default_store' => 1,
        ]);

        $deliveryUser = User::forceCreate([
            'username' => 'db_api_sweep_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::DELIVERY_BOY, 'active' => 1, 'mobile' => '9995550001',
        ]);
        $token = $deliveryUser->createToken('sweep')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        $sellerUser = User::forceCreate([
            'username' => 'db_api_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'active' => 1,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 1,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'disk' => 'public', 'status' => 1,
            'category_ids' => '',
        ]);

        $customer = User::forceCreate([
            'username' => 'db_api_customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'mobile' => '9995550002',
        ]);

        $category = Category::forceCreate(['name' => json_encode(['en' => 'Cat']), 'store_id' => 1, 'slug' => 'cat-dapi-' . uniqid(), 'image' => '', 'banner' => '', 'status' => 1]);
        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id, 'store_id' => 1,
            'name' => json_encode(['en' => 'Product']), 'slug' => 'product-dapi-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '0', 'status' => 1, 'stock' => 10, 'availability' => 1,
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 20, 'status' => 1, 'stock' => 5]);

        $order = Order::forceCreate([
            'user_id' => $customer->id, 'mobile' => '9995550002', 'total' => 20, 'payment_method' => 'cod',
            'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD', 'base_currency_code' => 'USD',
            'order_payment_currency_conversion_rate' => 1,
        ]);
        $status = json_encode([['awaiting', now()->format('d-m-Y h:i:sa')]]);
        OrderItems::forceCreate([
            'user_id' => $customer->id, 'order_id' => $order->id, 'seller_id' => $seller->id,
            'product_variant_id' => $variant->id, 'quantity' => 1, 'price' => 20, 'sub_total' => 20,
            'status' => $status, 'active_status' => 'awaiting', 'order_type' => 'regular_order',
        ]);

        $routes = [
            'delivery_boy_api/get_cities', 'delivery_boy_api/get_delivery_boy_cash_collection',
            'delivery_boy_api/get_delivery_boy_details', 'delivery_boy_api/get_fund_transfers',
            'delivery_boy_api/get_language_labels', 'delivery_boy_api/get_languages',
            'delivery_boy_api/get_notifications', 'delivery_boy_api/get_orders',
            'delivery_boy_api/get_returned_order_items', 'delivery_boy_api/get_settings',
            'delivery_boy_api/get_wallet_transaction', 'delivery_boy_api/get_withdrawal_request',
            'delivery_boy_api/get_zipcodes', 'delivery_boy_api/get_zones',
            'delivery_boy_api/verify_user?mobile=9995550001',
        ];

        foreach ($routes as $uri) {
            $this->hit($uri);
        }

        $this->assertEmpty($this->failures, "Delivery boy API route sweep breakage (route => status/error):\n" . json_encode($this->failures, JSON_PRETTY_PRINT));
    }
}
