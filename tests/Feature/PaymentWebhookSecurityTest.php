<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * docs/CHANGELOG_FEATURE_AUDIT.md (P0, "payment gateway callback/webhook security"): all three actively-used
 * webhook handlers in Admin\Webhook trusted their POST body directly with no real signature verification:
 *
 *  - razorpay_webhook(): checked only that an X-Razorpay-Signature header was present (non-empty), never
 *    that its value was a valid HMAC-SHA256 signature of the body using the configured webhook secret.
 *  - stripe_webhook(): the real \Stripe\Webhook::constructEvent() verification call was entirely commented
 *    out; $event was built straight from json_decode() of the raw body.
 *  - paystack_webhook(): fetched the webhook secret into a variable and never referenced it again anywhere
 *    in the method.
 *
 * In every case, anyone who could reach the public webhook URL could forge a payment.captured/
 * charge.success/payment_intent.succeeded event and, via the wallet-refill-user-{id}-{time}-{rand} order_id
 * convention these handlers already support, credit an arbitrary amount to an arbitrary user's wallet with
 * no real payment ever happening - or mark a real order paid without payment. Fixed by actually computing
 * and comparing the HMAC signature (Stripe via the official SDK, Razorpay/Paystack via hash_hmac +
 * hash_equals) before trusting anything in the payload, in all three handlers.
 *
 * Also found and fixed while wiring this up:
 *  - Both razorpay_webhook() and paystack_webhook() read the signature header via $_SERVER['HTTP_...'],
 *    which Laravel's own test harness never populates (and which real request pipelines can't rely on
 *    either, once any earlier code has touched the header bag) - switched to $request->header().
 *  - Both read the raw body via file_get_contents('php://input'), which is similarly untestable/unreliable
 *    - switched to $request->getContent().
 *  - The razorpay/paystack/phonepe webhook routes were registered as GET in routes/web.php, but all three
 *    gateways deliver webhooks as POST - meaning a real webhook call would have hit Laravel routing as a
 *    405 before ever reaching the handler, regardless of any of the above. Fixed to POST.
 *  - stripe_webhook()'s wallet-duplicate-transaction check used `!empty($existing_transaction)` on a value
 *    that fetchDetails() always returns as an Eloquent Collection object - empty() on any object is always
 *    false in PHP, so this reported every first-time wallet refill as a duplicate and never credited it.
 *    Same bug, inverted, in its "order" branch's `empty($transaction)` check (always false, so a genuinely
 *    missing transaction fell through to a crash instead of an error response). Both switched to
 *    ->isEmpty(). razorpay_webhook()'s refund.processed handler had the identical dead `if (empty($transaction))
 *    {}` (empty body, always false) immediately followed by an unconditional $transaction[0] access that
 *    would throw on an unmatched refund; fixed the same way.
 *
 * Only one of these process-wide fixes is purely a test-authoring concern rather than a real bug: this
 * repo's SettingService::getSettings() memoizes each setting `variable` in a process-static cache with no
 * invalidation (documented and worked around the same way in PolicyPagesTest earlier this session) - so
 * every test method in one PHPUnit run that touches the 'payment_method' setting shares whatever value the
 * FIRST such test wrote, regardless of what any later test's Setting::forceCreate() call contains. All
 * three gateways' credentials are seeded together, once, in setUp() so every test in this class sees the
 * exact same 'payment_method' blob no matter the run order.
 *
 * These tests prove the negative (a forged/unsigned webhook is rejected AND does not touch the wallet) is
 * what matters most here - not just that a correctly-signed one works.
 */
class PaymentWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const RAZORPAY_SECRET = 'whsec_razorpay';
    private const PAYSTACK_SECRET = 'sk_paystack_secret';
    private const STRIPE_SECRET = 'whsec_stripe_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market'])]);
        Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);
        Setting::forceCreate(['variable' => 'payment_method', 'value' => json_encode([
            'razorpay_key_id' => 'rzp_test_key', 'razorpay_secret_key' => 'rzp_secret', 'razorpay_webhook_secret_key' => self::RAZORPAY_SECRET,
            'paystack_key_id' => 'pk_test', 'paystack_secret_key' => self::PAYSTACK_SECRET,
            'stripe_webhook_secret_key' => self::STRIPE_SECRET,
        ])]);
    }

    private function makeUser(float $balance = 0): User
    {
        return User::forceCreate([
            'username' => 'user_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'balance' => $balance,
        ]);
    }

    // ---------------------------------------------------------------- Razorpay

    public function test_razorpay_webhook_rejects_a_forged_signature_and_does_not_credit_wallet(): void
    {
        $victim = $this->makeUser(0);

        $orderId = 'wallet-refill-user-' . $victim->id . '-1700000000-123';
        $payload = json_encode([
            'event' => 'order.paid',
            'payload' => [
                'order' => ['entity' => ['receipt' => $orderId, 'notes' => ['order_id' => $orderId]]],
                'payment' => ['entity' => ['id' => 'pay_forged', 'amount' => 500000, 'currency' => 'INR', 'notes' => ['order_id' => $orderId]]],
            ],
        ]);

        $response = $this->call('POST', route('admin.razorpay_webhook'), [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => 'attacker-supplied-nonsense',
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(400);
        $this->assertSame(0.0, (float) $victim->fresh()->balance, 'A forged webhook must never move real money.');
    }

    public function test_razorpay_webhook_accepts_a_correctly_signed_wallet_refill(): void
    {
        $victim = $this->makeUser(0);

        $orderId = 'wallet-refill-user-' . $victim->id . '-1700000000-123';
        $payload = json_encode([
            'event' => 'order.paid',
            'payload' => [
                'order' => ['entity' => ['receipt' => $orderId, 'notes' => ['order_id' => $orderId]]],
                'payment' => ['entity' => ['id' => 'pay_real', 'amount' => 50000, 'currency' => 'INR', 'notes' => ['order_id' => $orderId]]],
            ],
        ]);
        $signature = hash_hmac('sha256', $payload, self::RAZORPAY_SECRET);

        $response = $this->call('POST', route('admin.razorpay_webhook'), [], [], [], [
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertOk();
        $this->assertSame(500.0, (float) $victim->fresh()->balance);
    }

    // ---------------------------------------------------------------- Paystack

    public function test_paystack_webhook_rejects_a_forged_signature_and_does_not_credit_wallet(): void
    {
        $victim = $this->makeUser(0);

        $orderId = 'wallet-refill-user-' . $victim->id . '-1700000000-123';
        $payload = json_encode([
            'event' => 'charge.success',
            'data' => ['metadata' => ['order_id' => $orderId], 'reference' => 'ref_forged', 'amount' => 500000, 'currency' => 'NGN'],
        ]);

        $response = $this->call('POST', route('admin.paystack_webhook'), [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => 'attacker-supplied-nonsense',
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(400);
        $this->assertSame(0.0, (float) $victim->fresh()->balance, 'A forged webhook must never move real money.');
    }

    public function test_paystack_webhook_accepts_a_correctly_signed_wallet_refill(): void
    {
        $victim = $this->makeUser(0);

        $orderId = 'wallet-refill-user-' . $victim->id . '-1700000000-123';
        $payload = json_encode([
            'event' => 'charge.success',
            'data' => ['metadata' => ['order_id' => $orderId], 'reference' => 'ref_real', 'amount' => 50000, 'currency' => 'NGN'],
        ]);
        $signature = hash_hmac('sha512', $payload, self::PAYSTACK_SECRET);

        $response = $this->call('POST', route('admin.paystack_webhook'), [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertOk();
        $this->assertSame(500.0, (float) $victim->fresh()->balance);
    }

    // ---------------------------------------------------------------- Stripe

    public function test_stripe_webhook_rejects_a_forged_signature_and_does_not_credit_wallet(): void
    {
        $victim = $this->makeUser(0);

        $payload = json_encode([
            'id' => 'evt_forged', 'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['metadata' => ['type' => 'wallet', 'user_id' => (string) $victim->id, 'amount' => 500], 'payment_intent' => 'pi_forged']],
        ]);

        $response = $this->call('POST', route('admin.stripe_webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't=1700000000,v1=attackersuppliednonsense',
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(400);
        $this->assertSame(0.0, (float) $victim->fresh()->balance, 'A forged webhook must never move real money.');
    }

    public function test_stripe_webhook_accepts_a_correctly_signed_wallet_refill(): void
    {
        $victim = $this->makeUser(0);

        $payload = json_encode([
            'id' => 'evt_real', 'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['metadata' => ['type' => 'wallet', 'user_id' => (string) $victim->id, 'amount' => 500], 'payment_intent' => 'pi_real']],
        ]);
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, self::STRIPE_SECRET);
        $header = "t={$timestamp},v1={$signature}";

        $response = $this->call('POST', route('admin.stripe_webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $header,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertOk();
        $this->assertSame(500.0, (float) $victim->fresh()->balance);
    }
}
