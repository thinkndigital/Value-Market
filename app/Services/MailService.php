<?php

namespace App\Services;
use App\Services\SettingService;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
class MailService
{
    public function sendDigitalProductMail($to, $subject, $emailMessage, $attachment)
    {

        try {
            Mail::send([], [], function (Message $message) use ($to, $subject, $emailMessage, $attachment) {
                $email_settings = app(SettingService::class)->getSettings('email_settings', true);
                $email_settings = json_decode($email_settings, true);
                $message->to($to)
                    ->subject($subject)
                    ->html($emailMessage)
                    ->from($email_settings['email'], env('APP_NAME'));
            });

            $response = [
                'error' => false,
                'message' => 'Email Sent'
            ];
        } catch (\Exception $e) {
            $response = [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }

        return $response;
    }

    public function sendCustomMail($to, $subject, $emailMessage, $attachment)
    {

        try {
            Mail::send([], [], function (Message $message) use ($to, $subject, $emailMessage, $attachment) {
                $email_settings = app(SettingService::class)->getSettings('email_settings', true);
                $email_settings = json_decode($email_settings, true);
                $message->to($to)
                    ->subject($subject)
                    ->html($emailMessage)
                    ->from($email_settings['email'], env('APP_NAME'));
            });

            $response = [
                'error' => false,
                'message' => 'Email Sent'
            ];
        } catch (\Exception $e) {
            $response = [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }

        return $response;
    }


    /**
     * Changelog v1.0.3 ("Email order invoices"): OrderService::placeOrder() built a confirmation email with
     * a "Download Invoice" link pointing at an admin-only route (/admin/orders/generat_invoice_PDF/{id}) -
     * unreachable by the customer who receives the email, since they have no admin session. Attaching the
     * PDF directly avoids needing any authenticated link at all. $attachment (unused by every existing
     * caller of sendCustomMail()/sendDigitalProductMail() despite being accepted as a parameter) attaches
     * raw file bytes already in memory (e.g. an invoice PDF's response content) rather than a path on disk,
     * matching how this app's own PDF generation already works (Admin\OrderController::generatInvoicePDF()
     * returns a Response, not a saved file).
     */
    public function sendMailWithAttachment($to, $subject, $emailMessage, ?string $attachmentContent = null, ?string $attachmentName = null, string $attachmentMime = 'application/pdf')
    {
        try {
            Mail::send([], [], function (Message $message) use ($to, $subject, $emailMessage, $attachmentContent, $attachmentName, $attachmentMime) {
                $email_settings = app(SettingService::class)->getSettings('email_settings', true);
                $email_settings = json_decode($email_settings, true);
                $message->to($to)
                    ->subject($subject)
                    ->html($emailMessage)
                    ->from($email_settings['email'] ?? config('mail.from.address'), env('APP_NAME'));

                if (!empty($attachmentContent) && !empty($attachmentName)) {
                    $message->attachData($attachmentContent, $attachmentName, ['mime' => $attachmentMime]);
                }
            });

            $response = [
                'error' => false,
                'message' => 'Email Sent',
            ];
        } catch (\Exception $e) {
            $response = [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }

        return $response;
    }

    public function sendContactUsMail($from, $subject, $emailMessage)
    {
        try {
            Mail::send([], [], function (Message $message) use ($from, $subject, $emailMessage) {
                $email_settings = app(SettingService::class)->getSettings('email_settings', true);
                $email_settings = json_decode($email_settings, true);
                $message->from($from)
                    ->subject($subject)
                    ->html($emailMessage)
                    ->to($email_settings['email'], env('APP_NAME'));
            });

            $response = [
                'error' => false,
                'message' => 'Email Sent'
            ];
        } catch (\Exception $e) {
            $response = [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }

        return $response;
    }
    public function sendMailTemplate($to, $template_key, $givenLanguage = "", $data = [], $subjectData = [])
    {
        if ($givenLanguage == "") {
            $givenLanguage = session("locale") ?? "default";
        }

        $viewpath = "components.utility.email_templates.$template_key.";
        if (View::exists($viewpath . $givenLanguage)) {
            $viewpath .= $givenLanguage;
        } else {
            $viewpath .= "default";
        }

        $emailMessage = view($viewpath, $data)->render();
        $subject = strip_tags(view($viewpath . "-subject", $subjectData)->render());
        $response = $this->sendCustomMail($to, $subject, $emailMessage, "");
        return $response;
    }

    public function isEmailConfigured()
    {

        $email_settings = app(SettingService::class)->getSettings('email_settings', true);
        $email_settings = json_decode($email_settings, true);

        if (
            isset($email_settings['email']) && !empty($email_settings['email']) &&
            isset($email_settings['password']) && !empty($email_settings['password']) &&
            isset($email_settings['smtp_host']) && !empty($email_settings['smtp_host']) &&
            isset($email_settings['smtp_port']) && !empty($email_settings['smtp_port'])
        ) {
            return true;
        } else {
            return false;
        }
    }

}