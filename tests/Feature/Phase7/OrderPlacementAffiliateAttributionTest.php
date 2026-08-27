<?php

namespace Tests\Feature\Phase7;

use App\Models\AffiliateLink;
use App\Models\Cart;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\ReferralConversion;
use App\Models\Role;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\FirebaseNotificationService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase 7 (docs/PHASE_7_AFFILIATE_ENGINE.md): a storefront checkout that carries an affiliate_code is
 * marked channel = CHANNEL_AFFILIATE (reserved but unused since Phase 3) and gets a pending conversion
 * recorded - end to end through the real OrderService::placeOrder(), not just AffiliateService in isolation.
 */
class OrderPlacementAffiliateAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_checkout_carrying_an_affiliate_code_is_attributed(): void
    {
        Currency::forceCreate([
            'name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$',
            'exchange_rate' => 1, 'is_default' => 1, 'status' => 1,
        ]);
        Setting::forceCreate([
            'variable' => 'system_settings',
            'value' => json_encode(['single_seller_order_system' => '0']),
        ]);

        $sellerUser = User::forceCreate([
            'username' => 'affiliate_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone',
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']), 'slug' => 'cat-' . uniqid(), 'image' => '', 'banner' => '',
        ]);
        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id,
            'name' => json_encode(['en' => 'Affiliate Product']), 'slug' => 'aff-product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '',
            'stock_type' => '0', 'stock' => 10, 'availability' => 1, 'status' => 1,
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 25, 'status' => 1]);

        $customer = User::forceCreate([
            'username' => 'affiliate_customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'mobile' => (string) random_int(6000000000, 6999999999),
        ]);
        Cart::forceCreate([
            'user_id' => $customer->id, 'product_variant_id' => $variant->id, 'qty' => 1,
            'is_saved_for_later' => 0, 'product_type' => 'regular',
        ]);

        $affiliate = User::forceCreate([
            'username' => 'affiliate_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'balance' => 0,
        ]);
        $link = app(AffiliateService::class)->createLink($affiliate->id, AffiliateLink::TARGET_PLATFORM);
        CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_PLATFORM, 'scope_id' => null, 'rate_type' => 'percentage', 'rate_value' => 5, 'status' => 1]);

        $this->mock(FirebaseNotificationService::class, function ($mock) {
            $mock->shouldReceive('sendNotification')->andReturn(null);
        });
        Mail::fake();

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
            'affiliate_code' => $link->code,
        ];

        $result = app(OrderService::class)->placeOrder($data);

        $order = Order::first();
        $this->assertNotNull($order, 'placeOrder should have created an order: ' . json_encode($result));
        $this->assertSame(Order::CHANNEL_AFFILIATE, $order->channel);

        $conversion = ReferralConversion::where('order_id', $order->id)->first();
        $this->assertNotNull($conversion);
        $this->assertSame(ReferralConversion::STATUS_PENDING, $conversion->status);
        $this->assertSame($link->id, $conversion->affiliate_link_id);
    }
}
