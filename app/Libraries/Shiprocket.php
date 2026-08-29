<?php

namespace App\Libraries;

use App\Services\SettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Shiprocket
{
    private $email = "";
    private $password = "";
    private $url = "https://apiv2.shiprocket.in/v1/external/";
    private $timeout;
    private $connectTimeout;
    private $tokenTtlMinutes;

    public function __construct()
    {
        $settings = app(SettingService::class)->getSettings('shipping_method', true);
        $settings = json_decode($settings, true);
        // Base URL/timeouts come from config (config/services.php) rather than being hardcoded here, so
        // tests can point this at a local fixture server instead of ever making a real network call.
        $this->url = config('services.shiprocket.base_url', 'https://apiv2.shiprocket.in/v1/external/');
        $this->timeout = (int) config('services.shiprocket.timeout', 15);
        $this->connectTimeout = (int) config('services.shiprocket.connect_timeout', 8);
        $this->tokenTtlMinutes = (int) config('services.shiprocket.token_ttl_minutes', 9 * 24 * 60);
        // Credentials live in the `shipping_method` Setting row (admin/pages/forms/shipping_settings.blade.php),
        // matching this app's existing convention for third-party credentials (Razorpay/Stripe/Paystack) -
        // never hardcoded here.
        $this->email = isset($settings['email']) ? $settings['email'] : '';
        $this->password = isset($settings['password']) ? $settings['password'] : '';
    }

    public function get_credentials()
    {
        $data['email'] = $this->email;
        $data['password'] = $this->password;
        return $data;
    }

    /**
     * The cache key is bound to the configured email so that changing the Shiprocket account in Settings
     * can never serve a token minted for the previous account's credentials.
     */
    private function tokenCacheKey()
    {
        return 'shiprocket_auth_token_' . md5($this->email);
    }

    /**
     * Shiprocket bearer tokens stay valid for ~10 days. The previous version of this method called
     * /auth/login on every single API call made anywhere in the app (deliverability checks alone can fire
     * several times per cart/checkout page load) - wasteful, slow, and a real risk of tripping Shiprocket's
     * login rate limit. The token is now cached for config('services.shiprocket.token_ttl_minutes') and only
     * re-fetched when missing/expired or explicitly invalidated after a 401 (see curl()).
     */
    public function generate_token($forceRefresh = false)
    {
        $cacheKey = $this->tokenCacheKey();

        if (!$forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (!empty($cached)) {
                return $cached;
            }
        }

        if ($this->email === '' || $this->password === '') {
            // Nothing configured yet (Shiprocket shipping method not set up) - fail closed without ever
            // making a network call or caching an empty token.
            return "";
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => rtrim($this->url, '/') . '/auth/login',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            // json_encode() (not manual string concatenation) so an email/password containing a `"` or
            // backslash can never produce malformed JSON.
            CURLOPT_POSTFIELDS => json_encode([
                'email' => $this->email,
                'password' => $this->password,
            ]),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $result = curl_exec($curl);
        $curlErrno = curl_errno($curl);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlErrno !== 0) {
            // Never log $this->email/$this->password or the response body here - curl_error() only
            // describes the transport failure (timeout, DNS, connection refused), never credentials.
            Log::warning('Shiprocket auth request failed (network): ' . $curlError);
            return "";
        }

        $response = (!empty($result)) ? json_decode($result, true) : "";
        $token = (isset($response['token'])) ? $response['token'] : "";

        if ($token !== "") {
            Cache::put($cacheKey, $token, now()->addMinutes($this->tokenTtlMinutes));
        } else {
            // Auth genuinely failed (bad credentials, account locked, etc.) - log that it failed, never the
            // credentials or the token.
            Log::warning('Shiprocket auth did not return a token. Response message: ' . ($response['message'] ?? 'unknown'));
        }

        return $token;
    }

    /**
     * @param bool $isRetry internal flag - true only on the single automatic retry after a 401, to prevent
     *                       an infinite loop if Shiprocket keeps rejecting a freshly re-issued token.
     */
    public function curl($url, $method = 'GET', $data = [], $isRetry = false)
    {
        $token = $this->generate_token();

        if ($token === "") {
            // No credentials configured, or auth itself failed - fail closed with a safe, well-formed
            // response instead of sending an unauthenticated request (which would just 401 anyway) or
            // letting a caller's isset() checks silently degrade against `false`.
            return [
                'error' => true,
                'status' => false,
                'message' => 'Shiprocket is not configured or authentication failed.',
            ];
        }

        $ch = curl_init();
        $headers = array(
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        );
        $curl_options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_HTTPHEADER => $headers,
            // The original implementation set CURLOPT_TIMEOUT => 0 (auth) or no timeout at all (here),
            // meaning a slow/unresponsive Shiprocket could hang the request indefinitely - on the
            // deliverability-check path that runs during cart/checkout, that could tie up a PHP worker
            // until PHP's own max_execution_time fired a fatal error, breaking checkout entirely. A third-
            // party shipping API being briefly down must never be able to do that.
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
        );
        if (strtolower($method) == 'post') {
            $curl_options[CURLOPT_POST] = 1;
            $curl_options[CURLOPT_POSTFIELDS] = $data;
        } else {
            $curl_options[CURLOPT_CUSTOMREQUEST] = 'GET';
        }
        curl_setopt_array($ch, $curl_options);

        $result = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrno !== 0) {
            // Never log $headers (carries the bearer token) or $data (may carry customer PII) - only the
            // transport error and the endpoint path.
            Log::warning('Shiprocket API request failed (network): ' . $curlError . ' | url=' . $url);
            return [
                'error' => true,
                'status' => false,
                'message' => 'Shiprocket request failed: ' . $curlError,
            ];
        }

        // A 401/403 means the cached token expired or was revoked. Clear it and retry exactly once with a
        // freshly issued token before giving up - avoids surfacing a spurious failure to the caller (and to
        // checkout) just because the 9-day cache window happened to lapse mid-request.
        if (($httpCode == 401 || $httpCode == 403) && !$isRetry) {
            Cache::forget($this->tokenCacheKey());
            return $this->curl($url, $method, $data, true);
        }

        $decoded = (!empty($result)) ? json_decode($result, true) : null;

        if ($decoded === null) {
            // Either an empty body or a non-JSON body (Shiprocket returning an HTML error page for a 5xx,
            // for example) - never let downstream `isset($res['...'])` checks see a bare `false`/string.
            return [
                'error' => true,
                'status' => false,
                'message' => 'Shiprocket returned an unexpected response (HTTP ' . $httpCode . ').',
            ];
        }

        return $decoded;
    }

    public function check_serviceability($data)
    {
        $pickup_location = (isset($data['pickup_postcode']) && !empty($data['pickup_postcode'])) ? $data['pickup_postcode'] : "";
        $delivery_pincode = (isset($data['delivery_postcode']) && !empty($data['delivery_postcode'])) ? $data['delivery_postcode'] : "";
        $weight = (isset($data['weight']) && !empty($data['weight'])) ? $data['weight'] : "";
        $cod = (isset($data['cod']) && !empty($data['cod'])) ? $data['cod'] : 0;

        $query = array(
            "pickup_postcode" => $pickup_location,
            "delivery_postcode" => $delivery_pincode,
            "weight" => $weight,
            "cod" => $cod
        );

        $qry_str = http_build_query($query);

        $url = $this->url . 'courier/serviceability/?' . $qry_str;

        $result = $this->curl($url);
        return $result;
    }

    public function cancel_order($shipment_id)
    {
        $url = $this->url . 'orders/cancel';
        $data = array(
            'ids' => [$shipment_id]
        );
        $result = $this->curl($url, "POST", json_encode($data));
        return $result;
    }

    public function get_specific_order($order_id)
    {
        // firebase server url to send the curl request

        $url = $this->url . 'orders/show/' . $order_id;
        $result = $this->curl($url);

        //and return the result
        return $result;
    }

    public function create_order($data)
    {
        // firebase server url to send the curl request
        $url = $this->url . 'orders/create/adhoc';

        //building headers for the request

        $data = json_encode($data);
        $result = $this->curl($url, $method = 'POST', $data);
        return $result;
    }

    public function generate_awb($shipment_id)
    {
        $url = $this->url . 'courier/assign/awb';
        $data = array(
            'shipment_id' => $shipment_id,
        );
        $result = $this->curl($url, "POST", json_encode($data));

        return $result;
    }

    public function get_order($shipment_id)
    {
        // firebase server url to send the curl request

        $url = $this->url . 'shipments/' . $shipment_id;
        $result = $this->curl($url);

        //and return the result
        return $result;
    }
    public function request_for_pickup($shipment_id)
    {
        // firebase server url to send the curl request
        $url = $this->url . 'courier/generate/pickup';

        $shipment_id = array('shipment_id' => $shipment_id);
        $result = $this->curl($url, "POST", json_encode($shipment_id));

        //and return the result
        return $result;
    }
    public function generate_label($shipment_id)
    {
        $url = $this->url . 'courier/generate/label';
        $data = array(
            'shipment_id' => [$shipment_id]
        );
        $result = $this->curl($url, 'POST', json_encode($data));
        return $result;
    }
    public function generate_invoice($order_id)
    {
        $url = $this->url . 'orders/print/invoice';
        $data = array(
            'ids' => [$order_id]
        );
        $result = $this->curl($url, 'POST', json_encode($data));
        return $result;
    }

    public function add_pickup_location($data)
    {
        // firebase server url to send the curl request
        // dd($data);
        $url = $this->url . 'settings/company/addpickup';
        $result = $this->curl($url, "POST", json_encode($data));
        // dd($result);
        //and return the result

        return $result;
    }
    public function tracking_order($tracking_id)
    {

        $url = $this->url . 'courier/track?order_id=' . $tracking_id;
        //building headers for the request
        $result = $this->curl($url, 'GET');
        // dd($result);
        return $result;
    }
}
