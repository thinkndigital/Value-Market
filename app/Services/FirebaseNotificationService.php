<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Setting;
use App\Models\User;
use App\Models\CustomMessage;
use Illuminate\Support\Facades\DB;
use App\Services\StoreService;
use Google\Client;
class FirebaseNotificationService
{
    public function getAccessToken()
    {
        static $accessToken = null;

        if ($accessToken != null) {
            return $accessToken;
        }

        // Fetch the file name from the settings table
        $fileName = Setting::where('variable', 'service_account_file')
            ->value('value');

        // Construct the file path in the storage/app/public directory
        $filePath = storage_path('app/public/' . $fileName);

        // Check if the file exists
        if (!is_file($filePath) || !file_exists($filePath)) {
            throw new \Exception('Service account file not found.');
        }

        // Initialize the Google Client
        $client = new Client();
        $client->setAuthConfig($filePath);
        $client->setScopes(['https://www.googleapis.com/auth/firebase.messaging']);

        // Fetch the access token
        $accessToken = $client->fetchAccessTokenWithAssertion()['access_token'];
        return $accessToken;
    }
    public function sendNotification($fcmMsg, $registrationIDsChunks, $customBodyFields = [], $title = "test title", $message = "test message", $type = "test type")
    {
        $storeId = app(StoreService::class)->getStoreId();
        $storeId = $storeId ?: ($customBodyFields['store_id'] ?? "");

        $projectId = Setting::where('variable', 'firebase_project_id')->value('value');
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $accessToken = $this->getAccessToken();
        // dd($accessToken);
        foreach ($registrationIDsChunks as $registrationIDs) {
            foreach ($registrationIDs as $registrationID) {
                if (empty($registrationID) || $registrationID == "BLACKLISTED") continue;

                $data = [
                    "message" => [
                        "token" => $registrationID,
                        "notification" => [
                            "title" => $customBodyFields['title'],
                            "body" => $customBodyFields['body'],
                        ],
                        "data" => $customBodyFields,
                        "android" => [
                            "notification" => [
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                            ],
                            "data" => [
                                "title" => $title,
                                "body" => $message,
                                "type" => $customBodyFields['type'] ?? $type,
                                "store_id" => strval($storeId),
                            ]
                        ],
                        "apns" => [
                            "headers" => ["apns-priority" => "10"],
                            "payload" => [
                                "aps" => [
                                    "alert" => [
                                        "title" => $customBodyFields['title'],
                                        "body" => $customBodyFields['body'],
                                    ],
                                    "user_id" => $customBodyFields['user_id'] ?? '',
                                    "store_id" => strval($storeId),
                                    "data" => $customBodyFields,
                                ]
                            ]
                        ],
                    ]
                ];

                $result = Http::withToken($accessToken) 
                    ->withHeaders([
                        'Content-Type' => 'application/json', 
                    ])
                    ->post($url, $data);

                    // dd($result);
                return $result;
            }
        }

        return true;
    }
    public function sendCustomNotificationOnPaymentSuccess($orderId, $userId)
    {
        // Fetch custom notification template
        $customNotification = fetchDetails(CustomMessage::class, ['type' => 'place_order'], '*');

        // Replace placeholders in title
        $hashtagOrderId = '< order_id >';
        $titleTemplate = !$customNotification->isEmpty() ? json_encode($customNotification[0]->title, JSON_UNESCAPED_UNICODE) : "";
        $titleTemplateDecoded = html_entity_decode($titleTemplate);
        $title = str_replace($hashtagOrderId, $orderId, $titleTemplateDecoded);
        $title = trim($title, '"');

        // Replace placeholders in message
        $hashtagApplicationName = '< application_name >';
        $messageTemplate = !$customNotification->isEmpty() ? json_encode($customNotification[0]->message, JSON_UNESCAPED_UNICODE) : "";
        $messageTemplateDecoded = html_entity_decode($messageTemplate);
        $appName = $system_settings['app_name'] ?? Setting::where('variable', 'app_name')->value('value');
        $message = str_replace($hashtagApplicationName, $appName, $messageTemplateDecoded);
        $message = trim($message, '"');

        // Default FCM message and subject
        $fcmAdminSubject = !empty($customNotification) ? $title : 'New order placed ID #' . $orderId;
        $fcmAdminMsg = !empty($customNotification) ? $message : 'New order received for ' . $appName . ', please process it.';

        // Fetch user FCM details
        $userFcm = fetchDetails(User::class, ['id' => $userId], ['fcm_id', 'mobile', 'email']);
        $userFcmId[] = !$userFcm->isEmpty() ? [$userFcm[0]->fcm_id] : [];

        // Get Firebase project and service account details
        $firebaseProjectId = Setting::where('variable', 'firebase_project_id')->value('value');
        $serviceAccountFile = DB::table('settings')->where('variable', 'service_account_file')->value('value');

        // If Firebase details are available, send the notification
        if (!empty($userFcmId) && !empty($firebaseProjectId) && !empty($serviceAccountFile)) {
            $fcmMsg = [
                'title' => $fcmAdminSubject,
                'body' => $fcmAdminMsg,
                'image' => '',
                'type' => 'place_order',
            ];

            // Call function to send notification
           $this->sendNotification('', $userFcmId, $fcmMsg);
        }
    }

}
