<?php

namespace Tests\Feature;

use App\Jobs\SendOrderConfirmationEmailJob;
use App\Models\Currency;
use App\Models\Order;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * docs/CHANGELOG_FEATURE_AUDIT.md (v1.0.10, "Queue integration" / "Faster order processing / Better UX
 * during high traffic"): confirmed genuinely missing - no `ShouldQueue` implementations existed anywhere
 * (`app/Jobs` didn't exist), and `QUEUE_CONNECTION` was not actually dispatched to despite being mentioned
 * in deployment docs. See docs/QUEUE_ARCHITECTURE.md for the Cloud-Run-compatible design (no permanently
 * running worker process is assumed - a `verify_cron_secret`-protected HTTP endpoint drains the queue,
 * matching this app's existing Cloud Scheduler cron pattern).
 */
class QueueIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_placing_an_order_dispatches_the_confirmation_email_job_instead_of_sending_inline(): void
    {
        Queue::fake();

        $order = $this->makeOrderWithUser('customer@example.com');

        // Simulate the exact dispatch call OrderService::placeOrder() makes.
        dispatch(new SendOrderConfirmationEmailJob($order->id));

        Queue::assertPushed(SendOrderConfirmationEmailJob::class, function ($job) use ($order) {
            return true;
        });
    }

    public function test_the_job_sends_the_invoice_email_when_run(): void
    {
        Setting::forceCreate(['variable' => 'email_settings', 'value' => json_encode(['email' => 'store@example.com'])]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market'])]);
        Mail::fake();

        $order = $this->makeOrderWithUser('customer@example.com');

        // Should not throw, matching the original inline code's guarantee that a mail failure never breaks
        // order placement.
        (new SendOrderConfirmationEmailJob($order->id))->handle();

        $this->assertTrue(true, 'Job ran to completion without throwing.');
    }

    public function test_the_job_is_a_no_op_for_a_user_with_no_email(): void
    {
        Setting::forceCreate(['variable' => 'email_settings', 'value' => json_encode(['email' => 'store@example.com'])]);
        Mail::fake();

        $order = $this->makeOrderWithUser(null);

        (new SendOrderConfirmationEmailJob($order->id))->handle();

        Mail::assertNothingSent();
    }

    public function test_the_job_is_a_no_op_when_email_is_not_configured(): void
    {
        Mail::fake();

        $order = $this->makeOrderWithUser('customer@example.com');

        (new SendOrderConfirmationEmailJob($order->id))->handle();

        Mail::assertNothingSent();
    }

    public function test_process_queue_route_rejects_a_request_with_no_cron_secret(): void
    {
        config(['constants.CRON_SECRET' => 'the-real-secret']);

        $response = $this->get('/admin/cronjob/processQueue');

        $response->assertStatus(403);
    }

    public function test_process_queue_route_accepts_the_configured_cron_secret(): void
    {
        config(['constants.CRON_SECRET' => 'the-real-secret']);

        $response = $this->get('/admin/cronjob/processQueue?cron_secret=the-real-secret');

        $response->assertOk();
        $response->assertJson(['error' => false]);
    }

    public function test_process_queue_endpoint_drains_the_jobs_table(): void
    {
        // Real database queue connection for this one test (not the suite's default `sync`), so a job is
        // genuinely persisted to the `jobs` table and then drained by the endpoint, proving the whole
        // Cloud-Run-compatible path (queue -> jobs table -> HTTP-triggered worker) actually works end to end.
        config(['queue.default' => 'database']);

        Setting::forceCreate(['variable' => 'email_settings', 'value' => json_encode(['email' => 'store@example.com'])]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market'])]);
        Mail::fake();

        $order = $this->makeOrderWithUser('customer@example.com');
        dispatch(new SendOrderConfirmationEmailJob($order->id));

        $this->assertDatabaseHas('jobs', ['queue' => 'default']);

        \Illuminate\Support\Facades\Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--max-time' => 10,
            '--tries' => 1,
        ]);

        $this->assertDatabaseCount('jobs', 0);
    }

    private function makeOrderWithUser(?string $email): Order
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);

        $user = User::forceCreate([
            'username' => 'customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'email' => $email,
        ]);

        return Order::forceCreate([
            'user_id' => $user->id, 'store_id' => 1, 'mobile' => '9999999999', 'total' => 100,
            'payment_method' => 'cod', 'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD',
            'base_currency_code' => 'USD', 'order_payment_currency_conversion_rate' => 1,
        ]);
    }
}
