<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItems;
use App\Models\OrderTracking;
use App\Models\Parcel;
use App\Models\Parcelitem;
use App\Models\Role;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/CHANGELOG_FEATURE_AUDIT.md (P1, "Shiprocket depth audit"): Webhook::spr_webhook() existed only as a
 * completely empty method body - the route was registered (as GET; Shiprocket delivers webhooks as POST, so
 * a real call would have 405'd before ever reaching it), the admin Settings screen already required and
 * stored a `webhook_token` specifically for this (SettingController::storeShippingSettings()), and that
 * token was already being hidden from the mobile-app settings API response - but nothing ever verified an
 * incoming request against it, because there was no code in the handler to verify anything with. The same
 * "security control collected but never actually checked" pattern found and fixed in the Razorpay/Paystack/
 * Stripe webhooks this session (see PaymentWebhookSecurityTest), just with the check missing entirely rather
 * than merely bypassed.
 *
 * These tests prove the negative first - a request without the correct token must be rejected AND must not
 * touch any local tracking/parcel/order-item data - then prove a correctly-authenticated webhook actually
 * updates tracking state and cascades a cancellation to the parcel/order items, the same way
 * ShiprocketService::cancelShiprocketOrder() already does for the pull-based (manual "Update Status") path.
 */
class ShiprocketWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_TOKEN = 'sr_test_webhook_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::forceCreate(['variable' => 'shipping_method', 'value' => json_encode([
            'shiprocket_shipping_method' => 1,
            'email' => 'ops@example.test',
            'password' => 'not-a-real-password',
            'webhook_token' => self::WEBHOOK_TOKEN,
        ])]);
    }

    /**
     * @return array{0: Order, 1: OrderItems, 2: Parcel, 3: OrderTracking}
     */
    private function makeShippedOrderFixture(): array
    {
        $customer = User::forceCreate([
            'username' => 'customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
        $sellerUser = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public']);

        $order = Order::forceCreate([
            'user_id' => $customer->id, 'mobile' => '9999999999', 'total' => 100, 'payment_method' => 'cod',
            'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD', 'base_currency_code' => 'USD',
            'order_payment_currency_conversion_rate' => 1,
        ]);

        // OrderService::updateOrder()'s $isJson=true path (used for the 'status' column on both models)
        // requires 'status' to already be a JSON-encoded [[state, date], ...] history array, matching every
        // real write site in this codebase (e.g. ParcelService::viewAllParcels(), OrderService's own
        // placeOrder()) - a plain string here would make json_decode() return null and the priority-status
        // lookup that follows silently no-op.
        $shippedStatus = json_encode([['shipped', now()->format('d-m-Y h:i:sa')]]);

        $orderItem = OrderItems::forceCreate([
            'user_id' => $customer->id, 'order_id' => $order->id, 'seller_id' => $seller->id,
            'quantity' => 1, 'price' => 100, 'sub_total' => 100, 'status' => $shippedStatus, 'active_status' => 'shipped',
            'order_type' => 'regular_order',
        ]);

        $parcel = Parcel::forceCreate([
            'order_id' => $order->id, 'name' => 'Parcel 1', 'status' => $shippedStatus, 'active_status' => 'shipped', 'otp' => 1234,
        ]);

        Parcelitem::forceCreate([
            'parcel_id' => $parcel->id, 'order_item_id' => $orderItem->id, 'unit_price' => 100, 'quantity' => 1,
        ]);

        $tracking = OrderTracking::forceCreate([
            'order_id' => $order->id, 'order_item_id' => (string) $orderItem->id, 'parcel_id' => $parcel->id,
            'shiprocket_order_id' => 555111, 'shipment_id' => 999222, 'courier_company_id' => 10,
            'awb_code' => '', 'pickup_status' => 1, 'pickup_scheduled_date' => '', 'pickup_token_number' => '',
            'status' => 0, 'others' => '', 'pickup_generated_date' => '', 'data' => '', 'date' => '',
            'is_canceled' => 0, 'manifest_url' => '', 'label_url' => '', 'invoice_url' => '',
            'tracking_id' => 'CHANNEL-' . $order->id, 'url' => '',
        ]);

        return [$order, $orderItem, $parcel, $tracking];
    }

    public function test_webhook_rejects_a_request_with_no_token_and_makes_no_change(): void
    {
        [, , , $tracking] = $this->makeShippedOrderFixture();

        $payload = json_encode([
            'order_id' => $tracking->shiprocket_order_id,
            'awb' => 'AWB-FORGED',
            'current_status' => 'DELIVERED',
        ]);

        $response = $this->call('POST', route('admin.spr_webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(400);
        $this->assertSame('', $tracking->fresh()->awb_code, 'An unauthenticated webhook must never update tracking data.');
    }

    public function test_webhook_rejects_a_wrong_token_and_makes_no_change(): void
    {
        [, , , $tracking] = $this->makeShippedOrderFixture();

        $payload = json_encode([
            'order_id' => $tracking->shiprocket_order_id,
            'awb' => 'AWB-FORGED',
            'current_status' => 'DELIVERED',
        ]);

        $response = $this->call('POST', route('admin.spr_webhook'), [], [], [], [
            'HTTP_X_API_KEY' => 'attacker-guessed-value',
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(400);
        $this->assertSame('', $tracking->fresh()->awb_code, 'A forged webhook token must never update tracking data.');
    }

    public function test_webhook_get_is_not_routed(): void
    {
        // Shiprocket delivers webhooks as POST; the route used to be registered as GET, meaning a real
        // webhook call would have 405'd before ever reaching the handler.
        $response = $this->get('/admin/webhook/spr_webhook');

        $response->assertStatus(405);
    }

    public function test_webhook_with_correct_header_token_updates_tracking(): void
    {
        [, , , $tracking] = $this->makeShippedOrderFixture();

        $payload = json_encode([
            'order_id' => $tracking->shiprocket_order_id,
            'awb' => 'AWB123456789',
            'current_status' => 'Out for Delivery',
        ]);

        $response = $this->call('POST', route('admin.spr_webhook'), [], [], [], [
            'HTTP_X_API_KEY' => self::WEBHOOK_TOKEN,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertOk();
        $fresh = $tracking->fresh();
        $this->assertSame('AWB123456789', $fresh->awb_code);
        $this->assertSame('Out for Delivery', $fresh->others);
        $this->assertSame(0, (int) $fresh->is_canceled);
    }

    public function test_webhook_accepts_the_token_via_the_json_body_as_a_fallback(): void
    {
        [, , , $tracking] = $this->makeShippedOrderFixture();

        $payload = json_encode([
            'order_id' => $tracking->shiprocket_order_id,
            'awb' => 'AWB000111222',
            'current_status' => 'Shipped',
            'token' => self::WEBHOOK_TOKEN,
        ]);

        $response = $this->call('POST', route('admin.spr_webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertOk();
        $this->assertSame('AWB000111222', $tracking->fresh()->awb_code);
    }

    public function test_webhook_cancellation_cascades_to_parcel_and_order_item(): void
    {
        [, $orderItem, $parcel, $tracking] = $this->makeShippedOrderFixture();

        $payload = json_encode([
            'order_id' => $tracking->shiprocket_order_id,
            'current_status' => 'CANCELLED',
        ]);

        $response = $this->call('POST', route('admin.spr_webhook'), [], [], [], [
            'HTTP_X_API_KEY' => self::WEBHOOK_TOKEN,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertOk();
        $this->assertSame(1, (int) $tracking->fresh()->is_canceled);
        $this->assertSame('cancelled', $parcel->fresh()->active_status);
        $this->assertSame('cancelled', $orderItem->fresh()->active_status);
    }

    public function test_webhook_for_an_unknown_shiprocket_order_id_is_a_no_op_not_an_error(): void
    {
        $this->makeShippedOrderFixture();

        $payload = json_encode([
            'order_id' => 999999999,
            'current_status' => 'Delivered',
        ]);

        $response = $this->call('POST', route('admin.spr_webhook'), [], [], [], [
            'HTTP_X_API_KEY' => self::WEBHOOK_TOKEN,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        // A verified webhook for a shipment this app has no record of must not be treated as an attack or
        // surface a 4xx/5xx that would make Shiprocket keep retrying - just quietly ignored.
        $response->assertOk();
        $response->assertJsonFragment(['error' => false]);
    }
}
