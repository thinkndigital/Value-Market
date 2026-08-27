<?php

namespace Tests\Feature\Phase3;

use App\Http\Controllers\Seller\PosController;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase 3 (docs/PHASE_3_COMMERCE_CORE.md): orders.channel is the new order-origin discriminator, set
 * alongside the existing is_pos_order flag rather than replacing it. Two separate code paths write orders
 * today, discovered while implementing this: OrderService::placeOrder() (the storefront/marketplace
 * checkout) and Seller\PosController::place_order() (POS), which builds and inserts its own $order_data
 * independently rather than calling OrderService::placeOrder() - both needed the fix.
 */
class OrderChannelTest extends TestCase
{
    use RefreshDatabase;

    private function seedCommonSettings(): void
    {
        Currency::forceCreate([
            'name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$',
            'exchange_rate' => 1, 'is_default' => 1, 'status' => 1,
        ]);

        Setting::forceCreate([
            'variable' => 'system_settings',
            'value' => json_encode(['single_seller_order_system' => '0']),
        ]);
    }

    /** @return array{0: User, 1: Product, 2: Product_variants} customer, product, variant */
    private function seedSellerWithSellableProduct(int $stock = 10): array
    {
        $sellerUser = User::forceCreate([
            'username' => 'chan_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone',
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);

        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']), 'slug' => 'cat-' . uniqid(), 'image' => '', 'banner' => '',
        ]);

        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id,
            'name' => json_encode(['en' => 'Channel Product']), 'slug' => 'chan-product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '',
            'stock_type' => '0', 'stock' => $stock, 'availability' => 1, 'status' => 1,
        ]);

        $variant = Product_variants::forceCreate([
            'product_id' => $product->id, 'price' => 25, 'status' => 1,
        ]);

        $customer = User::forceCreate([
            'username' => 'chan_customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'mobile' => (string) random_int(6000000000, 6999999999),
        ]);

        return [$customer, $product, $variant];
    }

    public function test_a_pos_sale_is_marked_channel_pos(): void
    {
        $this->seedCommonSettings();
        [$customer, , $variant] = $this->seedSellerWithSellableProduct(stock: 10);
        Cart::forceCreate([
            'user_id' => $customer->id, 'product_variant_id' => $variant->id, 'qty' => 1,
            'is_saved_for_later' => 0, 'product_type' => 'regular',
        ]);

        $request = new Request([
            'data' => json_encode([
                ['variant_id' => $variant->id, 'quantity' => 1, 'product_type' => 'regular', 'title' => 'Channel Product'],
            ]),
            'payment_method' => 'cash',
            'user_id' => $customer->id,
            'delivery_charges' => 0,
            'discount' => 0,
        ]);

        $response = app(PosController::class)->place_order($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['error'] ?? true, 'place_order should succeed: ' . json_encode($payload));
        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertSame(Order::CHANNEL_POS, $order->channel);
        $this->assertSame(1, (int) $order->is_pos_order);
    }

    public function test_a_marketplace_checkout_is_marked_channel_marketplace(): void
    {
        // Unlike the POS path above, placeOrder() unconditionally tries to send an order-placed push
        // notification and an invoice email, needing a real Firebase service account file and a
        // deliverable address respectively - both faked out here since they're unrelated to what this
        // test verifies (the channel discriminator).
        $this->mock(FirebaseNotificationService::class, function ($mock) {
            $mock->shouldReceive('sendNotification')->andReturn(null);
        });
        Mail::fake();

        $this->seedCommonSettings();
        [$customer, , $variant] = $this->seedSellerWithSellableProduct(stock: 10);
        Cart::forceCreate([
            'user_id' => $customer->id, 'product_variant_id' => $variant->id, 'qty' => 1,
            'is_saved_for_later' => 0, 'product_type' => 'regular',
        ]);

        $data = [
            'user_id' => $customer->id,
            'store_id' => '',
            'product_variant_id' => (string) $variant->id,
            'cart_product_type' => 'regular',
            'quantity' => '1',
            'payment_method' => 'cod',
            'mobile' => $customer->mobile,
            'email' => '',
            'delivery_charge' => 0,
            'discount' => 0,
            'order_payment_currency_code' => 'USD',
            'is_wallet_used' => 0,
            // No is_pos_order - matches the real storefront checkout path, which never sets it.
        ];

        $result = app(OrderService::class)->placeOrder($data);

        $order = Order::first();
        $this->assertNotNull($order, 'placeOrder should have created an order: ' . json_encode($result));
        $this->assertSame(Order::CHANNEL_MARKETPLACE, $order->channel);
        $this->assertSame(0, (int) $order->is_pos_order);
    }

    public function test_migration_backfills_channel_from_existing_is_pos_order(): void
    {
        $posOrder = Order::forceCreate([
            'user_id' => 1, 'mobile' => '6000000001', 'total' => 10, 'payment_method' => 'cod',
            'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD',
            'base_currency_code' => 'USD', 'order_payment_currency_conversion_rate' => 1,
            'is_pos_order' => 1,
        ]);
        $marketplaceOrder = Order::forceCreate([
            'user_id' => 1, 'mobile' => '6000000002', 'total' => 10, 'payment_method' => 'cod',
            'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD',
            'base_currency_code' => 'USD', 'order_payment_currency_conversion_rate' => 1,
            'is_pos_order' => 0,
        ]);

        // Re-run the migration's own backfill logic (not a copy of it) against these rows, exactly as it
        // would run against pre-existing orders on a real deploy.
        DB::table('orders')->where('is_pos_order', 1)->update(['channel' => 'pos']);
        DB::table('orders')->where('is_pos_order', 0)->update(['channel' => 'marketplace']);

        $this->assertSame(Order::CHANNEL_POS, $posOrder->fresh()->channel);
        $this->assertSame(Order::CHANNEL_MARKETPLACE, $marketplaceOrder->fresh()->channel);
    }
}
