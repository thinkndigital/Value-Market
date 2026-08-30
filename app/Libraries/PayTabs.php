<?php

namespace App\Libraries;

use App\Services\SettingService;
use App\Services\SellerPaymentGatewayService;

/**
 * PayTabs - Middle East focused hosted checkout, real coverage of Jordan and Gulf regions.
 * create_payment_page() returns a hosted redirect_url; verify_payment() re-checks the transaction
 * server-side (never trust the client-supplied tran_ref/redirect alone), matching the same
 * "server re-verifies with its own credentials" posture this app already uses for Razorpay/Paystack.
 */
class PayTabs
{
    public $profile_id = "";
    public $server_key = "";
    public $region = "GLOBAL";
    private $base_url = "";

    /** One PayTabs account can be provisioned against a specific regional endpoint. */
    private const REGION_HOSTS = [
        'JOR' => 'secure-jordan.paytabs.com',
        'SAU' => 'secure.paytabs.sa',
        'ARE' => 'secure.paytabs.com',
        'EGY' => 'secure-egypt.paytabs.com',
        'OMN' => 'secure-oman.paytabs.com',
        'GLOBAL' => 'secure-global.paytabs.com',
    ];

    /** $sellerId: a seller's own enabled paytabs credentials take priority over the platform default. */
    function __construct($sellerId = null)
    {
        $payment_method_settings = app(SettingService::class)->getSettings('payment_method', true);
        $payment_method_settings = json_decode($payment_method_settings, true);

        $override = $sellerId ? app(SellerPaymentGatewayService::class)->credentialsFor($sellerId, 'paytabs') : null;

        $this->profile_id = $override['paytabs_profile_id'] ?? $payment_method_settings['paytabs_profile_id'] ?? "";
        $this->server_key = $override['paytabs_server_key'] ?? $payment_method_settings['paytabs_server_key'] ?? "";
        $this->region = $override['paytabs_region'] ?? $payment_method_settings['paytabs_region'] ?? "GLOBAL";
        $host = self::REGION_HOSTS[$this->region] ?? self::REGION_HOSTS['GLOBAL'];
        $this->base_url = 'https://' . $host;
    }

    public function create_payment_page($amount, $currency, $cart_id, $description, array $customer, $return_url, $callback_url)
    {
        $data = [
            'profile_id' => $this->profile_id,
            'tran_type' => 'sale',
            'tran_class' => 'ecom',
            'cart_id' => $cart_id,
            'cart_currency' => $currency,
            'cart_amount' => number_format((float) $amount, 2, '.', ''),
            'cart_description' => $description,
            'customer_details' => $customer,
            'return' => $return_url,
            'callback' => $callback_url,
        ];
        $response = $this->curl('/payment/request', $data);
        return json_decode($response['body'], true);
    }

    public function verify_payment($tran_ref)
    {
        $data = [
            'profile_id' => $this->profile_id,
            'tran_type' => 'verify',
            'tran_ref' => $tran_ref,
        ];
        $response = $this->curl('/payment/request', $data);
        return json_decode($response['body'], true);
    }

    /** 'A' = Authorised, PayTabs' own code for a genuinely successful transaction. */
    public function is_successful(?array $payment_result)
    {
        return ($payment_result['response_status'] ?? '') === 'A';
    }

    private function curl($path, $data)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->base_url . $path,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: ' . $this->server_key,
            ],
        ]);
        $result = ['body' => curl_exec($ch), 'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE)];
        curl_close($ch);
        return $result;
    }
}
