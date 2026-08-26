<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartReminder;
use App\Models\CustomMessage;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Seller;
use App\Models\SellerCommission;
use App\Models\User;
use App\Models\UserFcm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Traits\HandlesValidation;
use App\Services\FirebaseNotificationService;
use App\Services\StoreService;
use App\Services\SettingService;
use App\Services\WalletService;
use App\Services\PromoCodeService;
class CronJobController extends Controller
{
    use HandlesValidation;
    public function settleSellerCommission(Request $request)
    {

        $rules = [
            'is_date' => 'required',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }
        $store_id = app(StoreService::class)->getStoreId();
        $is_date = (isset($request['is_date']) && is_numeric($request['is_date']) && !empty(trim($request['is_date']))) ? $request['is_date'] : false;

        $date = now()->toDateString();


        $settings = app(SettingService::class)->getSettings('system_settings', true);
        $settings = json_decode($settings, true);

        if ($is_date) {
            $where = "active_status='delivered' AND is_credited=0 and  DATE_ADD(DATE_FORMAT(oi.created_at, '%Y-%m-%d'), INTERVAL " . $settings['max_days_to_return_item'] . " DAY) = '" . $date . "'";
        } else {
            $where = "active_status='delivered' AND is_credited=0 ";
        }

        $data = OrderItems::with([
            'productVariant.product.category:id'
        ])
            ->select('id', 'order_id', 'product_variant_id', 'seller_id', 'sub_total', DB::raw('DATE(created_at) as order_date'))
            ->whereRaw($where)
            ->get()
            ->map(function ($item) {
                // dd($item->productVariant->product);
                return [
                    'category_id' => optional($item->productVariant->product->category)->id,
                    'id' => $item->id,
                    'order_date' => $item->order_date,
                    'order_id' => $item->order_id,
                    'product_variant_id' => $item->product_variant_id,
                    'seller_id' => $item->seller_id,
                    'sub_total' => $item->sub_total,
                ];
            })
            ->toArray();
        // dd($data);
        $walletUpdated = false;


        if (!empty($data)) {
            // dd('here');
            // dd($data);
            foreach ($data as $row) {
                // dd($row);
                $user_id = Seller::where('id', $row['seller_id'])->value('user_id');

                $catCom = fetchDetails(SellerCommission::class, ['seller_id' => $row['seller_id'], 'category_id' => $row['category_id']], 'commission');
                // dd($catCom);
                if (!$catCom->isEmpty() && ($catCom[0]->commission != 0)) {
                    $commissionPr = $catCom[0]->commission;
                } else {

                    $seller = Seller::with(['stores' => function ($q) use ($store_id) {
                        $q->where('store_id', $store_id);
                    }])->find($row['seller_id']);

                    // Access the commission from the pivot
                    $globalComm = optional($seller->stores->first())->pivot->commission ?? null;

                    // Using ternary operator to handle the array access and empty check
                    $commissionPr = (isset($globalComm) && !empty($globalComm) && isset($globalComm[0]->commission)) ? $globalComm[0]->commission : 0;
                }

                $commissionAmt = $row['sub_total'] / 100 * $commissionPr;
                $transferAmt = $row['sub_total'] - $commissionAmt;

                $response = app(WalletService::class)->updateWalletBalance('credit',  $user_id, $transferAmt, 'Commission Amount Credited for Order Item ID  : ' . $row['id'], $row['id']);
                // dd($response);
                if ($response['error'] == false) {
                    updateDetails(['is_credited' => 1, 'admin_commission_amount' => $commissionAmt, "seller_commission_amount" => $transferAmt], ['id' => $row['id']], OrderItems::class);
                    $walletUpdated = true;
                    $responseData['error'] = false;
                    $responseData['message'] =
                        labels('admin_labels.commission_settled_successfully', 'Commission settled Successfully');
                } else {
                    $walletUpdated = false;
                    $responseData['error'] = true;
                    $responseData['message'] =
                        labels('admin_labels.commission_not_settled', 'Commission not settled');
                }
            }
            // dd('sbsaf');
            if ($walletUpdated == true) {
                $sellerIds = array_values(array_unique(array_column($data, "seller_id")));

                foreach ($sellerIds as $seller) {

                    $settings = app(SettingService::class)->getSettings('system_settings', true);
                    $settings = json_decode($settings, true);
                    $appName = isset($settings['app_name']) && !empty($settings['app_name']) ? $settings['app_name'] : '';

                    $userRes = fetchDetails(User::class, ['id' => $seller], ['username', 'fcm_id', 'email', 'mobile']);
                }
            } else {
                $responseData['error'] = true;
                $responseData['message'] =
                    labels('admin_labels.commission_not_settled', 'Commission not settled');
            }
        } else {
            $responseData['error'] = true;
            $responseData['message'] =
                labels('admin_labels.no_order_found_for_settlement', 'No order found for settlement');
        }

        return response()->json($responseData);
    }


    public function settleCashbackDiscount(Request $request)
    {
        $return = false;
        $date = now()->format('Y-m-d');
        $settings = app(SettingService::class)->getSettings('system_settings', true);
        $settings = json_decode($settings, true);

        $returnableData = Order::with(['orderItems.productVariant.product'])
            ->select('orders.id', 'orders.created_at', 'orders.total', 'orders.final_total', 'orders.promo_code_id', 'orders.user_id', DB::raw('DATE_FORMAT(orders.created_at, "%Y-%m-%d") AS date'))
            ->whereHas('orderItems', function ($query) {
                $query->where('active_status', 'delivered');
            })
            ->whereNotNull('promo_code_id')
            ->where('promo_code_id', '!=', '')
            ->where('promo_discount', '<=', 0)
            ->groupBy('orders.id')
            ->get();

        // Now determine returnable status per order without querying again
        foreach ($returnableData as $order) {
            $returnableStatus = [];

            foreach ($order->orderItems as $item) {
                $isReturnable = optional($item->productVariant->product)->is_returnable;
                if (in_array($isReturnable, [0, 1])) {
                    $returnableStatus[] = $isReturnable;
                }
            }

            $order->returnable = in_array(1, $returnableStatus);
        }
        // dd($order);
        $selectDate = $return
            ? DB::raw("DATE_ADD(DATE_FORMAT(orders.created_at, '%Y-%m-%d'), INTERVAL {$settings['max_days_to_return_item']} DAY) AS date")
            : DB::raw("DATE_FORMAT(orders.created_at, '%Y-%m-%d') AS date");

        $data = Order::with(['orderItems'])
            ->select(
                'orders.id',
                'orders.store_id',
                'orders.created_at',
                'orders.total',
                'orders.final_total',
                'orders.promo_code_id',
                'orders.user_id',
                $selectDate
            )
            ->whereHas('orderItems', function ($query) {
                $query->where('active_status', 'delivered');
            })
            ->whereNotNull('promo_code_id')
            ->where('promo_code_id', '!=', '')
            ->where('promo_discount', '<=', 0)
            ->groupBy('orders.id')
            ->get();
        // dd($data);
        $walletUpdated = false;
        if ($data->isNotEmpty()) {
            foreach ($data as $row) {
                // dd($row);
                $promoCodeId = $row->promo_code_id;
                $userId = $row->user_id;
                $finalTotal = $row->final_total;
                $store_id = $row->store_id;

                $res = app(abstract: PromoCodeService::class)->validatePromoCode($promoCodeId, $userId, $finalTotal, 1);
                // dd($res);
                if (!empty($res->original['data']) && isset($res->original['data'][0])) {
                    $response = app(WalletService::class)->updateWalletBalance('credit', $userId, $res->original['data'][0]->final_discount, 'Discounted Amount Credited for Order Item ID: ' . $row->id);
                    if ($response['error'] == false && $response['error'] == '') {
                        updateDetails(['total_payable' => $res->original['data'][0]->final_total, 'final_total' => $res->original['data'][0]->final_total, 'promo_discount' => $res->original['data'][0]->final_discount], ['id' => $row->id], Order::class);
                        $walletUpdated = true;
                        $response_data['error'] = false;
                        $response_data['message'] =
                            labels('admin_labels.discount_added_successfully', 'Discount Added Successfully...');
                    } else {
                        $walletUpdated = false;
                        $response_data['error'] =  true;
                        $response_data['message'] =
                            labels('admin_labels.discount_not_added', 'Discount not Added');
                    }
                }
            }

            if ($walletUpdated == true) {
                $userIds = array_values(array_unique($data->pluck('user_id')->toArray()));
                foreach ($userIds as $user) {

                    //custom message
                    $app_name = isset($settings['app_name']) && !empty($settings['app_name']) ? $settings['app_name'] : '';
                    $user_res = fetchDetails(User::class, ['id' => $user], ['username', 'fcm_id', 'email', 'mobile']);
                    $custom_notification =  fetchDetails(CustomMessage::class, ['type' => "settle_cashback_discount"], '*');
                    $hashtag_customer_name = '< customer_name >';
                    $hashtag_application_name = '< application_name >';
                    $string = !$custom_notification->isEmpty() ? json_encode($custom_notification[0]->message, JSON_UNESCAPED_UNICODE) : '';
                    $hashtag = html_entity_decode($string);
                    $data = str_replace(array($hashtag_customer_name, $hashtag_application_name), array($user_res[0]->username, $app_name), $hashtag);
                    $message = outputEscaping(trim($data, '"'));
                    $customer_title = !$custom_notification->isEmpty() ? $custom_notification[0]->title : "Discounted Amount Credited";
                    $customer_msg = !$custom_notification->isEmpty() ? $message :  'Hello Dear ' . $user_res[0]->username . 'Discounted Amount Credited, which orders are delivered. Please take note of it! Regards' . $app_name . '';

                    $fcm_ids = array();

                    $results = UserFcm::with('user:id,id,is_notification_on')
                        ->where('user_id', $user)
                        ->whereHas('user', function ($q) {
                            $q->where('is_notification_on', 1);
                        })
                        ->get()
                        ->map(function ($fcm) {
                            return [
                                'fcm_id' => $fcm->fcm_id,
                                'is_notification_on' => $fcm->user?->is_notification_on,
                            ];
                        });
                    foreach ($results as $result) {
                        $fcm_ids[] = $result['fcm_id'];
                    }

                    $fcmMsg = array(
                        'title' => "$customer_title",
                        'body' => "$customer_msg",
                        'type' => "Discounted",
                        'store_id' => "$store_id",
                    );
                    $registrationIDs_chunks = array_chunk($fcm_ids, 1000);
                    app(FirebaseNotificationService::class)->sendNotification('', $registrationIDs_chunks, $fcmMsg);
                }
            } else {
                $response_data['error'] =  true;
                $response_data['message'] =
                    labels('admin_labels.discount_not_added', 'Discount not Added');
            }
        } else {
            $response_data['error'] =  true;
            $response_data['message'] =
                labels('admin_labels.orders_not_found', 'Orders Not Found');
        }

        return response()->json($response_data);
    }

    public function sendCartReminders()
    {
        $api_keys = [
            'gemini_api' => json_decode(app(SettingService::class)->getSettings('gemini_api_key', true), true)['gemini_api_key'] ?? null,
            'openrouter_api' => json_decode(app(SettingService::class)->getSettings('openrouter_api_key', true), true)['openrouter_api_key'] ?? null,
        ];
        $ai_settings = json_decode(app(SettingService::class)->getSettings('ai_settings', true), true);
        $ai_method = $ai_settings['ai_method'] ?? 'gemini_api';

        $cart = Cart::with(['user', 'productVariant.product'])
            ->leftJoin('cart_reminders', function ($join) {
                $join->on('cart.user_id', '=', 'cart_reminders.user_id')
                    ->on('cart.product_variant_id', '=', 'cart_reminders.product_variant_id');
            })
            ->whereNull('cart_reminders.reminded_at')
            ->select(
                'cart.user_id',
                'cart.store_id',
                'cart.product_variant_id',
                'users.username as user_name',
                'users.email',
                'users.is_notification_on',
                'products.name as product_name'
            )
            ->join('users', 'users.id', '=', 'cart.user_id')
            ->join('product_variants', 'product_variants.id', '=', 'cart.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->groupBy(
                'cart.user_id',
                'cart.product_variant_id',
                'users.username',
                'users.email',
                'users.is_notification_on',
                'products.name'
            )
            ->get();

        // dd($cart);
        foreach ($cart as $item) {
            // dd($item->store_id);
            if (!$item->is_notification_on) {
                continue;
            }

            $results = UserFcm::with('user:id,id,is_notification_on')
                ->where('user_id', $item->user_id)
                ->whereHas('user', function ($q) {
                    $q->where('is_notification_on', 1);
                })
                ->get()
                ->map(function ($fcm) {
                    return [
                        'fcm_id' => $fcm->fcm_id,
                        'is_notification_on' => $fcm->user?->is_notification_on,
                    ];
                });

            $fcm_ids = [];
            $productName = json_decode($item->product_name, true)['en'] ?? '';
            $message = $this->generateCartReminderMessage($item->user_name, $productName, $api_keys, $ai_method);
            $store_id = $item->store_id;
            $title = "Cart Reminder 🛒";
            $fcmMsg = [
                'title' => $title,
                'body' => $message,
                'type' => 'cart',
                'store_id' => "$store_id",
            ];

            foreach ($results as $result) {
                $fcm_ids[] = $result['fcm_id'];
            }

            $registrationIDs_chunks = array_chunk($fcm_ids, 1000);
            app(FirebaseNotificationService::class)->sendNotification('', $registrationIDs_chunks, $fcmMsg);

            CartReminder::updateOrCreate(
                [
                    'user_id' => $item->user_id,
                    'product_variant_id' => $item->product_variant_id,
                ],
                [
                    'reminded_at' => now(),
                ]
            );

            // Log::info("Reminder sent to {$item->user_name} ({$item->email}) for '{$productName}' | Message: {$message}");
        }

        return response()->json([
            'message' => 'Cart reminders sent successfully.',
            'notified_count' => $cart->count(),
        ]);
    }


    private function generateCartReminderMessage($userName, $productName, $api_keys, $ai_method = 'gemini_api')
    {
        $defaultMessage = "Hey {$userName}, your cart misses '{$productName}'! Complete your order before it’s gone!";

        try {
            $prompt = "Write a catchy, funny cart reminder under 20 words. The user {$userName} left '{$productName}' in the cart.";

            if ($ai_method === 'gemini_api' && !empty($api_keys['gemini_api'])) {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$api_keys['gemini_api']}", [
                    'contents' => [[
                        'parts' => [['text' => $prompt]]
                    ]]
                ]);

                if (!$response->successful()) {
                    return $defaultMessage;
                }

                $message = $response->json('candidates.0.content.parts.0.text') ?? null;
            } elseif ($ai_method === 'openrouter_api' && !empty($api_keys['openrouter_api'])) {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $api_keys['openrouter_api'],
                    'HTTP-Referer' => config('app.url'),
                ])->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => 'mistralai/mistral-7b-instruct',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 60,
                ]);

                if (!$response->successful()) {
                    return $defaultMessage;
                }

                $message = $response->json('choices.0.message.content') ?? null;
            } else {
                return $defaultMessage;
            }

            // Trim and fall back to default if message is empty or malformed
            return $message && is_string($message)
                ? $this->truncateMessage($message, 100)
                : $defaultMessage;
        } catch (\Throwable $e) {
            Log::error("AI generation failed: " . $e->getMessage());
            return $defaultMessage;
        }
    }


    private function truncateMessage($text, $maxLength = 100)
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
        if (strlen($text) <= $maxLength) return $text;

        // Cut at word boundary
        return substr($text, 0, strrpos(substr($text, 0, $maxLength), ' ')) . '...';
    }
}
