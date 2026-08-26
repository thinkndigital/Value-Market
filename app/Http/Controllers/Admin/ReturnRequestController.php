<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomMessage;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Store;
use App\Models\User;
use App\Models\UserFcm;
use App\Services\TranslationService;
use Illuminate\Http\Request;
use App\Traits\HandlesValidation;
use App\Services\FirebaseNotificationService;
use App\Services\ProductService;
use App\Services\SettingService;
use App\Services\CurrencyService;
use App\Services\OrderService;

class ReturnRequestController extends Controller
{
    use HandlesValidation;
    public function index()
    {
        $deliveryRes = User::where('role_id', 3)
            ->where('active', 1)
            ->get();
        return view('admin.pages.tables.return_request', compact('deliveryRes'));
    }

    public function list()
    {
        $limit = request()->input('limit', 10);
        $offset = request()->input('pagination_offset', 0);
        $sort = request()->input('sort', 'id');
        $order = request()->input('order', 'ASC');
        $search = trim(request()->input('search', ''));

        $query = ReturnRequest::with(['user', 'product', 'orderItem.store']);

        if (!empty($search)) {
            $query->whereHas('user', fn($q) => $q->where('username', 'like', "%$search%"))
                ->orWhereHas('product', fn($q) => $q->where('name', 'like', "%$search%"))
                ->orWhereHas('orderItem.store', fn($q) => $q->where('name', 'like', "%$search%"))
                ->orWhere('id', 'like', "%$search%")
                ->orWhereHas('orderItem', fn($q) => $q->where('order_id', 'like', "%$search%"));
        }

        $total = $query->count();

        $returnRequests = $query->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();

        $rows = [];
        $language_code = app(TranslationService::class)->getLanguageCode();

        foreach ($returnRequests as $row) {
           
            $delivery_boy_name = !empty($row->orderItem) ? $this->getUserName(userId: $row->orderItem->delivery_boy_id) : '';
            $statusLabels = [
                '0' => '<span class="badge bg-success">Pending</span>',
                '1' => '<span class="badge bg-primary">Approved</span>',
                '2' => '<span class="badge bg-danger">Rejected</span>',
                '8' => '<span class="badge bg-secondary">Return Pickedup</span>',
                '3' => '<span class="badge bg-success">Returned</span>',
            ];

            $rows[] = [
                'id' => $row->id,
                'user_id' => $row->user->id,
                'user_name' => $row->user->username,
                'order_id' => $row->orderItem->order_id,
                'order_item_id' => $row->orderItem->id,
                'delivery_boy_id' => $row->orderItem->delivery_boy_id . '|' . $delivery_boy_name,
                'product_name' => app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $row->product->id, $language_code),
                'store_name' => app(TranslationService::class)->getDynamicTranslation(Store::class, 'name', $row->orderItem->store->id, $language_code),
                'price' => app(CurrencyService::class)->formateCurrency(formatePriceDecimal($row->orderItem->price)),
                'discounted_price' => app(CurrencyService::class)->formateCurrency(formatePriceDecimal($row->orderItem->discounted_price)),
                'quantity' => $row->orderItem->quantity,
                'sub_total' => app(CurrencyService::class)->formateCurrency(formatePriceDecimal($row->orderItem->sub_total)),
                'status_digit' => $row->status,
                'status' => $statusLabels[$row->status] ?? 'N/A',
                'remarks' => $row->remarks,
                'operate' => '<div class="d-flex align-items-center">
                                <a class="dropdown-item single_action_button dropdown_menu_items edit_request edit_return_request data-id="' . (!empty($row->orderItem) ? $row->orderItem->id : '') . '"
                                   data-bs-target="#request_request_modal" data-bs-toggle="modal">
                                   <i class="bx bx-pencil mx-2"></i>
                                </a>
                              </div>',
            ];
        }

        return response()->json([
            "rows" => $rows,
            "total" => $total,
        ]);
    }


    private function getUserName($userId)
    {
        $user = User::find($userId);
        return $user ? $user->username : null;
    }
    public function update(Request $request)
    {
        $rules = [
            'return_request_id' => 'required|numeric',
            'status' => 'required|numeric',
            'order_item_id' => 'required|numeric',
        ];
        if ($request->filled('status') && $request->input('status') == '1') {
            $rules['deliver_by'] = 'required';
        }

        $messages = [
            'deliver_by.required' => 'Please select delivery boy.',
        ];

        if ($response = $this->HandlesValidation($request, $rules, $messages)) {
            return $response;
        } else {
            $status = $request['status'];

            $remarks = isset($request['update_remarks']) && !empty($request['update_remarks']) ? $request['update_remarks'] : null;
            $returnRequestId = $request['return_request_id'];
            $item_id = $request['order_item_id'];

            // Find the record by its ID
            $returnRequest = ReturnRequest::find($returnRequestId);

            if ($returnRequest) {


                if ($returnRequest->status == 3 && $request['status'] == 3) {
                    return response()->json([
                        'error' => true,
                        'error_message' => 'This Item Is Already Returned!'
                    ]);
                }
                if ($returnRequest->status == 1 && $request['status'] == 1) {
                    return response()->json([
                        'error' => true,
                        'error_message' => 'This Item Is Already Approved!'
                    ]);
                }
                if ($returnRequest->status == 2 && $request['status'] == 2) {
                    return response()->json([
                        'error' => true,
                        'error_message' => 'This Item Is Already Rejected!'
                    ]);
                }
                if ($returnRequest->status == 2 && $request['status'] == 1) {
                    return response()->json([
                        'error' => true,
                        'error_message' => 'You can not approve rejected return request!'
                    ]);
                }
                $returnRequest->status = $status;
                $returnRequest->remarks = $remarks;
                $returnRequest->save();
                $data = fetchDetails(OrderItems::class, ['id' => $request['order_item_id']], ['product_variant_id', 'quantity', 'user_id']);
                $order_item_res = fetchDetails(OrderItems::class, ['id' => $item_id], ['order_id', 'store_id']);
                $customer_id = $data[0]->user_id;
                $settings = app(SettingService::class)->getSettings('system_settings', true);
                $settings = json_decode($settings, true);
                $app_name = isset($settings['app_name']) && !empty($settings['app_name']) ? $settings['app_name'] : '';
                $customer_res = fetchDetails(User::class, ['id' => $customer_id], ['username', 'fcm_id']);
                $fcm_ids = array();

                if ($request['status'] == '3') {
                    app(OrderService::class)->process_refund($item_id, 'returned');
                    app(ProductService::class)->updateStock($data[0]->product_variant_id, $data[0]->quantity, 'plus');
                    app(OrderService::class)->update_order_item($item_id, 'returned', 1);

                    $custom_notification = fetchDetails(CustomMessage::class, ['type' => "customer_order_returned"], '*');
                    $customer_res[0]->username = isset($customer_res[0]->username) ? $customer_res[0]->username : '';
                    $hashtag_customer_name = '< customer_name >';
                    $hashtag_order_id = '< order_item_id >';
                    $hashtag_application_name = '< application_name >';
                    $string = !$custom_notification->isEmpty() ? json_encode($custom_notification[0]->message, JSON_UNESCAPED_UNICODE) : '';
                    $hashtag = html_entity_decode($string);
                    $data1 = str_replace(array($hashtag_customer_name, $hashtag_order_id, $hashtag_application_name), array($customer_res[0]->username, $order_item_res[0]->order_id, $app_name), $hashtag);
                    $message = outputEscaping(trim($data1, '"'));
                    $customer_msg = !$custom_notification->isEmpty() ? $message : 'Hello Dear ' . $customer_res[0]->username . ',your return request of order item id' . $item_id . ' has been declined';

                    $customer_result = UserFcm::with('user:id,id,is_notification_on')
                        ->where('user_id', $customer_id)
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

                    foreach ($customer_result as $result) {
                        $fcm_ids[] = $result['fcm_id'];
                    }
                    $store_id = $order_item_res[0]->store_id;
                    $order_status_title = !$custom_notification->isEmpty() ? $custom_notification[0]->title : "Order status updated";

                    $fcmMsg = array(
                        'title' => "$order_status_title",
                        'body' => "$customer_msg",
                        'type' => "order",
                        'store_id' => "$store_id",
                    );

                    $registrationIDs_chunks = array_chunk($fcm_ids, 1000);
                    app(FirebaseNotificationService::class)->sendNotification('', $registrationIDs_chunks, $fcmMsg);
                } elseif ($request['status'] == '1') {
                    $store_id = fetchDetails(OrderItems::class, ['id' => $item_id], 'store_id');
                    $store_id = isset($store_id) && !empty($store_id) ? $store_id[0]->store_id : "";
                    updateDetails(['delivery_boy_id' => $request['deliver_by']], ['id' => $item_id], OrderItems::class);
                    app(OrderService::class)->update_order_item($item_id, 'return_request_approved', 1);

                    //for delivery boy notification
                    $user_id = $request['deliver_by'];

                    $user_res = fetchDetails(User::class, ['id' => $user_id], ['username', 'fcm_id']);

                    //custom message

                    $custom_notification = fetchDetails(CustomMessage::class, ['type' => "customer_order_returned_request_approved"], '*');
                    $customer_res[0]->username = isset($customer_res[0]->username) ? $customer_res[0]->username : '';
                    $hashtag_customer_name = '< customer_name >';
                    $hashtag_order_id = '< order_item_id >';
                    $hashtag_application_name = '< application_name >';
                    $string = !$custom_notification->isEmpty() ? json_encode($custom_notification[0]->message, JSON_UNESCAPED_UNICODE) : '';
                    $hashtag = html_entity_decode($string);
                    $data1 = str_replace(array($hashtag_customer_name, $hashtag_order_id, $hashtag_application_name), array($customer_res[0]->username, $order_item_res[0]->order_id, $app_name), $hashtag);
                    $message = outputEscaping(trim($data1, '"'));
                    $delivery_boy_msg = 'Hello Dear ' . $user_res[0]->username . ' ' . 'you have new order to be pickup order ID #' . $order_item_res[0]->order_id . ' please take note of it! Thank you. Regards ' . $app_name . '';
                    $customer_msg = !$custom_notification->isEmpty() ? $message : 'Hello Dear ' . $customer_res[0]->username . ',your return request of order item id' . $item_id . ' is approved';
                    $title = !$custom_notification->isEmpty() ? $custom_notification[0]->title : "You have new order to deliver";

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
                    foreach ($results as $result) {
                        $fcm_ids[] = $result['fcm_id'];
                    }
                    $fcmMsg = array(
                        'title' => "$title",
                        'body' => "$delivery_boy_msg",
                        'type' => "order",
                        'store_id' => "$store_id",


                    );
                    $registrationIDs_chunks = array_chunk($fcm_ids, 1000);
                    app(FirebaseNotificationService::class)->sendNotification('', $registrationIDs_chunks, $fcmMsg);

                    $order_status_title = !$custom_notification->isEmpty() ? $custom_notification[0]->title : "Order status updated";

                    $results = UserFcm::with('user:id,id,is_notification_on')
                        ->where('user_id', $customer_id)
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
                        'title' => "$order_status_title",
                        'body' => "$customer_msg",
                        'type' => "order",
                        'store_id' => "$store_id",
                    );

                    $registrationIDs_chunks = array_chunk($fcm_ids, 1000);
                    app(FirebaseNotificationService::class)->sendNotification('', $registrationIDs_chunks, $fcmMsg);
                } elseif ($request['status'] == '2') {
                    $store_id = fetchDetails(OrderItems::class, ['id' => $item_id], 'store_id');

                    $store_id = isset($store_id) && !empty($store_id) ? $store_id[0]->store_id : "";
                    app(OrderService::class)->update_order_item($item_id, 'return_request_decline', 1);
                    //custom message
                    $custom_notification = fetchDetails(CustomMessage::class, ['type' => "customer_order_returned_request_decline"], '*');
                    $customer_res[0]->username = isset($customer_res[0]->username) ? $customer_res[0]->username : '';
                    $hashtag_customer_name = '< customer_name >';
                    $hashtag_order_id = '< order_item_id >';
                    $hashtag_application_name = '< application_name >';
                    $string = !$custom_notification->isEmpty() ? json_encode($custom_notification[0]->message, JSON_UNESCAPED_UNICODE) : '';
                    $hashtag = html_entity_decode($string);
                    $data1 = str_replace(array($hashtag_customer_name, $hashtag_order_id, $hashtag_application_name), array($customer_res[0]->username, $order_item_res[0]->order_id, $app_name), $hashtag);
                    $message = outputEscaping(trim($data1, '"'));
                    $customer_msg = !$custom_notification->isEmpty() ? $message : 'Hello Dear ' . $customer_res[0]->username . ',your return request of order item id' . $item_id . ' has been declined';

                    $results = UserFcm::with('user:id,id,is_notification_on')
                        ->where('user_id', $customer_id)
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
                    $order_status_title = !$custom_notification->isEmpty() ? $custom_notification[0]->title : "Order status updated";

                    $fcmMsg = array(
                        'title' => "$order_status_title",
                        'body' => "$customer_msg",
                        'type' => "order",
                        'store_id' => "$store_id",
                    );

                    $registrationIDs_chunks = array_chunk($fcm_ids, 1000);
                    app(FirebaseNotificationService::class)->sendNotification('', $registrationIDs_chunks, $fcmMsg);
                }
            }
            $response['error'] = false;
            $response['message'] =
                labels('admin_labels.return_request_updated_successfully', 'Return request updated successfully');
            return response()->json($response);
        }
    }
}
