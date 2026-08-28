<?php

namespace Tests\Feature;

use App\Http\Controllers\App\v1\ApiController;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderCharges;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/SECURITY_AUDIT.md §6.5's own follow-up note flagged App\v1\ApiController::paypal_transaction_webview()
 * as an unauthenticated info-disclosure: it took $user_id straight from the request with zero check that
 * $order_id actually belonged to that user, and unconditionally embedded the target user's real email in the
 * rendered PayPal auto-submit form's hidden 'custom' field - letting an attacker learn any user's email by
 * enumerating user_id, given only some order_id (any numeric one that resolved, or none at all - the "no
 * matching order" branch disclosed the email just as readily as the "order found" branch).
 *
 * Fixed by requiring $order_id to actually belong to $user_id before proceeding - either a real order
 * (fetchOrders() scoped by both id and owner) or a wallet-refill synthetic id
 * ("wallet-refill-user-{user_id}-...", the same convention Admin\Webhook.php already parses) whose embedded
 * user id matches. These tests call the controller method directly (it echoes HTML rather than returning a
 * Response, since it's meant to be an auto-submitting webview page) and capture output via ob_start().
 */
class PaypalTransactionWebviewOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function seedPaypalSettings(): void
    {
        Setting::forceCreate([
            'variable' => 'payment_method',
            'value' => json_encode([
                'paypal_mode' => 'sandbox',
                'paypal_business_email' => 'business@example.com',
                'paypal_client_id' => 'client-id',
                'currency_code' => 'USD',
            ]),
        ]);
        // fetchOrders()'s order-detail hydration reads 'max_days_to_return_item' for the return-eligibility
        // window unconditionally, regardless of whether a delivery date is even set - needed for any test
        // that resolves a real order, not specific to the paypal fix itself. database/migrations/2025_01_01_
        // 000016_baseline_default_settings.php already seeds a 'system_settings' row (without this key) via
        // RefreshDatabase's migration run, so merge into that row rather than inserting a second one -
        // Setting::where('variable', ...)->value('value') has no ordering guarantee over which row wins if
        // two exist for the same variable.
        $existing = Setting::where('variable', 'system_settings')->first();
        $settings = $existing ? (json_decode($existing->value, true) ?? []) : [];
        $settings['max_days_to_return_item'] = 7;
        if ($existing) {
            $existing->update(['value' => json_encode($settings)]);
        } else {
            Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode($settings)]);
        }
    }

    private function makeUser(string $email): User
    {
        return User::forceCreate([
            'username' => 'user_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'email' => $email,
        ]);
    }

    private function makeOrderWithItem(User $owner): Order
    {
        $order = Order::forceCreate([
            'user_id' => $owner->id, 'mobile' => '9999999999', 'total' => 100,
            'payment_method' => 'paypal', 'order_payment_currency_id' => 1,
            'order_payment_currency_code' => 'USD', 'base_currency_code' => 'USD',
            'order_payment_currency_conversion_rate' => 1,
        ]);

        $sellerUser = $this->makeUser('seller_' . uniqid() . '@example.com');
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public']);
        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']), 'slug' => 'cat-' . uniqid(), 'image' => '', 'banner' => '',
        ]);
        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id,
            'name' => json_encode(['en' => 'Product']), 'slug' => 'product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '',
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 100]);
        $orderItem = OrderItems::forceCreate([
            'user_id' => $owner->id, 'order_id' => $order->id, 'seller_id' => $seller->id,
            'product_variant_id' => $variant->id, 'quantity' => 1, 'price' => 100, 'sub_total' => 100,
            'status' => 'placed', 'order_type' => 'regular_order',
        ]);
        OrderCharges::forceCreate([
            'seller_id' => $seller->id, 'product_variant_ids' => (string) $variant->id,
            'order_id' => $order->id, 'order_item_ids' => (string) $orderItem->id,
        ]);

        return $order;
    }

    private function callWebview($userId, $orderId, $amount = 100): string
    {
        ob_start();
        app(ApiController::class)->paypal_transaction_webview($userId, $orderId, $amount);
        return ob_get_clean();
    }

    public function test_a_numeric_order_id_belonging_to_a_different_user_does_not_leak_the_target_email(): void
    {
        $this->seedPaypalSettings();
        $victim = $this->makeUser('victim@example.com');
        $order = $this->makeOrderWithItem($victim);

        $attacker = $this->makeUser('attacker@example.com');
        $content = $this->callWebview($attacker->id, $order->id, 100);

        $this->assertStringNotContainsString('victim@example.com', $content);
    }

    public function test_a_numeric_order_id_belonging_to_the_requesting_user_renders_the_form_with_their_own_email(): void
    {
        $this->seedPaypalSettings();
        $owner = $this->makeUser('owner@example.com');
        $order = $this->makeOrderWithItem($owner);

        $content = $this->callWebview($owner->id, $order->id, 100);

        $this->assertStringContainsString('owner@example.com', $content);
    }

    public function test_a_wallet_refill_id_for_a_different_user_does_not_leak_the_target_email(): void
    {
        $this->seedPaypalSettings();
        $victim = $this->makeUser('victim2@example.com');
        $attacker = $this->makeUser('attacker2@example.com');

        $content = $this->callWebview($victim->id, 'wallet-refill-user-' . $attacker->id . '-' . time() . '-123', 50);

        $this->assertStringNotContainsString('victim2@example.com', $content);
    }

    public function test_a_wallet_refill_id_matching_the_requesting_user_renders_the_form(): void
    {
        $this->seedPaypalSettings();
        $user = $this->makeUser('refill@example.com');

        $content = $this->callWebview($user->id, 'wallet-refill-user-' . $user->id . '-' . time() . '-123', 50);

        $this->assertStringContainsString('refill@example.com', $content);
    }

    public function test_an_unrecognised_non_numeric_order_id_does_not_leak_the_email(): void
    {
        $this->seedPaypalSettings();
        $user = $this->makeUser('nobody@example.com');

        $content = $this->callWebview($user->id, 'not-a-real-order-id', 50);

        $this->assertStringNotContainsString('nobody@example.com', $content);
    }

    public function test_an_unknown_user_id_returns_a_clean_json_error(): void
    {
        $this->seedPaypalSettings();

        ob_start();
        $response = app(ApiController::class)->paypal_transaction_webview(999999, 1, 50);
        ob_get_clean();

        $this->assertNotNull($response);
        $payload = json_decode($response->getContent(), true);
        $this->assertTrue($payload['error']);
    }
}
