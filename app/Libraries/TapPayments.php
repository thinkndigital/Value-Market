<?php

namespace App\Libraries;

use App\Services\SettingService;
use App\Services\SellerPaymentGatewayService;

/**
 * Tap Payments - Gulf-focused hosted checkout (Kuwait, Saudi Arabia, UAE, Bahrain, Qatar).
 * create_charge() returns a hosted redirect_url (source id "src_all" covers every payment method Tap
 * offers the merchant, not just cards); retrieve_charge() re-fetches the charge server-side with our own
 * secret key rather than trusting the client-supplied charge id/redirect alone - the same posture as
 * PayTabs/Razorpay/Paystack elsewhere in this app.
 */
class TapPayments
{
    public $secret_key = "";
    public $publishable_key = "";
    private $base_url = "https://api.tap.company/v2";

    /** $sellerId: a seller's own enabled tap credentials take priority over the platform default. */
    function __construct($sellerId = null)
    {
        $payment_method_settings = app(SettingService::class)->getSettings('payment_method', true);
        $payment_method_settings = json_decode($payment_method_settings, true);

        $override = $sellerId ? app(SellerPaymentGatewayService::class)->credentialsFor($sellerId, 'tap') : null;

        $this->secret_key = $override['tap_secret_key'] ?? $payment_method_settings['tap_secret_key'] ?? "";
        $this->publishable_key = $override['tap_publishable_key'] ?? $payment_method_settings['tap_publishable_key'] ?? "";
    }

    public function create_charge($amount, $currency, array $customer, $redirect_url, $post_url, $description = '')
    {
        $data = [
            'amount' => (float) $amount,
            'currency' => $currency,
            'threeDSecure' => true,
            'save_card' => false,
            'description' => $description,
            'customer' => $customer,
            'source' => ['id' => 'src_all'],
            'redirect' => ['url' => $redirect_url],
            'post' => ['url' => $post_url],
        ];
        $response = $this->curl('/charges', 'POST', $data);
        return json_decode($response['body'], true);
    }

    public function retrieve_charge($charge_id)
    {
        $response = $this->curl('/charges/' . $charge_id, 'GET');
        return json_decode($response['body'], true);
    }

    public function is_successful(?string $status)
    {
        return $status === 'CAPTURED';
    }

    private function curl($path, $method, $data = [])
    {
        $ch = curl_init();
        $options = [
            CURLOPT_URL => $this->base_url . $path,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->secret_key,
            ],
        ];
        if (strtolower($method) === 'post') {
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        curl_setopt_array($ch, $options);
        $result = ['body' => curl_exec($ch), 'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE)];
        curl_close($ch);
        return $result;
    }
}
