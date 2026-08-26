<?php

namespace App\Services;
use App\Models\OrderTracking;
use App\Models\Parcel;
use App\Models\Parcelitem;
use App\Models\OrderItems;
use App\Models\Order;
use App\Models\Product;
use App\Models\user;
use App\Models\SellerStore;
use Carbon\Carbon;
use App\Services\TranslationService;
use App\Services\MediaService;
use Illuminate\Support\Facades\DB;
class ParcelService
{
    public function createParcel($request)
    {

        $parcel_title = $request->parcel_title;
        $parcel_order_type = $request->parcel_order_type;
        $order_item_ids = $request->selected_items;
        $order_id = $request->order_id;
        $product_variant_ids = [];
        $items = fetchDetails(OrderItems::class, ['order_id' => $order_id], ['active_status', 'id', 'order_id', 'product_variant_id']);
        foreach ($items as $item) {
            foreach ($order_item_ids as $order_item_id) {
                if ($order_item_id == $item->id) {
                    if (isExist(['order_item_id' => $item->id], ParcelItem::class)) {
                        return [
                            "error" => true,
                            "message" => 'Parcel is Already Created!',
                        ];
                    }
                    array_push($product_variant_ids, $item->product_variant_id);
                    if ($item->active_status == 'draft' || $item->active_status == 'awaiting') {
                        return [
                            "error" => true,
                            "message" => 'You can\'t ship order Right now Because Order is In Awaiting State, Payment verification is not Done Yet!',

                        ];
                    }
                    if ($item->active_status == 'cancelled' || $item->active_status == 'delivered') {
                        return [
                            "error" => true,
                            "message" => 'You can\'t ship Order Because Order is ' . $item->active_status,
                        ];
                    }
                }
            }
        }
        $orders = fetchDetails(Order::class, ['id' => $order_id], ['delivery_charge', 'store_id']);

        if (empty($orders)) {
            return [
                "error" => true,
                "message" => 'Order Not Found',
            ];
        }

        $status = "processed";

        $orders_delivery_charges = $orders[0]->delivery_charge;
        $store_id = $orders[0]->store_id;
        $parcels = fetchDetails(Parcel::class, ['order_id' => $order_id], 'delivery_charge');
        $flag = false;
        $delivery_charge = "0";
        foreach ($parcels as $parcel) {
            if ($parcel->delivery_charge == $orders_delivery_charges) {
                $flag = true;
                break;
            }
        }
        if ($flag == false) {
            $delivery_charge = $orders_delivery_charges;
        }
        $otp = random_int(100000, 999999);

        if (isset($parcel_title) && !empty($parcel_title)) {
            $parcel = [
                'name' => $parcel_title,
                'type' => $parcel_order_type,
                'order_id' => $order_id,
                'store_id' => $store_id,
                'otp' => $otp,
                'delivery_charge' => $delivery_charge,
                'active_status' => $status,
                'status' => json_encode([["received", date("Y-m-d") . " " . date("h:i:sa")], ["processed", date("Y-m-d") . " " . date("h:i:sa")]]),
            ];
        } else {
            return [
                "error" => true,
                "message" => 'Please Enter Parcel Title',

            ];
        }
        if (isset($product_variant_ids) && empty($product_variant_ids)) {
            return [
                "error" => true,
                "message" => 'Product Variant Id not found',
            ];
        }
        $product_variant_id = is_string($product_variant_ids) ? explode(",", $product_variant_ids) : $product_variant_ids;
        $order_items_data = OrderItems::select(["product_variant_id", "quantity", "delivered_quantity", "id", "order_id", 'price'])->whereIn("product_variant_id", $product_variant_id)->where("order_id", $order_id)->get()->toArray();

        $parcel = Parcel::create($parcel);
        $parcel_id = $parcel->id;
        $parcel_data = [];
        $response = [];

        foreach ($order_items_data as $row) {
            $unit_price = $row['price'];
            $response[] = [
                "id" => $row["id"],
                "quantity" => (int) $row["quantity"],
                "unit_price" => $unit_price,
                "delivered_quantity" => (int) $row["quantity"],
                "product_variant_id" => $row["product_variant_id"],
                "parcel_id" => $parcel_id
            ];
            $parcel_data[] = [
                "parcel_id" => $parcel_id,
                "store_id" => $store_id,
                "order_item_id" => $row["id"],
                "quantity" => $row["quantity"],
                "unit_price" => $unit_price,
                "product_variant_id" => $row["product_variant_id"],
            ];
            app(OrderService::class)->updateOrder(['status' => $status], ['id' => $row["id"]], true, 'order_items', '', 0, OrderItems::class);
            app(OrderService::class)->updateOrder(['active_status' => $status], ['id' => $row["id"]], false, 'order_items', '', 0, OrderItems::class);
            updateDetails([
                "delivered_quantity" => (int) $row["quantity"]
            ], ["id" => $row["id"]], OrderItems::class);
        }
        Parcelitem::insert($parcel_data);
        return [
            "error" => false,
            "message" => 'Parcel Created Successfully.',
            "data" => $response
        ];
    }
    public function deleteParcel($parcel_id)
    {
        $parcel_items = fetchDetails(ParcelItem::class, ['parcel_id' => $parcel_id], ['order_item_id', 'quantity']);
        if ($parcel_items->isEmpty()) {
            return [
                "error" => true,
                "message" => 'parcel Not Found',
            ];
        }
        $parcel = fetchDetails(Parcel::class, ['id' => $parcel_id], 'active_status');
        $priority_status = [
            'received' => 0,
            'processed' => 1,
            'shipped' => 2,
            'delivered' => 3,
            'return_request_pending' => 4,
            'return_request_decline' => 5,
            'cancelled' => 6,
            'returned' => 7,
        ];
        if (!$parcel->isEmpty()) {
            if ($priority_status[$parcel[0]->active_status] >= $priority_status['shipped']) {
                return [
                    "error" => true,
                    "message" => 'Cannot delete parcel after it has been Shipped',
                ];
            }
        }

        if (
            OrderTracking::where('parcel_id', $parcel_id)
                ->where('is_canceled', 0)
                ->where('shiprocket_order_id', '!=', '')
                ->exists()
        ) {
            return [
                "error" => true,
                "message" => 'The parcel cannot be deleted as a Shiprocket order has been created. Please cancel the Shiprocket order first.',
            ];
        }
        $order_item_id = [];
        foreach ($parcel_items as $item) {
            $order_item = fetchDetails(OrderItems::class, ['id' => $item->order_item_id], 'delivered_quantity');
            foreach ($order_item as $data) {
                $quantity = $item->quantity;
                $delivered_quantity = $data->delivered_quantity;
                $updated_delivered_quantity = (int) $delivered_quantity - (int) $quantity;

                updateDetails([
                    "delivered_quantity" => $updated_delivered_quantity
                ], ["id" => $item->order_item_id], OrderItems::class);
            }
            array_push($order_item_id, $item->order_item_id);
            app(OrderService::class)->updateOrder(['status' => json_encode([["received", date("d-m-y") . " " . date("h:i:sa")]])], ['id' => $item->order_item_id], false, "order_items", false, 0, OrderItems::class);
            app(OrderService::class)->updateOrder(['active_status' => 'received'], ['id' => $item->order_item_id], false, "order_items", false, 0, OrderItems::class);
        }
        deleteDetails(['id' => $parcel_id], Parcel::class);
        deleteDetails(['parcel_id' => $parcel_id], Parcelitem::class);

        $response_data = [];
        foreach ($order_item_id as $val) {
            $order_items = fetchDetails(OrderItems::class, ['id' => $val], ['id', 'product_variant_id', 'quantity', 'delivered_quantity', 'price']);
            foreach ($order_items as $order_item_data) {
                $unit_price = $order_item_data->price;
                // dd($order_item_data->delivered_quantity);
                $response_data[] = [
                    "id" => $order_item_data->id,
                    "delivered_quantity" => (int) $order_item_data->delivered_quantity,
                    "quantity" => (int) $order_item_data->quantity,
                    "product_variant_id" => $order_item_data->product_variant_id,
                    "unit_price" => $unit_price
                ];
            }
        }
        return [
            "error" => false,
            "message" => 'Parcel Deleted Successfully.',
            "data" => $response_data
        ];
    }
    public function viewAllParcels($order_id = '', $parcel_id = '', $seller_id = '', $offset = '', $limit = '', $order = 'DESC', $in_detail = 1, $delivery_boy_id = '', $multiple_status = '', $store_id = '', $parcel_type = "", $from_app = '')
    {
        $order_parcel_type = '';
        if (!empty($parcel_type)) {
            $order_parcel_type = $parcel_type;
        } elseif ($parcel_id) {
            $order_parcel_type_data = fetchDetails(Parcel::class, ['id' => $parcel_id], 'type');
            $order_parcel_type = !$order_parcel_type_data->isEmpty() ? $order_parcel_type_data[0]->type : "";
        }
        // dd($seller_id);
        $query = Parcel::with([
            'order.user',
            'order.items',
            'items.orderItem',
            'storeSeller.user'
        ])
            ->select('parcels.*')
            ->when(!empty($order_id), fn($q) => $q->where('order_id', $order_id))
            ->when(!empty($parcel_id), fn($q) => $q->where('id', $parcel_id))
            ->when(!empty($delivery_boy_id), fn($q) => $q->where('delivery_boy_id', $delivery_boy_id))
            ->when(!empty($store_id), fn($q) => $q->where('store_id', $store_id))
            ->when(!empty($order_parcel_type), fn($q) => $q->where('type', $order_parcel_type))
            ->when(!empty($multiple_status), fn($q) => $q->whereIn('active_status', (array) $multiple_status));

        // Add seller_id filter using order.items.seller_id
        if (!empty($seller_id)) {
            $query->whereHas('order.items', function ($q) use ($seller_id) {
                $q->where('seller_id', $seller_id);
            });
        }

        // Clone for accurate count using distinct ID
        $total = (clone $query)
            ->select('parcels.id')      // Focus on ID only
            ->distinct()                // Remove duplicates
            ->count('parcels.id');
        // Paginate results
        $results = $query->orderBy('id', $order)
            ->offset($offset)
            ->limit($limit)
            ->get();
        $parcel_list = [];

        foreach ($results as $row) {
            // dd($row->order);

            $parcel_id = $row->id;

            // Process sellers details
            $seller_id = optional($row->order->items->first())->seller_id;

            $seller_details = null;

            if ($seller_id) {
                $seller_store = SellerStore::with('user')
                    ->where('seller_id', $seller_id)
                    ->select('store_name', 'logo as store_image', 'user_id')
                    ->first();

                if ($seller_store && $seller_store->user) {
                    $user = $seller_store->user;

                    $seller_details = [
                        'store_name' => $seller_store->store_name,
                        'seller_name' => $user->username ?? '',
                        'address' => $user->address ?? '',
                        'mobile' => $user->mobile ?? '',
                        'store_image' => !empty($seller_store->store_image) ? asset($seller_store->store_image) : null,
                        'latitude' => $user->latitude ?? null,
                        'longitude' => $user->longitude ?? null,
                    ];
                }
            }
            $delivery_boy_data = User::select('id', 'username', 'address', 'mobile', 'email', 'image')
                ->find($row->delivery_boy_id);

            $delivery_boy_details = null;

            if ($delivery_boy_data) {
                $delivery_boy_details = [
                    'id' => $delivery_boy_data->id,
                    'username' => $delivery_boy_data->username,
                    'address' => $delivery_boy_data->address,
                    'mobile' => $delivery_boy_data->mobile,
                    'email' => $delivery_boy_data->email,
                    'image' => !empty($delivery_boy_data->image)
                        ? app(MediaService::class)->getMediaImageUrl($delivery_boy_data->image)
                        : '',
                ];
            }
            // Tracking details
            $tracking_data = OrderTracking::where('parcel_id', $row->id)
                ->where('is_canceled', 0)
                ->first();
            $tracking_details = null;

            if ($tracking_data) {
                $tracking_details = [
                    'id' => $tracking_data->id,
                    'order_id' => $tracking_data->order_id,
                    'shiprocket_order_id' => $tracking_data->shiprocket_order_id,
                    'shipment_id' => $tracking_data->shipment_id,
                    'courier_company_id' => $tracking_data->courier_company_id,
                    'awb_code' => $tracking_data->awb_code,
                    'pickup_status' => $tracking_data->pickup_status,
                    'pickup_scheduled_date' => $tracking_data->pickup_scheduled_date,
                    'pickup_token_number' => $tracking_data->pickup_token_number,
                    'status' => $tracking_data->status,
                    'others' => $tracking_data->others,
                    'pickup_generated_date' => $tracking_data->pickup_generated_date,
                    'data' => $tracking_data->data,
                    'date' => $tracking_data->date,
                    'is_canceled' => $tracking_data->is_canceled,
                    'manifest_url' => $tracking_data->manifest_url,
                    'label_url' => $tracking_data->label_url,
                    'invoice_url' => $tracking_data->invoice_url,
                    'order_item_id' => $tracking_data->order_item_id,
                    'courier_agency' => $tracking_data->courier_agency,
                    'tracking_id' => $tracking_data->tracking_id,
                    'parcel_id' => $tracking_data->parcel_id,
                    'url' => $tracking_data->url,
                    'created_at' => $tracking_data->created_at,
                    'updated_at' => $tracking_data->updated_at,
                ];
            }
            // Cancelled tracking details
            $cancelled_tracking_details = OrderTracking::where('parcel_id', $row->id)
                ->where('is_canceled', 1)
                ->first();

            // Parcel items
            $parcel_items = Parcelitem::where('parcel_id', $parcel_id)
                ->get();
            $items = [];
            $subtotal = 0;
            $total_tax_amount = 0;
            $total_tax_percent = 0;
            $total_unit_price = 0;
            foreach ($parcel_items as $item) {
                $store_id = isset($store_id) && !empty($store_id) ? $store_id : $item->store_id;
                $order_item_details = [];
                if ($in_detail == 1) {
                    $product_details = app(OrderService::class)->fetchOrderItems($item->order_item_id, '', '', '', '', '', 'id', 'DESC', '', '', '', $row->seller_id, $row->order_id, $store_id);

                    if (!empty($product_details)) {
                        $total_tax_amount += isset($product_details['order_data'][0]->tax_amount) ? $product_details['order_data'][0]->tax_amount : 0;
                        $total_tax_percent += isset($product_details['order_data'][0]->tax_percent) ? $product_details['order_data'][0]->tax_percent : 0;
                        $subtotal += isset($product_details['order_data'][0]->sub_total) ? $product_details['order_data'][0]->sub_total : 0;
                        $this->unsetUnnecessaryKeys($product_details);
                        $order_item_details = (array) $product_details;
                    }
                }
                // dd($item->quantity);
                // $total_unit_price += $item->unit_price;
                $total_unit_price += $item->unit_price * $item->quantity;
                $order_item = [
                    'id' => $item->id,
                    'product_variant_id' => $item->product_variant_id,
                    'order_item_id' => $item->order_item_id,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity
                ] + $order_item_details;

                array_push($items, $order_item);
            }
            // Calculate total payable
            $total_order_items = DB::table('order_items')
                ->where('order_id', $row->order_id)
                ->count();
            $delivery_charges = $row->delivery_charge;
            $item_delivery_charges = $delivery_charges / $total_order_items * count($parcel_items);
            // dd($subtotal);
            $total_discount_percentage = ($subtotal > 0 && $row->total > 0) ? app(OrderService::class)->calculatePercentage($subtotal, $row->total) : 0;
            // dd($total_discount_percentage);
            $promo_discount = $row->order->promo_discount ? calculatePrice($total_discount_percentage, $row->order->promo_discount) : 0;
            $wallet_balance = $row->wallet_balance ? calculatePrice($total_discount_percentage, $row->wallet_balance) : 0;

            $row->wallet_balance = (string) (int) $wallet_balance;
            $row->total_payable = (string) (int) ($subtotal + $item_delivery_charges - $promo_discount - $wallet_balance);
            $parcel_data = [
                'id' => $row->id ?? "",
                'store_id' => $row->store_id ?? "",
                'parcel_type' => $row->type ?? "",
                'username' => optional(optional($row->order)->user)->username ?? "",
                'email' => optional(optional($row->order)->user)->email ?? "",
                'mobile' => optional(optional($row->order)->user)->mobile ?? "",
                'order_id' => $row->order_id ?? "",
                'name' => $row->name ?? "",
                'parcel_name' => $row->name ?? "",
                'longitude' => optional(optional($row->order)->user)->longitude ?? "",
                'latitude' => optional(optional($row->order)->user)->latitude ?? "",
                'created_date' => Carbon::parse($row->created_at)->format('Y-m-d H:i:s') ?? "",
                'otp' => $row->otp ?? "",
                'seller_id' => optional(optional($row->order)->items->first())->seller_id ?? "",
                'payment_method' => optional($row->order)->payment_method ?? "",
                'user_address' => optional($row->order)->address ?? "",
                'user_profile' => asset(optional(optional($row->order)->user)->image ?? '') ?? "",
                'total' => optional($row->order)->total ?? "",
                'total_unit_price' => $total_unit_price,
                'delivery_charge' => $item_delivery_charges,
                'delivery_boy_id' => $row->delivery_boy_id ?? "",
                'wallet_balance' => optional($row->order)->wallet_balance ?? 0,
                'discount' => optional($row->order)->discount ?? 0,
                'tax_percent' => (string) $total_tax_percent,
                'tax_amount' => (string) $total_tax_amount,
                'promo_discount' => $promo_discount,
                'total_payable' => optional($row->order)->total_payable ?? "",
                'final_total' => optional($row->order)->final_total ?? "",
                'notes' => optional($row->order)->notes ?? "",
                'delivery_date' => optional($row->order)->delivery_date ?? "",
                'delivery_time' => optional($row->order)->delivery_time ?? "",
                'is_cod_collected' => optional($row->order)->is_cod_collected ?? 0,
                'is_shiprocket_order' => optional($row->order)->is_shiprocket_order ?? 0,
                'active_status' => $row->active_status ?? "",
                'status' => json_decode($row->status ?? '[]'),
                'tracking_details' => $tracking_details ?? null,
                'cancelled_tracking_details' => $cancelled_tracking_details ?? [],
                'items' => $items ?? [],
                'seller_details' => $seller_details ?? [],
                'delivery_boy_details' => $delivery_boy_details ?? []
            ];
            // dd($parcel_data);
            array_push($parcel_list, $parcel_data);
        }
        return response()->json([
            'error' => empty($parcel_list) ? true : false,
            'message' => empty($parcel_list) ? 'No data found' : 'Parcel retrieved successfully',
            'data' => $parcel_list,
            'total' => $total
        ]);
    }
    public function ViewParcel($request, $orderId = null, $sellerId = null, $deliveryBoyId = null, $language_code = '')
    {
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'desc');

        $paymentMethod = $request->input('payment_method');
        $orderStatus = $request->input('active_status');
        $search = trim($request->input('search'));
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = Parcel::with([
            'order.user',
            'items.orderItem',
            'items.productVariant.product'
        ])->distinct();

        // Filters
        if ($orderId)
            $query->where('order_id', $orderId);
        if ($deliveryBoyId)
            $query->where('delivery_boy_id', $deliveryBoyId);

        if ($sellerId) {
            $query->whereHas('items.orderItem', function ($q) use ($sellerId) {
                $q->where('seller_id', $sellerId);
            });
        }

        if ($orderStatus) {
            $statuses = array_map('trim', explode(',', $orderStatus));
            $query->whereIn('active_status', $statuses);
        }

        if ($paymentMethod) {
            $query->whereHas('order', function ($q) use ($paymentMethod) {
                if ($paymentMethod == 'online-payment') {
                    $q->whereNotIn('payment_method', ['cod', 'COD']);
                } else {
                    $q->where('payment_method', $paymentMethod);
                }
            });
        }

        if ($startDate && $endDate) {
            $query->whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date_added', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay()
                ]);
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%$search%")
                    ->orWhere('order_id', 'like', "%$search%")
                    ->orWhere('name', 'like', "%$search%")
                    ->orWhere('status', 'like', "%$search%")
                    ->orWhere('created_at', 'like', "%$search%");
            });
        }

        $total = $query->count();
        $parcels = $query->orderBy($sort, $order)
            ->offset($offset)
            ->limit($limit)
            ->get();

        $translationService = app(TranslationService::class);
        $rows = [];

        foreach ($parcels as $parcel) {
            $productNames = [];
            $quantities = [];

            foreach ($parcel->items as $item) {
                $product = $item->productVariant->product ?? null;
                $productNames[] = $product
                    ? $translationService->getDynamicTranslation(Product::class, 'name', $product->id, $language_code)
                    : '';
                $quantities[] = $item->quantity;
            }

            $user = $parcel->order->user ?? null;
            $orderLink = "<a href='" . route('delivery_boy.orders.edit', ['order' => $parcel->order_id]) . "' target='_blank'>" . $parcel->order_id . "</a>";

            $operate = '<div class="dropdown bootstrap-table-dropdown">
            <a href="#" class="text-dark" data-bs-toggle="dropdown"><i class="fa fa-ellipsis-v"></i></a>
            <div class="dropdown-menu">
                <a class="dropdown-item" href="' . route('delivery_boy.orders.edit', ['order' => $parcel->order_id, 'parcel_id' => $parcel->id]) . '">
                    <i class="bx bx-pencil"></i> Edit
                </a>
            </div>
        </div>';

            $badgeClass = match ($parcel->active_status) {
                'awaiting' => 'bg-secondary',
                'received' => 'bg-primary',
                'processed' => 'bg-info',
                'shipped' => 'bg-warning',
                'delivered' => 'bg-success',
                'returned', 'cancelled', 'return_request_decline' => 'bg-danger',
                'return_request_approved' => 'bg-success',
                'return_request_pending' => 'bg-secondary',
                default => 'bg-light text-dark'
            };

            $statusLabel = $parcel->active_status;
            if ($statusLabel == 'return_request_decline')
                $statusLabel = 'Return Declined';
            if ($statusLabel == 'return_request_approved')
                $statusLabel = 'Return Approved';
            if ($statusLabel == 'return_request_pending')
                $statusLabel = 'Return Requested';

            $statusBadge = "<label class='badge $badgeClass'>$statusLabel</label>";

            $rows[] = [
                'id' => $parcel->id,
                'order_link' => $orderLink,
                'order_id' => $parcel->order_id,
                'seller_id' => $sellerId ?? '',
                'username' => $user->username ?? '',
                'mobile' => $user->mobile ?? '',
                'product_name' => implode(', ', $productNames),
                'quantity' => implode(', ', $quantities),
                'name' => $parcel->name,
                'payment_method' => $parcel->order->payment_method ?? '',
                'status' => $statusBadge,
                'active_status' => $parcel->active_status,
                'otp' => $parcel->otp ?? '',
                'created_at' => ($parcel->created_at)->format('Y-m-d H:i:s'),
                'operate' => $operate,
                'parcel_items' => $parcel->items
            ];
        }

        return [
            'total' => $total,
            'rows' => $rows
        ];
    }
    public function unsetUnnecessaryKeys($product_details)
    {
        unset($product_details->order_item_id, $product_details->user_id, $product_details->delivery_boy_id, $product_details->order_id, $product_details->order_id, $product_details->discounted_price);
    }
}