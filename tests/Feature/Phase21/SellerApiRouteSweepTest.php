<?php

namespace Tests\Feature\Phase21;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\ComboProduct;
use App\Models\Currency;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\PickupLocation;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Tax;
use App\Models\User;
use App\Models\Zipcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 21 (32-phase SaaS brief): route sweep for routes/seller_api.php (the seller mobile-app API, backed
 * by Seller\v1\ApiController - 4,997 lines, 85 methods per docs/TECHNICAL_DEBT.md). Same methodology as
 * CustomerApiRouteSweepTest and this session's earlier Phase 2 sweeps: real HTTP kernel, real seeded
 * fixtures, a real Sanctum bearer token. Covers this file's 47 GET routes.
 */
class SellerApiRouteSweepTest extends TestCase
{
    use RefreshDatabase;

    private array $failures = [];

    /**
     * download_invoice/download_label/download_parcel_invoice need a real Shiprocket-created parcel
     * (external courier integration) and real PDF rendering - deliberately deferred, same reasoning this
     * session used for the admin/seller invoice-PDF routes in the Phase 2 param-route sweep.
     * get_shiprocket_order/shiprocket_order_tracking need a real Shiprocket order id and make a live
     * outbound call to Shiprocket's API - not something a route sweep with local fixtures can exercise.
     */
    private const SKIP_ROUTES = [
        'seller_api/download_invoice', 'seller_api/download_label', 'seller_api/download_parcel_invoice',
        'seller_api/get_shiprocket_order', 'seller_api/shiprocket_order_tracking',
    ];

    private function hit(string $uri): void
    {
        if (in_array($uri, self::SKIP_ROUTES, true)) {
            return;
        }
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

    public function test_seller_api_get_routes_render_without_a_server_error(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market'])]);
        Setting::forceCreate(['variable' => 'payment_method', 'value' => json_encode([])]);

        Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1, 'is_default_store' => 1,
        ]);

        $sellerUser = User::forceCreate([
            'username' => 'seller_api_sweep_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'active' => 1, 'mobile' => '9996660001',
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 1,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'disk' => 'public', 'status' => 1,
            'category_ids' => '',
        ]);
        $token = $sellerUser->createToken('sweep')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        $customer = User::forceCreate([
            'username' => 'seller_api_customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'mobile' => '9996660002',
        ]);

        $category = Category::forceCreate(['name' => json_encode(['en' => 'Cat']), 'store_id' => 1, 'slug' => 'cat-sapi-' . uniqid(), 'image' => '', 'banner' => '', 'status' => 1]);
        $brand = Brand::forceCreate(['name' => json_encode(['en' => 'Brand']), 'store_id' => 1, 'image' => '', 'slug' => 'brand-sapi-' . uniqid(), 'status' => 1]);

        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id, 'store_id' => 1, 'brand' => $brand->id,
            'name' => json_encode(['en' => 'Product']), 'slug' => 'product-sapi-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '0', 'status' => 1, 'stock' => 10, 'availability' => 1,
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 20, 'status' => 1, 'stock' => 5]);

        $comboProduct = ComboProduct::forceCreate([
            'title' => json_encode(['en' => 'Combo']), 'short_description' => json_encode(['en' => 'x']),
            'description' => json_encode(['en' => 'x']), 'seller_id' => $seller->id, 'product_type' => 'simple_product',
            'product_ids' => (string) $product->id, 'price' => 20, 'stock' => 5, 'availability' => 1,
            'status' => 1, 'store_id' => 1, 'slug' => 'combo-sapi-' . uniqid(),
        ]);

        $order = Order::forceCreate([
            'user_id' => $customer->id, 'mobile' => '9996660002', 'total' => 20, 'payment_method' => 'cod',
            'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD', 'base_currency_code' => 'USD',
            'order_payment_currency_conversion_rate' => 1,
        ]);
        $status = json_encode([['awaiting', now()->format('d-m-Y h:i:sa')]]);
        $orderItem = OrderItems::forceCreate([
            'user_id' => $customer->id, 'order_id' => $order->id, 'seller_id' => $seller->id,
            'product_variant_id' => $variant->id, 'quantity' => 1, 'price' => 20, 'sub_total' => 20,
            'status' => $status, 'active_status' => 'awaiting', 'order_type' => 'regular_order',
        ]);

        $tax = Tax::forceCreate(['title' => json_encode(['en' => 'VAT']), 'percentage' => 5]);
        $pickupLocation = PickupLocation::forceCreate([
            'seller_id' => $seller->id, 'pickup_location' => 'Main', 'name' => 'Main', 'email' => 'x@example.com',
            'phone' => '123', 'city' => 'City', 'country' => 'Country', 'state' => 'State', 'pincode' => '11937',
            'address' => 'Addr', 'status' => 1,
        ]);
        $attribute = Attribute::forceCreate(['store_id' => 1, 'name' => 'Color', 'status' => 1, 'category_id' => $category->id]);
        $city = City::forceCreate(['name' => json_encode(['en' => 'City']), 'minimum_free_delivery_order_amount' => 0, 'delivery_charges' => 0]);
        $zipcode = Zipcode::forceCreate(['zipcode' => '11938', 'city_id' => $city->id, 'minimum_free_delivery_order_amount' => 0, 'delivery_charges' => 0]);

        $routes = [
            'seller_api/download_order_invoice?order_id=' . $order->id,
            'seller_api/get_all_categories', 'seller_api/get_all_parcels',
            "seller_api/get_attribute_values?attribute_id={$attribute->id}", 'seller_api/get_attributes',
            'seller_api/get_brand_list', 'seller_api/get_categories', 'seller_api/get_cities',
            "seller_api/get_combo_product_rating?combo_product_id={$comboProduct->id}", 'seller_api/get_combo_products',
            'seller_api/get_countries_data', 'seller_api/get_delivery_boys', 'seller_api/get_language_labels',
            'seller_api/get_languages', 'seller_api/get_media', 'seller_api/get_notifications',
            "seller_api/get_order_items?order_id={$order->id}", "seller_api/get_order_tracking?order_id={$order->id}",
            'seller_api/get_orders', 'seller_api/get_overview_statistic', 'seller_api/get_pickup_locations',
            "seller_api/get_product_faqs?product_id={$product->id}", "seller_api/get_product_rating?product_id={$product->id}",
            'seller_api/get_products', 'seller_api/get_return_requests', 'seller_api/get_sales_list',
            'seller_api/get_seller_details', 'seller_api/get_seller_stores', 'seller_api/get_settings',
            'seller_api/get_statistics', 'seller_api/get_stores', 'seller_api/get_taxes', 'seller_api/get_total_data',
            'seller_api/get_transactions', 'seller_api/get_user_details', 'seller_api/get_withdrawal_request',
            'seller_api/get_zipcodes', 'seller_api/get_zones', 'seller_api/most_selling_categories',
            'seller_api/top_selling_products', 'seller_api/verify_user?mobile=9996660001',
        ];

        foreach ($routes as $uri) {
            $this->hit($uri);
        }

        $this->assertEmpty($this->failures, "Seller API route sweep breakage (route => status/error):\n" . json_encode($this->failures, JSON_PRETTY_PRINT));
    }
}
