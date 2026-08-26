<?php
/*
    1. get_credentials()
    2. create_order($amount,$receipt='')
    3. fetch_payments($id ='')
    4. capture_payment($amount, $id, $currency = "INR")
    5. verify_payment($order_id, $razorpay_payment_id, $razorpay_signature)

    0. curl($url, $method = 'GET', $data = [])
*/
namespace App\Libraries;
use App\Services\SettingService;
class Phonepe
{
    private $salt_index = "";
    private $salt_key = "";
    private $merchant_id = "";
    private $url = "";
    private $environment = "";
    private $app_id = "";
    private $client_id = "";

    private $client_secret = "";
    function __construct()
    {
        $settings = app(SettingService::class)->getSettings('payment_method', true);
        $settings = json_decode($settings);
        // $this->salt_index = $settings->phonepe_salt_index ?? " ";
        // $this->salt_key = $settings->phonepe_salt_key ?? " ";
        // $this->merchant_id = $settings->phonepe_marchant_id ?? " ";
        $this->client_id = $settings->phonepe_client_id ?? " ";
        $this->client_secret = $settings->phonepe_client_secret ?? " ";
        $this->url = (isset($settings->phonepe_mode) && $settings->phonepe_mode == "PRODUCTION") ? "https://api.phonepe.com/apis/hermes" : "https://api-preprod.phonepe.com/apis/pg-sandbox";
        // dd($settings);
        if (isset($settings->phonepe_mode)) {
            if ($settings->phonepe_mode == 'PRODUCTION') {
                $this->environment = 'PRODUCTION';
            } elseif ($settings->phonepe_mode == 'UAT') {
                $this->environment = 'UAT';
            } else {
                $this->environment = 'SANDBOX';
            }
        }
    }

    public function get_credentials()
    {
        $data['salt_index'] = $this->salt_index;
        $data['salt_key'] = $this->salt_key;
        $data['merchant_id'] = $this->merchant_id;
        $data['url'] = $this->url;
        return $data;
    }

    public function pay($data)
    {
        $data['merchantId'] = $this->merchant_id;
        $data['paymentInstrument'] = array(
            'type' => 'PAY_PAGE',
        );
        $url = $this->url . '/pg/v1/pay';
        $method = 'POST';
        /** generating a X-VERIFY header */
        $encode = base64_encode(json_encode($data));
        $saltKey = $this->salt_key;
        $saltIndex = $this->salt_index;
        $string = $encode . '/pg/v1/pay' . $saltKey;
        $sha256 = hash('sha256', $string);
        $finalXHeader = $sha256 . '###' . $saltIndex;

        $header = [
            "Content-Type: application/json",
            "accept: application/json",
            "X-VERIFY: $finalXHeader"
        ];
        $response = $this->curl($url, $method, json_encode(['request' => $encode]), $header);
        $res = json_decode($response['body'], true);
        return $res;
    }

    public function phonepe_checksum($data)
    {
        $phonePeMerId = $this->merchant_id;
        $phonePeEndPointUrl = url("admin/webhook/phonepe_webhook");
        // $phonePeEndPointUrl = "	https://webhook.site/55e4ad5a-cf46-4d85-bb93-3f3ced4c0917";
        $phonePeSaltKey = $this->salt_key;
        $phonePeSaltIndex = $this->salt_index;
        $totalPrice = $data['final_total'];
        $userMobileNumber = $data['mobile'];
        $amt = (int) round($totalPrice);
        // dd($amt);
        $jsonData = [
            "merchantId" => $phonePeMerId,
            "merchantUserId" => $phonePeMerId,
            "merchantTransactionId" => $data['order_id'],
            "amount" => $amt,
            "redirectUrl" => $phonePeEndPointUrl,
            "redirectMode" => "REDIRECT",
            "callbackUrl" => $phonePeEndPointUrl,
            "mobileNumber" => $userMobileNumber,
            "paymentInstrument" => ["type" => "PAY_PAGE"]
        ];
        $base64Data = base64_encode(json_encode($jsonData, JSON_UNESCAPED_SLASHES));
        $apiEndPoint = "/pg/v1/pay";
        $dataToHash = $base64Data . $apiEndPoint . $phonePeSaltKey;
        $sHA256 = hash('sha256', $dataToHash);
        $checksum = $sHA256 . '###' . $phonePeSaltIndex;
        $token = $this->get_access_token();
        // dd($token);
        $data = [
            "payload" => $jsonData,
            "checksum" => $checksum,
            "token" => $token
        ];
        return $data;
    }
    public function phonepe_checksum_v2($data)
    {

        $orderId = 'TX' . time(); // unique order ID

        $expireAfter = 1200; // in seconds (20 mins)
        $token = $this->get_access_token();

        $order = $this->pay_v2($data, 'app');
        // dd($order);
        $requestPayload = [
            // "orderId" => $data['merchantTransactionId'],
            "state" => "PENDING",
            "merchantOrderId" => $order['orderId'],
            "amount" => $data['amount'], 
            "expireAT" => $expireAfter,
            "token" => $order['token'],
            "paymentMode" => [
                "type" => "PAY_PAGE"
            ]
        ];

        // Convert to JSON string as required by Flutter SDK
        $requestString = json_encode($requestPayload);

        return [
            "environment" => $this->environment, // or "PRODUCTION"
            "merchantOrderId" => $order['orderId'],
            "flowId" => $orderId,
            "enableLogging" => true, // false in production
            "request" => $requestPayload,
            "token" => $token,
        ];
    }
    public function check_status($id = '')
    {
        $data['merchantId'] = $this->merchant_id;
        $data['paymentInstrument'] = array(
            'type' => 'PAY_PAGE',
        );
        $endpoint = "/pg/v1/status/$this->merchant_id/$id";
        $url = $this->url . $endpoint;
        $method = 'GET';

        /** generating a X-VERIFY header */
        $saltKey = $this->salt_key;
        $saltIndex = $this->salt_index;
        $string = $endpoint . "" . $saltKey;
        $sha256 = hash('sha256', $string);
        $finalXHeader = $sha256 . '###' . $saltIndex;

        $header = [
            "Content-Type: application/json",
            "X-VERIFY: $finalXHeader",
            "X-MERCHANT-ID: $this->merchant_id",
        ];
        $response = $this->curl($url, $method, [], $header);
        $res = json_decode($response['body'], true);
        return $res;
    }

    // phone pe new apis 


    public function get_access_token()
    {
        $client_id = $this->client_id;
        $client_version = 1;
        $client_secret = $this->client_secret;
        $grant_type = "Generated";

        $curl = curl_init();


        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->url . '/v1/oauth/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'client_id=' . $client_id . '&client_version=' . $client_version . '&client_secret=' . $client_secret . '&grant_type=' . $grant_type,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $getToken = json_decode($response, true);

        //echo $getToken['access_token'];
        if (isset($getToken['access_token']) && $getToken['access_token'] != '') {
            $accessToken = $getToken['access_token'];
            $expires_at = $getToken['expires_at'];
            // Save this details in the database to use access token and check expiry

        } else {
            $accessToken = '';
            $expires_at = '';
        }

        return $accessToken;
    }
    public function pay_v2($data, $type = "")
    {
        // dd($data);
        $accessToken = $this->get_access_token();

        if (!$accessToken) {
            return ['error' => 'Could not fetch access token'];
        }
        if ($type == 'app') {
            $url = $this->url . "/checkout/v2/sdk/order";
            $data['redirectUrl'] = "";
        } else {
            $url = $this->url . "/checkout/v2/pay";
        }
        $payload = [
            "merchantOrderId" => $data['merchantTransactionId'],
            "amount" => intval($data['amount']),
            "metaInfo" => $data,
            "paymentFlow" => [
                "type" => "PG_CHECKOUT",
                "message" => "Payment message used for collect requests",
                "merchantUrls" => [
                    "redirectUrl" => $data['redirectUrl'] . "&id=" . $data['merchantTransactionId'],
                ]
            ]
        ];
        $headers = [
            'Content-Type: application/json',
            'Authorization: O-Bearer ' . $accessToken
        ];


        $response = $this->curl($url, 'POST', json_encode($payload), $headers);
        $response_data = json_decode($response['body'], true);

        if ($type == 'app') {
            return $response_data;
        } else {
            return [
                'orderId' => $response_data['orderId'] ?? '',
                'redirectUrl' => $response_data['redirectUrl'],
                'merchantOrderId' => $data['merchantTransactionId']
            ];
        }
        // return $data;
    }
    public function check_status_v2($id = '')
    {
        $accessToken = $this->get_access_token();

        $data['paymentInstrument'] = array(
            'type' => 'PAY_PAGE',
        );
        $endpoint = '/checkout/v2/order/' . $id . '/status';
        $url = $this->url . $endpoint;
        $method = 'GET';

        $header = [
            'Content-Type: application/json',
            'Authorization: O-Bearer ' . $accessToken
        ];
        $response = $this->curl($url, $method, [], $header);
        $res = json_decode($response['body'], true);
        return $res;
    }
    public function curl($url, $method = 'POST', $data = [], $header = [])
    {
        $ch = curl_init();
        $curl_options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_HTTPHEADER => $header
        );
        if (strtolower($method) == 'post') {
            $curl_options[CURLOPT_POST] = 1;
            if (!empty($data)) {
                $curl_options[CURLOPT_POSTFIELDS] = $data;
            }
        } else {
            $curl_options[CURLOPT_CUSTOMREQUEST] = 'GET';
        }
        curl_setopt_array($ch, $curl_options);
        $result = array(
            'body' => curl_exec($ch),
            'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
        );
        return $result;
    }
}
