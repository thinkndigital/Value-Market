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