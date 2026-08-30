<?php

namespace App\Libraries;

use App\Services\SettingService;
use App\Services\SellerPaymentGatewayService;

/**
 * HyperPay (OPPWA "Copy&Pay") - widely used across Jordan/Gulf/MENA, supports mada, Visa/Mastercard,
 * STC Pay. The frontend loads HyperPay's own widget script with the checkout id create_checkout()
 * returns (PCI-compliant card collection happens inside that widget, never on this server); once the
 * shopper submits it, HyperPay redirects back to the app's own return page, which must call
 * get_payment_status() server-side before trusting the payment - never trust the redirect alone.
 */
class HyperPay
{
    public $entity_id = "";
    public $access_token = "";
    public $mode = "test";
    private $base_url = "";

    /** $sellerId: a seller's own enabled hyperpay credentials take priority over the platform default. */
    function __construct($sellerId = null)
    {
        $payment_method_settings = app(SettingService::class)->getSettings('payment_method', true);
        $payment_method_settings = json_decode($payment_method_settings, true);

        $override = $sellerId ? app(SellerPaymentGatewayService::class)->credentialsFor($sellerId, 'hyperpay') : null;

        $this->entity_id = $override['hyperpay_entity_id'] ?? $payment_method_settings['hyperpay_entity_id'] ?? "";
        $this->access_token = $override['hyperpay_access_token'] ?? $payment_method_settings['hyperpay_access_token'] ?? "";
        $this->mode = $override['hyperpay_mode'] ?? $payment_method_settings['hyperpay_mode'] ?? "test";
        $this->base_url = $this->mode === 'live' ? 'https://eu-prod.oppwa.com' : 'https://eu-test.oppwa.com';
    }

    public function create_checkout($amount, $currency, $merchant_transaction_id = '')
    {
        $data = [
            'entityId' => $this->entity_id,
            'amount' => number_format((float) $amount, 2, '.', ''),
            'currency' => $currency,
            'paymentType' => 'DB',
        ];
        if (!empty($merchant_transaction_id)) {
            $data['merchantTransactionId'] = $merchant_transaction_id;
        }
        $response = $this->curl($this->base_url . '/v1/checkouts', 'POST', $data);
        return json_decode($response['body'], true);
    }

    public function get_payment_status($checkout_id)
    {
        $url = $this->base_url . '/v1/checkouts/' . $checkout_id . '/payment?entityId=' . $this->entity_id;
        $response = $this->curl($url, 'GET');
        return json_decode($response['body'], true);
    }

    /**
     * HyperPay's own documented "successful transaction" regex against result.code. Anything else
     * (including the manual-review family of codes) is deliberately treated as not-yet-successful - a
     * conservative default, not a full implementation of every review/pending code HyperPay defines.
     */
    public function is_successful($result_code)
    {
        return (bool) preg_match('/^(000\.000\.|000\.100\.1|000\.[36])/', (string) $result_code);
    }

    private function curl($url, $method, $data = [])
    {
        $ch = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->access_token],
        ];
        if (strtolower($method) === 'post') {
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = http_build_query($data);
        }
        curl_setopt_array($ch, $options);
        $result = ['body' => curl_exec($ch), 'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE)];
        curl_close($ch);
        return $result;
    }
}
