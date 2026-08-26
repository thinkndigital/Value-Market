<?php

namespace App\Http\Controllers\Delivery_boy;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Currency;
use App\Models\CustomMessage;
use App\Models\Order;
use App\Models\OrderBankTransfers;
use App\Models\OrderCharges;
use App\Models\OrderItems;
use App\Models\Parcel;
use App\Models\Parcelitem;
use App\Models\Seller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserFcm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\FirebaseNotificationService;
use App\Services\ProductService;
use Illuminate\Validation\Rule;
use App\Traits\HandlesValidation;
use App\Services\TranslationService;
use App\Services\MediaService;
use App\Services\ParcelService;
use App\Services\SettingService;
use App\Services\OrderService;
class OrderController extends Controller
{
    use HandlesValidation;
    public function index()
    {
        $currencyDetails = fetchDetails(Currency::class, ['is_default' => 1], 'symbol');
        $currency = !$currencyDetails->isEmpty() ? $currencyDetails[0]->symbol : '';
        return view('delivery_boy.pages.tables.manage_orders', compact('currency'));
    }


    public function view_parcels(Request $request, $orderId = null, $sellerId = null, $deliveryBoyId = null)
    {
        $language_code = app(TranslationService::class)->getLanguageCode();
        $delivery_boy_id = auth::id();
        $parcelData = app(ParcelService::class)->ViewParcel($request, '', '', $delivery_boy_id, $language_code);
        // dd($parcelData);
        return response()->json($parcelData);
    }
    public function edit(Request $request, $id)
    {

        $parcel_id = $request->parcel_id ?? "";

        $parcel_details = fetchDetails(Parcel::class, ['id' => $parcel_id], ['store_id']);

        $store_id = isset($parcel_details) && !empty($parcel_details) ? $parcel_details[0]->store_id : "";

        $limit = 25;
        $offset = 0;
        $order = 'DESC';
        $delivery_boy_id = auth::id();

        $parcel_details = app(ParcelService::class)->viewAllParcels('', $parcel_id, '', $offset, $limit, $order, 1, '', '', $store_id);

        // dd($parcel_details);
        if (isset($parcel_details->original) && empty($parcel_details->original['data'])) {
            $response['error'] = true;
            $response['message'] = "Parcel Not Found.";
            $response['data'] = [];
            return response()->json($response);
        }


        if (!empty($parcel_details->original)) {

            $parcel_items = $parcel_details->original['data'];

            $order_items_id = [];

            foreach ($parcel_items as $item) {
                $order_items_id = [
                    ...$order_items_id,
                    ...array_map(function ($items) {
                        return ($items["order_item_id"]);
                    }, $item["items"])
                ];
            }


            $order_items = app(OrderService::class)->fetchOrderItems($order_items_id, null, null, null, null, null, 'oi.id', $order, null, null, null, null, $id, $store_id);


            if (isset($order_items['order_data']) && empty($order_items['order_data'])) {
                $response['error'] = true;
                $response['message'] = "Order items Not Found.";
                $response['data'] = [];
                return response()->json($response);
            }
            $order_items = $order_items['order_data'];

            if ($delivery_boy_id == $order_items[0]->delivery_boy_id && isset($id) && !empty($id) && !empty($parcel_items) && is_numeric($id)) {
                $items = [];
                $total = 0;
                foreach ($order_items as $row) {

                    $multipleWhere = ['seller_id' => $row->seller_id, 'order_id' => $row->order_id];
                    $orderChargeData = OrderCharges::where($multipleWhere)->get();
                    $updated_username = isset($row->updated_by) && !empty($row->updated_by) && $row->updated_by != 0 ? fetchDetails(User::class, ['id' => $row->updated_by], 'username')[0]->username : '';
                    $address_number = isset($row->address_id) && !empty($row->address_id) && $row->address_id != 0
                        ? (fetchDetails(Address::class, ['id' => $row->address_id], 'mobile') && isset(fetchDetails(Address::class, ['id' => $row->address_id], 'mobile')[0]->mobile)
                            ? fetchDetails(Address::class, ['id' => $row->address_id], 'mobile')[0]->mobile
                            : '')
                        : '';
                    $address = isset($row->address_id) && !empty($row->address_id) && $row->address_id != 0
                        ? (fetchDetails(Address::class, ['id' => $row->address_id], '*') && isset(fetchDetails(Address::class, ['id' => $row->address_id], 'address')[0]->address)
                            ? fetchDetails(Address::class, ['id' => $row->address_id], '*')
                            : '')
                        : '';
                    if ($address) {
                        $addressDetails = $address[0];
                        $fullAddress = trim(
                            (isset($addressDetails->name) ? $addressDetails->name : '') . ', ' .
                                (isset($addressDetails->mobile) ? $addressDetails->mobile : '') . ', ' .
                                (isset($addressDetails->address) ? $addressDetails->address : '') . ', ' .
                                (isset($addressDetails->area) ? $addressDetails->area : '') . ', ' .
                                (isset($addressDetails->city) ? $addressDetails->city : '') . ', ' .
                                (isset($addressDetails->state) ? $addressDetails->state : '') . ' - ' .
                                (isset($addressDetails->pincode) ? $addressDetails->pincode : '')
                        );
                    }
                    $deliver_by = isset($row->delivery_boy_id) && !empty($row->delivery_boy_id) && $row->delivery_boy_id != 0 ? fetchDetails(User::class, ['id' => $row->delivery_boy_id], 'username')[0]->username : '';
                    $temp = [
                        'id' => $row->id,
                        'item_otp' => $row->otp,
                        'product_id' => $row->product_id,
                        'product_variant_id' => $row->product_variant_id,
                        'product_type' => $row->product_type,
                        'wallet_balance' => $row->wallet_balance,
                        'pname' => isset($row->pname) && ($row->pname != null) ? $row->pname : $row->product_name,
                        'quantity' => $row->quantity,
                        'is_cancelable' => $row->is_cancelable,
                        'is_attachment_required' => $row->is_attachment_required,
                        'is_returnable' => $row->is_returnable,
                        'tax_amount' => $row->tax_amount,
                        'discounted_price' => $row->discounted_price,
                        'price' => $row->price,
                        'item_subtotal' => $row->sub_total,
                        'updated_by' => $updated_username,
                        'deliver_by' => $deliver_by,
                        'seller_delivery_charge' => $orderChargeData->isEmpty() ? 0 : $orderChargeData->first()->delivery_charge,
                        'seller_promo_discount' => $orderChargeData->isEmpty() ? 0 : $orderChargeData->first()->promo_discount,
                        'active_status' => $row->active_status,
                        'product_image' => $row->image,
                        'product_variants' => app(ProductService::class)->getVariantsValuesById($row->product_variant_id),
                        'pickup_location' => $row->pickup_location,
                        'seller_otp' => $orderChargeData->isEmpty() ? 0 : $orderChargeData[0]->otp,
                        'is_sent' => $row->is_sent,
                        'seller_id' => $row->seller_id,
                        'download_allowed' => $row->download_allowed,
                        'product_slug' => $row->product_slug,
                        'sku' => isset($row->product_sku) && !empty($row->product_sku) ? $row->product_sku : $row->sku,
                        'address_number' => $address_number,
                    ];

                    array_push($items, $temp);
                    $total += $row->sub_total;
                    if ($total > 0 && $order_items[0]->subtotal_of_order_items > 0) {
                        $total_discount_percentage = app(OrderService::class)->calculatePercentage($total, $order_items[0]->subtotal_of_order_items);
                    }
                    $total_order_items = OrderItems::where('order_id', $order_items[0]->order_id)
                        ->distinct()
                        ->count('id');


                    $res['data']['id'] = $order_items[0]->id;
                    $res['data']['order_id'] = $order_items[0]->order_id;
                    $res['data']['parcel_id'] = $parcel_id;
                    $res['data']['delivery_charge'] = $orderChargeData->isEmpty() ? 0 : $orderChargeData->first()->delivery_charge;
                    $res['data']['seller_promo_discount'] = $orderChargeData->isEmpty() ? 0 : $orderChargeData->first()->promo_discount;
                    $res['data']['delivery_boy_name'] = $order_items[0]->username;
                    $res['data']['delivery_boy_mobile'] = $order_items[0]->mobile;
                    $res['data']['delivery_boy_email'] = $order_items[0]->email;
                    $res['data']['notes'] = $order_items[0]->notes;
                    $res['data']['payment_method'] = $order_items[0]->payment_method;
                    $res['data']['address'] = $fullAddress;
                    $res['data']['total_promo_discount'] = $order_items[0]->promo_discount;
                    $res['data']['username'] = $order_items[0]->username;
                    $res['data']['wallet_balance'] = $order_items[0]->wallet_balance;
                    $res['data']['total_payable'] = $order_items[0]->total_payable;
                    $res['data']['order_total'] = $order_items[0]->total;
                    $res['data']['final_total'] = $order_items[0]->final_total;
                    $res['data']['delivery_boy_id'] = $order_items[0]->delivery_boy_id;
                    $res['data']['created_at'] = $order_items[0]->created_at;
                    $res['data']['delivery_date'] = $order_items[0]->delivery_date;
                    $res['data']['delivery_time'] = $order_items[0]->delivery_time;
                    $res['data']['is_cod_collected'] = $order_items[0]->is_cod_collected;
                    $res['data']['seller_id'] = $order_items[0]->seller_id;
                    $res['data']['promo_discount'] = $order_items[0]->promo_discount;
                    $order_detls = $res['data'];
                }
            }

            $seller = [];
            $sellers_id = collect($res)->pluck('seller_id')->unique()->values()->all();

            foreach ($sellers_id as $id) {
                // Get the seller with the related store data
                $seller_data = Seller::with([
                    'stores' => function ($query) {
                        $query->select('store_name', 'logo', 'user_id', 'seller_id');
                    },
                    'stores.user' => function ($query) {
                        $query->select('id', 'mobile', 'email', 'city', 'username', 'pincode');
                    }
                ])->find($id);

                if ($seller_data) {
                    // For each seller, get data only once
                    $seller_info = [
                        'id' => $id,
                        'user_id' => null,
                        'store_name' => null,
                        'seller_name' => null,
                        'seller_email' => null,
                        'shop_logo' => null,
                        'seller_mobile' => null,
                        'seller_pincode' => null,
                        'seller_city' => null
                    ];

                    // For each store related to the seller
                    foreach ($seller_data->stores as $store) {
                        // Assign values only once for the seller (first store found)
                        if ($seller_info['user_id'] === null) {
                            $seller_info['user_id'] = $store->pivot->user_id ?? '';
                            $seller_info['store_name'] = $store->pivot->store_name ?? '';
                            $seller_info['seller_name'] = $store->user->username ?? '';
                            $seller_info['seller_email'] = $store->user->email ?? '';
                            $seller_info['shop_logo'] = $store->pivot->logo ? app(MediaService::class)->getMediaImageUrl($store->pivot->logo, 'SELLER_IMG_PATH') : '';
                            $seller_info['seller_mobile'] = $store->user->mobile ?? '';
                            $seller_info['seller_pincode'] = $store->user->pincode ?? '';
                            $seller_info['seller_city'] = $store->user->city ?? '';
                        }
                    }

                    // Push the seller info once
                    array_push($seller, $seller_info);
                }
            }


            $sellers = $seller;

            $settings = app(SettingService::class)->getSettings('system_settings', true);
            $settings = json_decode($settings, true);
            $currencyDetails = fetchDetails(Currency::class, ['is_default' => 1], 'symbol');
            $currency = !$currencyDetails->isEmpty() ? $currencyDetails[0]->symbol : '';
            $mobile_data = fetchDetails(Address::class, ['id' => $order_items[0]->address_id], 'mobile');
            return view('delivery_boy.pages.forms.edit_orders', compact('order_detls', 'items', 'settings', 'sellers', 'currency', 'mobile_data'));
        }
    }
    public function update_order_item_status(Request $request)
    {

        $rules = [
            'id' => 'required',
            'otp' => 'nullable|numeric',
            'status' => [
                'required',
                Rule::in(['received', 'processed', 'shipped', 'delivered', 'cancelled', 'returned']),
            ],
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }
        $parcel_id =  $request->input('id') ?? "";
        $parcel = fetchDetails(Parcel::class, ['id' => $parcel_id], '*');

        $res = app(OrderService::class)->validateOrderStatus($parcel_id, $request['status'], 'parcels', '', '', $parcel[0]->type);

        if ($res['error']) {
            $response['error'] = true;
            $response['message'] = $res['message'];
            $response['data'] = array();
            return response()->json($response);
        }
        $parcel_items = fetchDetails(ParcelItem::class, ['parcel_id' => $parcel[0]->id], '*');
        if ($parcel->isEmpty() && !$parcel_items->isEmpty()) {
            $response = [
                'error' => true,
                'message' => 'Parcel Not Found',
            ];
            return response()->json($response);
        }
        $order_item_ids = $parcel_items->pluck('order_item_id')->all();
        $order_id = $parcel[0]->order_id;


        $orderItemRes = OrderItems::query()
            ->select('order_items.*')
            ->addSelect([
                'order_item_id' => OrderItems::select('id')->whereColumn('id', 'order_items.id')->limit(1),

                'order_counter' => OrderItems::selectRaw('count(*)')
                    ->whereColumn('order_id', 'order_items.order_id'),

                'order_cancel_counter' => OrderItems::selectRaw('count(*)')
                    ->where('active_status', 'cancelled')
                    ->whereColumn('order_id', 'order_items.order_id'),

                'order_return_counter' => OrderItems::selectRaw('count(*)')
                    ->where('active_status', 'returned')
                    ->whereColumn('order_id', 'order_items.order_id'),

                'order_delivered_counter' => OrderItems::selectRaw('count(*)')
                    ->where('active_status', 'delivered')
                    ->whereColumn('order_id', 'order_items.order_id'),

                'order_processed_counter' => OrderItems::selectRaw('count(*)')
                    ->where('active_status', 'processed')
                    ->whereColumn('order_id', 'order_items.order_id'),

                'order_shipped_counter' => OrderItems::selectRaw('count(*)')
                    ->where('active_status', 'shipped')
                    ->whereColumn('order_id', 'order_items.order_id'),

                'order_status' => Order::select('status')
                    ->whereColumn('id', 'order_items.order_id')
                    ->limit(1),
            ])
            ->whereIn('id', $order_item_ids)
            ->get();

        if (request('status') == 'delivered') {
            $settings = app(SettingService::class)->getSettings('system_settings', true);
            $settings = json_decode($settings, true);

            if ($settings['order_delivery_otp_system'] == 1) {

                $rules = [
                    'otp' => 'required|numeric',
                ];

                if ($response = $this->HandlesValidation($request, $rules)) {
                    return $response;
                }

                if (!validateOtp(request('otp'), $orderItemRes[0]->order_item_id, $order_id, $orderItemRes[0]->seller_id, $parcel_id)) {
                    return response()->json([
                        'error' => true,
                        'message' =>
                        labels('admin_labels.invalid_otp_supplied', 'Invalid OTP supplied!'),
                        'data' => [],
                    ]);
                }
            }
        }

        $order_method = fetchDetails(Order::class, ['id' => $order_id], ['store_id', 'payment_method']);
        $store_id = $order_method[0]->store_id;
        if ($order_method[0]->payment_method == 'bank_transfer') {
            $bank_receipt = fetchDetails(OrderBankTransfers::class, ['order_id' => $order_id]);
            $transaction_status = fetchDetails(Transaction::class, ['order_id' => $order_id], 'status');
            if (empty($bank_receipt) || strtolower($transaction_status[0]->status) != 'success') {
                $response['error'] = true;
                $response['message'] =
                    labels('admin_labels.order_status_cannot_update_bank_verification_remains', "Order Status can not update, Bank verification is remain from transactions.");
                $response['data'] = array();
                return response()->json($response);
            }
        }
        if (app(OrderService::class)->updateOrder(['status' => $request->input('status')], ['id' => $parcel_id], true, "parcels", false, 0, Parcel::class)) {

            app(OrderService::class)->updateOrder(['active_status' => $request->input('status')], ['id' => $parcel_id], false, "parcels", false, 0, Parcel::class);
            foreach ($parcel_items as $item) {
                app(OrderService::class)->updateOrder(['status' => $request->input('status')], ['id' => $item->order_item_id], true, 'order_items', false, 0, OrderItems::class);
                app(OrderService::class)->updateOrder(['active_status' => $request->input('status')], ['id' => $item->order_item_id], false, 'order_items', false, 0, OrderItems::class);
                updateDetails(['updated_by' => auth()->id()], ['id' => $item->order_item_id], OrderItems::class);
            }
            if (($orderItemRes[0]->order_counter == intval($orderItemRes[0]->order_cancel_counter) + 1 && $request['status'] == 'cancelled') ||  ($orderItemRes[0]->order_counter == intval($orderItemRes[0]->order_return_counter) + 1 && $request['status'] == 'returned') || ($orderItemRes[0]->order_counter == intval($orderItemRes[0]->order_delivered_counter) + 1 && $request['status'] == 'delivered') || ($orderItemRes[0]->order_counter == intval($orderItemRes[0]->order_processed_counter) + 1 && $request['status'] == 'processed') || ($orderItemRes[0]->order_counter == intval($orderItemRes[0]->order_shipped_counter) + 1 && $request['status'] == 'shipped')) {
                /* process the refer and earn */
                $user = fetchDetails(Order::class, ['id' => $order_id], 'user_id');
                $user_id = $user[0]->user_id;

                $settings = app(SettingService::class)->getSettings('system_settings', true);
                $settings = json_decode($settings, true);
                $app_name = isset($settings['app_name']) && !empty($settings['app_name']) ? $settings['app_name'] : '';
                $user_res = fetchDetails(User::class, ['id' => $user_id], ['username', 'fcm_id', 'mobile', 'email']);
                //custom message
                if ($request->input('status') == 'received') {
                    $type = ['type' => "customer_order_received"];
                } elseif ($request->input('status') == 'processed') {
                    $type = ['type' => "customer_order_processed"];
                } elseif ($request->input('status') == 'shipped') {
                    $type = ['type' => "customer_order_shipped"];
                } elseif ($request->input('status') == 'delivered') {
                    $type = ['type' => "customer_order_delivered"];
                } elseif ($request->input('status') == 'cancelled') {
                    $type = ['type' => "customer_order_cancelled"];
                } elseif ($request->input('status') == 'returned') {
                    $type = ['type' => "customer_order_returned"];
                }

                $custom_notification = fetchDetails(CustomMessage::class, $type, '*');
                $hashtag_customer_name = '< customer_name >';
                $hashtag_order_id = '< order_item_id >';
                $hashtag_application_name = '< application_name >';
                $string = !$custom_notification->isEmpty() ? json_encode($custom_notification[0]->message, JSON_UNESCAPED_UNICODE) : '';
                $hashtag = html_entity_decode($string);
                $data = str_replace(array($hashtag_customer_name, $hashtag_order_id, $hashtag_application_name), array($user_res[0]->username, $order_id, $app_name), $hashtag);
                $message = outputEscaping(trim($data, '"'));
                $customer_msg = !$custom_notification->isEmpty() ? $message :  'Hello Dear ' . $user_res[0]->username . 'Order status updated to' . $request['status'] . ' for your order ID #' . $order_id . ' please take note of it! Thank you for shopping with us. Regards ' . $app_name . '';

                $results = UserFcm::with('user:id,id,is_notification_on')
                    ->where('user_id', $user_id)
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
                $fcm_ids = array();
                foreach ($results as $result) {
                    $fcm_ids[] = $result['fcm_id'];
                }

                $title = !$custom_notification->isEmpty() ? $custom_notification[0]->title : "Order status updated";
                $fcmMsg = array(
                    'title' => "$title",
                    'body' => "$customer_msg",
                    'type' => "order",
                    'store_id' => "$store_id",
                );
                $registrationIDs_chunks = array_chunk($fcm_ids, 1000);
                app(FirebaseNotificationService::class)->sendNotification('', $registrationIDs_chunks, $fcmMsg);
            }
            //custom message
            // if ($request->input('status') == 'received') {
            //     $type = ['type' => "customer_order_received"];
            // } elseif ($request->input('status') == 'processed') {
            //     $type = ['type' => "customer_order_processed"];
            // } elseif ($request->input('status') == 'shipped') {
            //     $type = ['type' => "customer_order_shipped"];
            // } elseif ($request->input('status') == 'delivered') {
            //     $type = ['type' => "customer_order_delivered"];
            // } elseif ($request->input('status') == 'cancelled') {
            //     $type = ['type' => "customer_order_cancelled"];
            // } elseif ($request->input('status') == 'returned') {
            //     $type = ['type' => "customer_order_returned"];
            // }
            // $custom_notification = fetchDetails(CustomMessage::class, where: $type);
            // $hashtag_customer_name = '< customer_name >';
            // $hashtag_order_id = '< order_item_id >';
            // $hashtag_application_name = '< application_name >';
            // $string = !$custom_notification->isEmpty() ? json_encode($custom_notification[0]->message, JSON_UNESCAPED_UNICODE) : '';
            // $hashtag = html_entity_decode($string);
            // $data = str_replace(array($hashtag_customer_name, $hashtag_order_id, $hashtag_application_name), array($user_res[0]->username, $order_id, $app_name), $hashtag);
            // $message = outputEscaping(trim($data, '"'));
            // $customer_msg = (!$custom_notification->isEmpty()) ? $message : 'Hello Dear ' . $user_res[0]->username . 'Order status updated to ' . $request['status'] . ' for your order ID #' . $order_id . ' please take note of it! Thank you for shopping with us. Regards ' . $app_name . '';

            // $results = UserFcm::with('user:id,id,is_notification_on')
            //     ->where('user_id', $user_id)
            //     ->whereHas('user', function ($q) {
            //         $q->where('is_notification_on', 1);
            //     })
            //     ->get()
            //     ->map(function ($fcm) {
            //         return [
            //             'fcm_id' => $fcm->fcm_id,
            //             'is_notification_on' => $fcm->user?->is_notification_on,
            //         ];
            //     });
            // $fcm_ids = array();
            // foreach ($results as $result) {
            //     $fcm_ids[] = $result['fcm_id'];
            // }

            // $title = (!empty($custom_notification)) ? $custom_notification[0]->title : "Order status updated";
            // $fcmMsg = array(
            //     'title' => "$title",
            //     'body' => "$customer_msg",
            //     'type' => "order",
            //     'store_id' => "$store_id",
            // );
            // $registrationIDs_chunks = array_chunk($fcm_ids, 1000);
            // app(FirebaseNotificationService::class)->sendNotification('', $registrationIDs_chunks, $fcmMsg);
           
            $response['error'] = false;
            $response['message'] = labels('admin_labels.status_updated_successfully', 'Status updated successfully.');
            $response['data'] = array();
            return response()->json($response);
        }
    }
    public function returned_orders()
    {
        return view('delivery_boy.pages.tables.returned_orders');
    }
    public function returned_orders_list(Request $request)
    {
        $delivery_boy_id = Auth::id();
        $response = app(OrderService::class)->getReturnOrderItemsList(
            $delivery_boy_id,
            $request->input('search', ''),
            $request->input('offset', 0),
            $request->input('limit', 10),
            $request->input('sort', 'id'),
            $request->input('order', 'DESC'),
            $request->input('seller_id'),
            $request->input('fromApp', '0'),
            $request->input('order_item_id', ''),
            $request->input('isPrint', '0'),
            $request->input('order_status', ''),
            $request->input('payment_method', '')
        );
        return $response;
    }
    public function edit_returned_orders($order_id, $order_item_id)
    {
        $store_id = fetchDetails(OrderItems::class, ['id' => $order_item_id], 'store_id');
        $store_id = isset($store_id) && !empty($store_id) ? $store_id[0]->store_id : "";
        $delivery_boy_id = Auth::id();

        $res = app(OrderService::class)->fetchOrderItems($order_item_id, '', '', $delivery_boy_id, '', '', 'id', 'DESC', '', '', '', '', '', $store_id);
        if (!empty($res['order_data'])) {
            $items = [];
            foreach ($res['order_data'] as $row) {
                if ($delivery_boy_id == $row->delivery_boy_id) {
                    $multipleWhere = ['seller_id' => $row->seller_id, 'order_id' => $row->id];
                    $orderChargeData = OrderCharges::where($multipleWhere)->get();
                    $updated_username = isset($row->updated_by) && !empty($row->updated_by) && $row->updated_by != 0 ? fetchDetails(User::class, ['id' => $row->updated_by], 'username')[0]->username : '';
                    $temp = [
                        'id' => $row->id,
                        'item_otp' => $row->otp,
                        'product_id' => $row->product_id,
                        'product_variant_id' => $row->product_variant_id,
                        'product_type' => $row->product_type,
                        'wallet_balance' => $row->wallet_balance,
                        'pname' => isset($row->pname) && ($row->pname != null) ? $row->pname : $row->product_name,
                        'quantity' => $row->quantity,
                        'is_cancelable' => $row->is_cancelable,
                        'is_attachment_required' => $row->is_attachment_required,
                        'is_returnable' => $row->is_returnable,
                        'tax_amount' => $row->tax_amount,
                        'discounted_price' => $row->discounted_price,
                        'price' => $row->price,
                        'item_subtotal' => $row->sub_total,
                        'updated_by' => $updated_username,
                        'seller_delivery_charge' => $orderChargeData->isEmpty() ? 0 : $orderChargeData->first()->delivery_charge,
                        'seller_promo_discount' => $orderChargeData->isEmpty() ? 0 : $orderChargeData->first()->promo_discount,
                        'active_status' => $row->active_status,
                        'product_image' => $row->image,
                        'product_variants' => app(ProductService::class)->getVariantsValuesById($row->product_variant_id),
                        'pickup_location' => $row->pickup_location,
                        'seller_otp' => $orderChargeData->isEmpty() ? 0 : $orderChargeData[0]->otp,
                        'is_sent' => $row->is_sent,
                        'seller_id' => $row->seller_id,
                        'download_allowed' => $row->download_allowed,
                        'product_slug' => $row->product_slug,
                        'sku' => isset($row->product_sku) && !empty($row->product_sku) ? $row->product_sku : $row->sku,
                    ];
                    array_push($items, $temp);
                }
            }

            $seller = [];
            $sellers_id = collect($res['order_data'])->pluck('seller_id')->unique()->values()->all();
            foreach ($sellers_id as $id) {
                // Get the seller with the related store data
                $seller_data = Seller::with([
                    'stores' => function ($query) {
                        $query->select('store_name', 'logo', 'user_id', 'seller_id');
                    },
                    'stores.user' => function ($query) {
                        $query->select('id', 'mobile', 'email', 'city', 'username', 'pincode');
                    }
                ])->find($id);

                if ($seller_data) {
                    // For each seller, get data only once
                    $seller_info = [
                        'id' => $id,
                        'user_id' => null,
                        'store_name' => null,
                        'seller_name' => null,
                        'seller_email' => null,
                        'shop_logo' => null,
                        'seller_mobile' => null,
                        'seller_pincode' => null,
                        'seller_city' => null
                    ];

                    // For each store related to the seller
                    foreach ($seller_data->stores as $store) {
                        // Assign values only once for the seller (first store found)
                        if ($seller_info['user_id'] === null) {
                            $seller_info['user_id'] = $store->pivot->user_id ?? '';
                            $seller_info['store_name'] = $store->pivot->store_name ?? '';
                            $seller_info['seller_name'] = $store->user->username ?? '';
                            $seller_info['seller_email'] = $store->user->email ?? '';
                            $seller_info['shop_logo'] = $store->pivot->logo ? app(MediaService::class)->getMediaImageUrl($store->pivot->logo, 'SELLER_IMG_PATH') : '';
                            $seller_info['seller_mobile'] = $store->user->mobile ?? '';
                            $seller_info['seller_pincode'] = $store->user->pincode ?? '';
                            $seller_info['seller_city'] = $store->user->city ?? '';
                        }
                    }

                    // Push the seller info once
                    array_push($seller, $seller_info);
                }
            }
            $sellers = $seller;
            $order_details = $res['order_data'][0];

            $settings = app(SettingService::class)->getSettings('system_settings', true);
            $settings = json_decode($settings, true);
            $currencyDetails = fetchDetails(Currency::class, ['is_default' => 1], 'symbol');
            $currency = !$currencyDetails->isEmpty() ? $currencyDetails[0]->symbol : '';
            $mobile_data = fetchDetails(Address::class, ['id' => $res['order_data'][0]->address_id], 'mobile');
            $address = isset($row->address_id) && !empty($row->address_id) && $row->address_id != 0
                ? (fetchDetails(Address::class, ['id' => $row->address_id], 'address') && isset(fetchDetails(Address::class, ['id' => $row->address_id], 'address')[0]->address)
                    ? fetchDetails(Address::class, ['id' => $row->address_id], 'address')[0]->address
                    : '')
                : '';
        }
        return view('delivery_boy.pages.forms.edit_returned_orders', compact('order_details', 'items', 'settings', 'currency', 'address', 'mobile_data', 'sellers'));
    }
    public function update_return_order_item_status(Request $request)
    {
        $order_item_id = $request->order_item_id ?? "";
        $status = $request->status ?? "";
        if ($status !== 'return_pickedup') {
            return response()->json([
                'error' => true,
                'message' =>
                labels('admin_labels.invalid_status_passed', 'Invalid Status Passed.'),
                'data' => [],
            ]);
        }
        $current_status = fetchDetails(OrderItems::class, ['id' => $order_item_id], 'status');
        $current_status = isset($current_status) && !empty($current_status) ? $current_status[0]->status : "";
        $current_status = json_decode($current_status, true);
        if (!is_array($current_status)) {
            $current_status = [];
        }
        $last_status = end($current_status);
        if ($last_status[0] == 'returned') {
            return response()->json([
                'error' => true,
                'message' =>
                labels('admin_labels.status_is_already_returned_you_can_not_set_it_as_pickedup', 'Status is already returned you can not set it as pickedup.'),
                'data' => [],
            ]);
        }
        if ($last_status[0] == 'return_pickedup') {
            return response()->json([
                'error' => true,
                'message' =>
                labels('admin_labels.status_already_updated', 'Status already updated.'),
                'data' => [],
            ]);
        }
        $current_time = date("Y-m-d H:i:s");
        $new_entry = [$status, $current_time];
        $current_status[] = $new_entry;
        $updated_status = json_encode($current_status);
        $update_data = [
            'active_status' => $status,
            'status' => $updated_status
        ];
        $result = app(OrderService::class)->updateOrderItemStatus($order_item_id, $update_data);
        if ($result) {
            return response()->json([
                'error' => false,
                'message' =>
                labels('admin_labels.status_updated_successfully', 'Status Updated Successfully'),
                'data' => $result,
            ]);
        } else {
            return response()->json([
                'error' => true,
                'message' =>
                labels('admin_labels.something_went_wrong', 'Something went wrong'),
                'data' => [],
            ]);
        }
    }
}
