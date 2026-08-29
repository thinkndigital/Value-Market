<?php

namespace App\Jobs;

use App\Http\Controllers\Admin\OrderController;
use App\Models\Order;
use App\Models\User;
use App\Services\MailService;
use App\Services\SettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Changelog v1.0.10 ("Queue integration" / "Faster order processing / Better UX during high traffic"):
 * generating the invoice PDF and sending the confirmation email used to run inline inside
 * OrderService::placeOrder(), inside the same request that placed the order - the slowest part of an
 * already-heavy request. Moved into a real queueable job so it can be deferred (QUEUE_CONNECTION=database
 * in production) instead of blocking the customer's checkout response.
 *
 * With this app's default QUEUE_CONNECTION=sync (local/dev, and this test suite - phpunit.xml), dispatching
 * this job still runs it immediately and synchronously in the same request - identical behavior to before
 * this job existed, just refactored into a reusable, independently-testable unit. See
 * docs/QUEUE_ARCHITECTURE.md for how a real deferred queue is drained on Cloud Run, where no
 * permanently-running `queue:work` process is assumed.
 */
class SendOrderConfirmationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly int $orderId)
    {
    }

    public function handle(): void
    {
        $order = Order::find($this->orderId);
        if (!$order || empty($order->user_id)) {
            return;
        }

        $user = User::find($order->user_id);
        if (!$user || empty($user->email)) {
            return;
        }

        if (!app(MailService::class)->isEmailConfigured()) {
            return;
        }

        $settings = json_decode(app(SettingService::class)->getSettings('system_settings', true), true) ?? [];
        $appName = $settings['app_name'] ?? '';

        $subject = $appName . ": Invoice for Your Order #{$this->orderId} - Thank You for Shopping with Us!";
        $messageContent = "
            <p>Dear <strong>{$user->username}</strong>,</p>
            <p>Thank you for your order with us! We appreciate your trust in our service.</p>
            <p>Your order has been successfully placed. Your invoice is attached to this email.</p>
            <p><strong>Invoice Details:</strong></p>
            <ul>
                <li><strong>Order ID:</strong> #{$this->orderId}</li>
                <li><strong>Date:</strong> " . now()->format('d M, Y') . "</li>
            </ul>
            <br>
            <p>If you have any questions, feel free to contact our support team.</p>
            <p>Best regards,</p>
            <p><strong>$appName</strong></p>
        ";

        // With QUEUE_CONNECTION=sync (this app's default - see class docblock), dispatch() runs this job
        // immediately in the same request that placed the order; an uncaught exception here would propagate
        // straight back into OrderService::placeOrder() and turn a successful order into a 500 for the
        // customer, exactly the failure mode the original inline code guarded against. Caught here so that
        // can never happen regardless of which queue connection is active.
        try {
            $invoicePdf = app(OrderController::class)->generatInvoicePDF($this->orderId)->getContent();

            app(MailService::class)->sendMailWithAttachment(
                $user->email,
                $subject,
                $messageContent,
                $invoicePdf,
                "invoice-{$this->orderId}.pdf"
            );
        } catch (Throwable $e) {
            Log::error('Order confirmation email failed for order ' . $this->orderId . ': ' . $e->getMessage());
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Order confirmation email job failed for order ' . $this->orderId . ': ' . $exception->getMessage());
    }
}
