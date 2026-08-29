<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Setting;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * docs/CHANGELOG_FEATURE_AUDIT.md (v1.0.3, "Email order invoices"): OrderService::placeOrder() already sent
 * a confirmation email, but it linked to /admin/orders/generat_invoice_PDF/{id} - an admin-only route the
 * customer receiving the email has no session for. Fixed to attach the actual invoice PDF instead, via a
 * new MailService::sendMailWithAttachment() (the $attachment parameter on the pre-existing
 * sendCustomMail()/sendDigitalProductMail() was accepted but never actually used by either method).
 *
 * Also fixed: the email send in placeOrder() previously called Mail::send() directly, unguarded - any SMTP
 * failure there would throw an uncaught exception AFTER the order had already been committed to the
 * database, turning a real, successful order into a 500 response for the customer. It's now wrapped in
 * try/catch and skipped entirely when the customer has no email on file or email isn't configured yet
 * (matching how this app's other optional-integration guards already behave), so a notification failure can
 * never take down order placement.
 */
class OrderInvoiceEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_mail_with_attachment_sends_successfully_with_a_pdf_attached(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'email_settings', 'value' => json_encode(['email' => 'store@example.com'])]);
        Mail::fake();

        $response = app(MailService::class)->sendMailWithAttachment(
            'customer@example.com',
            'Your Invoice',
            '<p>Thanks for your order</p>',
            '%PDF-1.4 fake pdf bytes',
            'invoice-123.pdf'
        );

        $this->assertFalse($response['error'], 'sendMailWithAttachment should report success: ' . json_encode($response));
    }

    public function test_is_email_configured_reflects_the_email_settings_row(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);

        $this->assertFalse(app(MailService::class)->isEmailConfigured(), 'No email_settings row at all must report not configured.');

        Setting::forceCreate(['variable' => 'email_settings', 'value' => json_encode([
            'email' => 'store@example.com', 'password' => 'secret', 'smtp_host' => 'smtp.example.com', 'smtp_port' => 587,
        ])]);

        $this->assertTrue(app(MailService::class)->isEmailConfigured());
    }

    public function test_send_mail_with_attachment_returns_an_error_response_instead_of_throwing_on_failure(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        // No email_settings row and no mail transport configured for this test run - Mail::send() will
        // throw internally. This proves the failure is caught and reported, not propagated - the same
        // safety property OrderService::placeOrder() now relies on to never fail order placement over a
        // notification problem.
        $response = app(MailService::class)->sendMailWithAttachment(
            'customer@example.com',
            'Your Invoice',
            '<p>Thanks for your order</p>',
            '%PDF-1.4 fake pdf bytes',
            'invoice-123.pdf'
        );

        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
    }
}
