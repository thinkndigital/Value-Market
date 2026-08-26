<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Libraries\Shiprocket;
use App\Models\Address;
use App\Models\City;
use App\Models\ComboProduct;
use App\Models\Currency;
use App\Models\CustomMessage;
use App\Models\DigitalOrdersMail;
use App\Models\Order;
use App\Models\OrderBankTransfers;
use App\Models\OrderCharges;
use App\Models\OrderItems;
use App\Models\OrderTracking;
use App\Models\PickupLocation;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\Promocode;
use App\Models\Seller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserFcm;
use App\Models\Zipcode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;
use LaravelDaily\Invoices\Classes\Party;
use LaravelDaily\Invoices\Invoice;
use App\Services\TranslationService;
use App\Traits\HandlesValidation;
use App\Services\FirebaseNotificationService;
use App\Services\ProductService;
use App\Services\ComboProductService;
use App\Services\StoreService;
use App\Services\MediaService;
use App\Services\ShiprocketService;
use App\Services\SettingService;
use App\Services\CurrencyService;
use App\Services\MailService;
use App\Services\OrderService;
class OrderController extends Controller
{
    use HandlesValidation;
    public function index(Request $request)
    {
        $store_id = app(StoreService::class)->getStoreId();
        $currencyDetails = fetchDetails(Currency::class, ['is_default' => 1], 'symbol');
        $currency = !$currencyDetails->isEmpty() ? $currencyDetails[0]->symbol : '';
        $user_id = $request['user_id'];
        return view('admin.pages.tables.manage_orders', compact('currency', 'user_id', 'store_id'));
    }

    public function generatInvoicePDF($id)
    {
        $res = app(OrderService::class)->getOrderDetails(['o.id' => $id]);
        // dd($res);
        if (empty($res)) {
            return view('admin.pages.views.no_data_found');
        }
        $seller_ids = array_values(array_unique(array_column($res, "seller_id")));
        $seller_user_ids = [];
        $promo_code = [];
        $items = [];

        foreach ($seller_ids as $id) {
            $seller_user_ids[] = Seller::where('id', $id)->value('user_id');
        }

        if (!empty($res)) {

            if (!empty($res[0]->promo_code_id)) {
                $promo_code = fetchDetails(Promocode::class, ['id' => trim($res[0]->promo_code_id)]);
            }

            foreach ($res as $row) {
                $temp['product_id'] = $row->product_id;
                $temp['seller_id'] = $row->seller_id;
                $temp['product_variant_id'] = $row->product_variant_id;
                $temp['pname'] = $row->pname;
                $temp['quantity'] = $row->quantity;
                $temp['discounted_price'] = $row->discounted_price;
                $temp['tax_percent'] = $row->tax_percent;
                $temp['tax_amount'] = $row->tax_amount;
                $temp['price'] = $row->price;
                $temp['product_price'] = $row->product_price;
                $temp['product_special_price'] = $row->product_special_price;
                $temp['product_price'] = $row->product_price;
                $temp['delivery_boy'] = $row->delivery_boy;
                $temp['mobile_number'] = $row->mobile_number;
                $temp['active_status'] = $row->oi_active_status;
                $temp['hsn_code'] = $row->hsn_code ?? '';
                $temp['is_prices_inclusive_tax'] = $row->is_prices_inclusive_tax;
                array_push($items, $temp);
            }
        }
        // dd($res);
        $item1 = InvoiceItem::make('Service 1')->pricePerUnit(2);
        $sellers = [
            'seller_ids' => $seller_ids,
            'seller_user_ids' => $seller_user_ids,
            'mobile_number' => $res[0]->mobile_number,
        ];

        $customer = new Buyer([
            'name' => $res[0]->uname,
            'custom_fields' => [
                'address' => $res[0]->address,
                'order_id' => $res[0]->id,
                'date_added' => $res[0]->created_at,
                'store_id' => $res[0]->store_id,
                'payment_method' => $res[0]->payment_method,
                'discount' => $res[0]->discount,
                'promo_code' => $promo_code[0]->promo_code ?? '',
                'promo_code_discount' => $promo_code[0]->discount ?? '',
                'promo_code_discount_type' => $promo_code[0]->discount_type ?? '',
            ],
        ]);
        // dd($temp['price']);
        $client = new Party([
            'custom_fields' => $sellers,
        ]);

        $invoice = Invoice::make()
            ->buyer($customer)
            ->seller($client)
            ->setCustomData($items)
            ->addItem($item1)
            ->template('invoice');

        return $invoice->stream();
    }

    public function order_items(Request $request)
    {

        $currencyDetails = fetchDetails(Currency::class, ['is_default' => 1], 'symbol');
        $currency = !$currencyDetails->isEmpty() ? $currencyDetails[0]->symbol : '';
        $user_id = $request['user_id'];
        return view('admin.pages.tables.manage_order_items', compact('currency', 'user_id'));
    }
    public function order_tracking()
    {
        return view('admin.pages.tables.order_tracking');
    }


    public function list()
    {
        $store_id = app(StoreService::class)->getStoreId();
        $search = trim(request('search'));
        $offset = $search || request('pagination_offset') ? request('pagination_offset') : 0;
        $limit = request('limit', 10);
        $sort = request('sort', 'id');
        $order = request('order', 'ASC');
        $startDate = request('start_date');
        $endDate = request('end_date');
        $allowModification = config('constants.ALLOW_MODIFICATION') == 1;
        $query = Order::with([
            'user',
            'promoCode',
            'items.productVariant.product',
            'items.user',
            'items.sellerData.user',
            'items.deliveryBoy'
        ])->where('store_id', $store_id);

        if ($startDate && $endDate) {
            $query->whereHas('items', function ($q) use ($startDate, $endDate) {
                $q->whereDate('created_at', '>=', $startDate)
                    ->whereDate('created_at', '<=', $endDate);
            });
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn($u) => $u->where('username', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%"))
                    ->orWhere('id', 'like', "%$search%")
                    ->orWhere('mobile', 'like', "%$search%")
                    ->orWhere('address', 'like', "%$search%")
                    ->orWhere('wallet_balance', 'like', "%$search%")
                    ->orWhere('total', 'like', "%$search%")
                    ->orWhere('final_total', 'like', "%$search%")
                    ->orWhere('payment_method', 'like', "%$search%")
                    ->orWhere('delivery_charge', 'like', "%$search%")
                    ->orWhere('delivery_time', 'like', "%$search%")
                    ->orWhere('created_at', 'like', "%$search%");
            });
        }

        if (request()->filled('delivery_boy_id')) {
            $query->whereHas('items', fn($q) => $q->where('delivery_boy_id', request('delivery_boy_id')));
        }

        if (request()->filled('seller_id')) {
            $query->whereHas('items', fn($q) => $q->where('seller_id', request('seller_id')));
        }

        if (request()->filled('user_id')) {
            $query->where('user_id', request('user_id'));
        }

        if (request()->filled('payment_method')) {
            $query->where('payment_method', request('payment_method'));
        }

        if (request()->filled('order_type')) {
            if (request('order_type') === 'physical_order') {
                $query->whereHas('items.productVariant.product', fn($q) => $q->where('type', '!=', 'digital_product'));
            } elseif (request('order_type') === 'digital_order') {
                $query->whereHas('items.productVariant.product', fn($q) => $q->where('type', 'digital_product'));
            }
        }

        $totalCount = $query->count();

        $orders = $query->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();

        $rows = [];

        foreach ($orders as $row) {
            if (!$row->items->isEmpty()) {
                $tempRow = [];
                $items1 = '';
                $temp = '';
                $total_amt = $total_qty = 0;

                $sellers = $row->items
                    ->pluck('sellerData.user.username')
                    ->filter()
                    ->unique()
                    ->implode(', ');
                // dd($row->items);
                $items1 = $temp;
                $discounted_amount = $row->total * $row->items[0]->discount / 100;
                $final_total_calc = $row->total - $discounted_amount;
                $discount_in_rupees = floor($row->total - $final_total_calc);

                $tempRow['id'] = $row->id;
                $tempRow['user_id'] = $row->user_id;
                $tempRow['name'] = $row->items[0]->user->username ?? '';
                $tempRow['mobile'] = $allowModification ? $row->mobile : '************';
                $tempRow['delivery_charge'] = app(CurrencyService::class)->formateCurrency(formatePriceDecimal($row->delivery_charge));
                $tempRow['items'] = $items1;
                $tempRow['sellers'] = $sellers;
                $tempRow['total'] = app(CurrencyService::class)->formateCurrency(formatePriceDecimal($row->total));
                $tempRow['wallet_balance'] = app(CurrencyService::class)->formateCurrency(formatePriceDecimal($row->wallet_balance));
                $tempRow['discount'] = $discount_in_rupees . '(' . $row->items[0]->discount . '%)';
                $tempRow['promo_discount'] = app(CurrencyService::class)->formateCurrency(formatePriceDecimal($row->promo_discount));
                $tempRow['promo_code'] = $row->promo_code ?? '';
                $tempRow['notes'] = $row->notes;
                $tempRow['qty'] = $total_qty;

                $final_total = $row->final_total;
                // $final_total = $row->final_total - $row->wallet_balance - $row->discount;
                $tempRow['final_total'] = app(CurrencyService::class)->formateCurrency(formatePriceDecimal($final_total));
                $tempRow['deliver_by'] = $row->delivery_boy;
                $tempRow['payment_method'] = str_replace('_', ' ', $row->payment_method);
                if (!empty($row->items[0]->updated_by)) {
                    $updated_username = fetchDetails(User::class, ['id' => $row->items[0]->updated_by], 'username');
                    $updated_username = !$updated_username->isEmpty() ? $updated_username[0]->username : '';
                    $tempRow['updated_by'] = $updated_username;
                } else {
                    $tempRow['updated_by'] = '';
                }

                $tempRow['address'] = outputEscaping(str_replace('\r\n', '</br>', $row->address));
                $tempRow['delivery_date'] = $row->delivery_date;
                $tempRow['delivery_time'] = $row->delivery_time;
                $tempRow['date_added'] = \Carbon\Carbon::parse($row->created_at)->format('d-m-Y');

                $edit_url = route('admin.orders.edit', $row->id);
                $delete_url = route('admin.orders.destroy', $row->id);

                $action = '<div class="dropdown bootstrap-table-dropdown">
                    <a href="#" class="text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-dots-horizontal-rounded"></i>
                    </a>
                    <div class="dropdown-menu table_dropdown order_action_dropdown" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item dropdown_menu_items" href="' . $edit_url . '"><i class="bx bx-pencil mx-2"></i> Edit</a>
                        <a class="dropdown-item delete-data dropdown_menu_items" data-url="' . $delete_url . '"><i class="bx bx-trash mx-2"></i> Delete</a>
                    </div>
                </div>';

                $tempRow['operate'] = $action;
                $rows[] = $tempRow;
            }
        }

        return response()->json([
            'rows' => $rows,
            'total' => $totalCount,
        ]);
    }

    public function order_item_list()
    {

        $store_id = app(StoreService::class)->getStoreId();
        $search = trim(request()->input('search'));
        $offset = $search || (request('pagination_offset')) ? (request('pagination_offset')) : 0;
        $limit = request('limit', 10);
        $sort = 'id';
        $order = request('order', 'DESC');
        $startDate = request()->input('start_date');
        $endDate = request()->input('end_date');
        $deliveryBoyId = request()->input('delivery_boy_id');
        $sellerId = request()->input('seller_id');
        $userId = request()->input('user_id');
        $orderStatus = request()->input('order_status');

        $paymentMethod = request()->input('payment_method');
        $orderType = request()->input('order_type');

        $countQuery = OrderItems::with([
            'productVariant.product',
            'user',
            'sellerData.user',
            'deliveryBoy',
            'order'
        ])
            ->selectRaw('COUNT(order_id) as total')
            ->where('store_id', $store_id)
            ->whereHas('order', function ($query) {
                $query->where('is_pos_order', 0);
            });

        if ($startDate && $endDate) {
            $countQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        if ($search) {
            $countQuery->where(function ($query) use ($search) {
                $query->whereHas('order.user', function ($q) use ($search) {
                    $q->where('username', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%");
                })->orWhereHas('deliveryBoy', function ($q) use ($search) {
                    $q->where('username', 'like', "%$search%");
                })
                    ->orWhereHas('sellerData.user', function ($q) use ($search) {
                        $q->where('username', 'like', "%$search%");
                    })
                    ->orWhere('id', 'LIKE', "%$search%")
                    ->orWhereHas('order', function ($q) use ($search) {
                        $q->where('mobile', 'like', "%$search%")
                            ->orWhere('address', 'like', "%$search%")
                            ->orWhere('payment_method', 'like', "%$search%")
                            ->orWhere('delivery_time', 'like', "%$search%");
                    })->orWhere('sub_total', 'like', "%$search%")
                    ->orWhere('active_status', 'like', "%$search%")
                    ->orWhereDate('created_at', 'like', "%$search%");
            });
        }

        if ($deliveryBoyId) {
            $countQuery->where('delivery_boy_id', $deliveryBoyId);
        }

        if ($sellerId) {
            $countQuery->where('seller_id', $sellerId)
                ->where('active_status', '!=', 'awaiting');
        }

        if ($userId) {
            $countQuery->whereHas('order', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        }

        if ($orderStatus) {
            $countQuery->where('active_status', $orderStatus);
        }

        if ($paymentMethod) {
            $countQuery->whereHas('order', function ($q) use ($paymentMethod) {
                $q->where('payment_method', $paymentMethod);
            });
        }

        if ($orderType === 'physical_order') {
            $countQuery->whereHas('productVariant.product', function ($q) {
                $q->where('type', '!=', 'digital_product');
            });
        }

        if ($orderType === 'digital_order') {
            $countQuery->whereHas('productVariant.product', function ($q) {
                $q->where('type', 'digital_product');
            });
        }

        $productCount = $countQuery->count();

        $searchQuery = OrderItems::with([
            'productVariant.product',
            'user:id,username',
            'deliveryBoy:id,username',
            'sellerData.user:id,username',
            'order',
            'transaction',
            'orderTracking',
        ])->where('store_id', $store_id)->whereHas('order', function ($query) {
            $query->where('is_pos_order', 0);
        });

        if ($deliveryBoyId) {
            $searchQuery->where('delivery_boy_id', $deliveryBoyId);
        }

        if ($sellerId) {
            $searchQuery->where('seller_id', $sellerId)
                ->where('active_status', '!=', 'awaiting');
        }

        if ($userId) {
            $searchQuery->whereHas('order', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        }

        if ($orderStatus) {
            $searchQuery->where('active_status', $orderStatus);
        }

        if ($paymentMethod) {
            $searchQuery->whereHas('order', function ($q) use ($paymentMethod) {
                $q->where('payment_method', $paymentMethod);
            });
        }

        if ($orderType == 'physical_order') {
            $searchQuery->whereHas('productVariant.product', function ($q) {
                $q->where('type', '!=', 'digital_product');
            });
        }

        if ($orderType == 'digital_order') {
            $searchQuery->whereHas('productVariant.product', function ($q) {
                $q->where('type', 'digital_product');
            });
        }
        $itemDetails = $searchQuery->orderBy($sort, $order)
            ->distinct()
            ->skip($offset)
            ->take($limit)
            ->get();
        // dd($itemDetails);
        $language_code = app(TranslationService::class)->getLanguageCode();
        $final_total_amount = 0;
        $count = $offset + 1;
        $allowModification = config('constants.ALLOW_MODIFICATION') == 1;
        $rows = [];
        foreach ($itemDetails as $item) {
            $tempRow = [];
            switch ($item->active_status) {
                case 'awaiting':
                    $active_status = '<span class="badge bg-secondary">Awaiting</span>';
                    break;
                case 'received':
                    $active_status = '<span class="badge bg-primary">Received</span>';
                    break;
                case 'processed':
                    $active_status = '<span class="badge bg-info text-dark">Processed</span>';
                    break;
                case 'shipped':
                    $active_status = '<span class="badge bg-warning text-dark">Shipped</span>';
                    break;
                case 'delivered':
                    $active_status = '<span class="badge bg-success">Delivered</span>';
                    break;
                case 'returned':
                case 'cancelled':
                    $active_status = '<span class="badge bg-danger">' . ucfirst($item->active_status) . '</span>';
                    break;
                case 'return_request_decline':
                    $active_status = '<span class="badge bg-danger">Return Declined</span>';
                    break;
                case 'return_request_approved':
                    $active_status = '<span class="badge bg-success">Return Approved</span>';
                    break;
                case 'return_request_pending':
                    $active_status = '<span class="badge bg-secondary">Return Requested</span>';
                    break;
                default:
                    $active_status = '<span class="badge bg-light text-dark">' . ucfirst($item->active_status) . '</span>';
                    break;
            }
            switch (optional($item->transaction)->status) {
                case 'success':
                    $tempRow['transaction_status'] = '<span class="badge bg-success">Success</span>';
                    break;
                case 'failed':
                    $tempRow['transaction_status'] = '<span class="badge bg-danger">Failed</span>';
                    break;
                case 'pending':
                    $tempRow['transaction_status'] = '<span class="badge bg-warning text-dark">Pending</span>';
                    break;
                default:
                    $tempRow['transaction_status'] = '<span class="badge bg-secondary">' . ucfirst(optional($item->transaction)->status) . '</span>';
                    break;
            }
            $tempRow['id'] = $count++;
            $tempRow['order_id'] = $item->order_id;
            $tempRow['order_item_id'] = $item->id;
            $tempRow['user_id'] = $item->user_id;
            $tempRow['seller_id'] = $item->seller_id;
            $tempRow['notes'] = $item->notes ?? "";

            $tempRow['username'] = optional($item->user)->username;
            $tempRow['seller_name'] = optional(optional($item->sellerData)->user)->username;

            $tempRow['is_credited'] = $item->is_credited
                ? '<label class="badge bg-success">Credited</label>'
                : '<label class="badge bg-danger">Not Credited</label>';

            $product = optional(optional($item->productVariant)->product);
            $variantName = optional($item->productVariant)->variant_name;
            // dd($product);
            $product_name = app(TranslationService::class)->getDynamicTranslation(
                Product::class,
                'name',
                $product->id,
                $language_code
            );
            $tempRow['product_name'] = $product_name ?? '';
            $tempRow['product_name'] .= (!empty($variantName)) ? " ($variantName)" : "";

            $tempRow['mobile'] = $allowModification ? optional($item->order)->mobile : '************';
            $tempRow['sub_total'] = app(CurrencyService::class)->formateCurrency(formatePriceDecimal($item->sub_total));
            $tempRow['quantity'] = $item->quantity;
            $final_total_amount += intval($item->sub_total);

            $tempRow['delivery_boy'] = optional($item->deliveryBoy)->username;
            $tempRow['payment_method'] = ucfirst(optional($item->order)->payment_method);
            $tempRow['delivery_boy_id'] = $item->delivery_boy_id;
            $tempRow['product_variant_id'] = $item->product_variant_id;

            $tempRow['delivery_date'] = optional($item->order)->delivery_date;
            $tempRow['delivery_time'] = optional($item->order)->delivery_time;

            $tracking = optional($item->orderTracking);
            $tempRow['courier_agency'] = $tracking->courier_agency ?? '';
            $tempRow['tracking_id'] = $tracking->tracking_id ?? '';
            $tempRow['url'] = $tracking->url ?? '';

            // Updated by
            if ($item->updated_by) {
                $updatedUser = \App\Models\User::find($item->updated_by);
                $tempRow['updated_by'] = $updatedUser->username ?? '';
            } else {
                $tempRow['updated_by'] = '';
            }

            $tempRow['status'] = $item->status ?? '';
            // $tempRow['transaction_status'] = optional($item->transaction)->status ?? '';
            $tempRow['active_status'] = $active_status;
            $tempRow['mail_status'] = $item->mail_status ?? '';
            $tempRow['date_added'] = Carbon::parse($item->created_at)->format('d-m-Y');

            // Action buttons
            $edit_url = route('admin.orders.edit', $item->order_id);
            $delete_url = route('admin.order.items.destroy', $item->id);
            $action = '<div class="dropdown bootstrap-table-dropdown">
                    <a href="#" class="text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bx bx-dots-horizontal-rounded"></i>
                    </a>
                    <div class="dropdown-menu table_dropdown order_items_action_dropdown" aria-labelledby="dropdownMenuButton">
                        <a class="dropdown-item dropdown_menu_items" href="' . $edit_url . '"><i class="bx bx-pencil mx-2"></i> Edit</a>
                        <a class="dropdown-item delete-data dropdown_menu_items" data-url="' . $delete_url . '"><i class="bx bx-trash mx-2"></i> Delete</a>
                    </div>
                </div>';

            $tempRow['operate'] = $action;

            $rows[] = $tempRow;
        }
        return response()->json(
            [
                "rows" => $rows,
                "total" => $productCount,
            ]
        );
    }

    public function get_order_tracking(Request $request)
    {
        $store_id = app(StoreService::class)->getStoreId();
        $search = trim($request->input('search'));
        $offset = $search || $request->input('pagination_offset') ? $request->input('pagination_offset') : 0;
        $limit = $request->input('limit', 10);
        $sort = 'id';
        $order = $request->input('order', 'DESC');
        $order_id = $request->input('order_id');

        $query = OrderTracking::with('order')
            ->whereHas('order', function ($q) use ($store_id) {
                $q->where('store_id', $store_id);
            });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%$search%")
                    ->orWhere('order_id', 'LIKE', "%$search%")
                    ->orWhere('tracking_id', 'LIKE', "%$search%")
                    ->orWhere('courier_agency', 'LIKE', "%$search%")
                    ->orWhere('order_item_id', 'LIKE', "%$search%")
                    ->orWhere('url', 'LIKE', "%$search%");
            });
        }

        if ($order_id) {
            $query->where('order_id', $order_id);
        }

        $total = $query->count();

        $txn_search_res = $query->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();

        $rows = [];

        foreach ($txn_search_res as $row) {
            $edit_url = route('admin.orders.edit', $row->order_id);
            $operate = '<div class="d-flex align-items-center">
            <a href="' . $edit_url . '" class="p-2 single_action_button" title="View Order" ><i class="bx bxs-show mx-2"></i></a>
        </div>';

            $tempRow = [
                'id' => $row->id,
                'order_id' => $row->order_id,
                'order_item_id' => $row->order_item_id,
                'courier_agency' => $row->courier_agency,
                'tracking_id' => $row->tracking_id,
                'url' => $row->url,
                'date' => Carbon::parse($row->created_at)->format('d-m-Y'),
                'operate' => $operate,
            ];

            $rows[] = $tempRow;
        }

        return response()->json([
            'rows' => $rows,
            'total' => $total,
        ]);
    }


    public function edit($id)
    {
        $store_id = app(StoreService::class)->getStoreId();
        $res = app(OrderService::class)->getOrderDetails(['o.id' => $id], '', '', $store_id);
        // dd($res);
        if ($res == null || empty($res)) {
            return view('admin.pages.views.no_data_found');
        } else {
            if (isExist(['id' => $res[0]->address_id], Address::class)) {
                $zipcode = fetchDetails(Address::class, ['id' => $res[0]->address_id], 'pincode');

                if (!empty($zipcode) && ($zipcode[0]->pincode != '')) {

                    $zipcode_id = fetchDetails(Zipcode::class, ['zipcode' => $zipcode[0]->pincode], 'id');

                    if (!$zipcode_id->isEmpty()) {

                        $delivery_res = User::where('role_id', 3)
                            ->where('active', 1)
                            ->whereRaw('FIND_IN_SET(?, serviceable_zipcodes) != 0', [$zipcode_id[0]->id])
                            ->get()->toArray();
                    } else {

                        $delivery_res = User::where('role_id', 3)
                            ->where('active', 1)
                            ->get()->toArray();
                    }
                } else {
                    $delivery_res = User::where('role_id', 3)
                        ->where('active', 1)
                        ->get()
                        ->toArray();
                }
            } else {

                $delivery_res = User::where('role_id', 3)
                    ->where('active', 1)
                    ->get()
                    ->toArray();
            }

            if ($res[0]->payment_method == "bank_transfer" || $res[0]->payment_method == "direct_bank_transfer") {
                $bank_transfer = fetchDetails(OrderBankTransfers::class, ['order_id' => $res[0]->order_id]);
                $transaction_search_res = fetchDetails(Transaction::class, ['order_id' => $res[0]->order_id]);
            }
            // dd($transaction_search_res);
            $items = $seller = [];
            foreach ($res as $row) {

                $multipleWhere = ['seller_id' => $row->seller_id, 'order_id' => $row->id];
                $orderChargeData = OrderCharges::where($multipleWhere)->get();

                $updated_username = isset($row->updated_by) && !empty($row->updated_by) && $row->updated_by != 0 ? fetchDetails(User::class, ['id' => $row->updated_by], 'username')[0]->username : '';
                $address_number = (isset($row->address_id) && !empty($row->address_id) && $row->address_id != 0) ? (fetchDetails(Address::class, ['id' => $row->address_id], 'mobile')[0]->mobile ?? '') : '';
                $deliver_by = isset($row->delivery_boy_id) && !empty($row->delivery_boy_id) && $row->delivery_boy_id != 0 ? fetchDetails(User::class, ['id' => $row->delivery_boy_id], 'username')[0]->username : '';
                $temp = [
                    'id' => $row->order_item_id,
                    'item_otp' => $row->item_otp,
                    'tracking_id' => $row->tracking_id,
                    'courier_agency' => $row->courier_agency,
                    'url' => $row->url,
                    'product_id' => $row->product_id,
                    'product_variant_id' => $row->product_variant_id,
                    'product_type' => $row->type,
                    'pname' => $row->pname,
                    'quantity' => $row->quantity,
                    'is_cancelable' => $row->is_cancelable,
                    'is_attachment_required' => $row->is_attachment_required,
                    'is_returnable' => $row->is_returnable,
                    'tax_amount' => $row->tax_amount,
                    'discounted_price' => $row->discounted_price,
                    'price' => $row->price,
                    'updated_by' => $updated_username,
                    'deliver_by' => $deliver_by,
                    'active_status' => $row->oi_active_status,
                    'product_image' => $row->product_image,
                    'product_variants' => app(ProductService::class)->getVariantsValuesById($row->product_variant_id),
                    'pickup_location' => $row->pickup_location,
                    'seller_otp' => isset($orderChargeData[0]) ? $orderChargeData[0]->otp : '',
                    'seller_delivery_charge' => isset($orderChargeData[0]) ? $orderChargeData[0]->delivery_charge : '',
                    'seller_promo_discount' => is_numeric(isset($orderChargeData[0]) ? $orderChargeData[0]->promo_discount : 0) ? (float) $orderChargeData[0]->promo_discount : 0.0,
                    'is_sent' => $row->is_sent,
                    'seller_id' => $row->seller_id,
                    'download_allowed' => $row->download_allowed,
                    'user_email' => $row->user_email,
                    'user_profile' => app(MediaService::class)->getMediaImageUrl($row->user_profile, 'USER_IMG_PATH'),
                    'product_slug' => $row->product_slug,
                    'sku' => isset($row->product_sku) && !empty($row->product_sku) ? $row->product_sku : $row->sku,
                    'address_number' => $address_number,
                    'item_subtotal' => isset($row->sub_total) ? $row->sub_total : '',
                    'wallet_balance' => isset($row->wallet_balance) ? $row->wallet_balance : 0,
                ];



                array_push($items, $temp);
            }
            $order_detls = $res;
            $sellers_id = collect($res)->pluck('seller_id')->unique()->values()->all();
            // dd($sellers_id);
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
            // dd($sellers);
            $bank_transfer = isset($bank_transfer) ? $bank_transfer : [];
            $transaction_search_res = isset($transaction_search_res) ? $transaction_search_res : [];

            $settings = app(SettingService::class)->getSettings('system_settings', true);
            $settings = json_decode($settings, true);

            $shipping_method = app(SettingService::class)->getSettings('shipping_method', true);
            $shipping_method = json_decode($shipping_method, true);

            $currencyDetails = fetchDetails(Currency::class, ['is_default' => 1], 'symbol');
            $currency = !$currencyDetails->isEmpty() ? $currencyDetails[0]->symbol : '';

            return view('admin.pages.forms.edit_orders', compact('delivery_res', 'order_detls', 'bank_transfer', 'store_id', 'transaction_search_res', 'items', 'settings', 'shipping_method', 'sellers', 'currency'));
        }
    }

    public function update_order_status(Request $request)
    {

        $rules = [
            'status' => 'required_without:deliver_by|in:received,processed,shipped,delivered,cancelled,returned',
            'deliver_by' => 'sometimes|nullable|numeric',
            'order_item_id' => [
                'required_if:status,cancelled,returned',
                'min:1',
            ],
            'seller_id' => 'required',
        ];
        $messages = [
            'status.required_without' => 'Please select status or delivery boy for updation.',
            'status.in' => 'Invalid status value.',
            'deliver_by.numeric' => 'Delivery Boy Id must be numeric.',
            'order_item_id.required_if' => 'Please select at least one item of seller for order cancelation or return.',
            'order_item_id.min' => 'Please select at least one item of seller for order cancelation or return.',
            'seller_id.required' => 'Please select at least one seller to update order item(s).',
        ];

        if ($response = $this->HandlesValidation($request, $rules, $messages)) {
            return $response;
        }
        $settings = app(SettingService::class)->getSettings('system_settings', true);
        $settings = json_decode($settings, true);
        $app_name = isset($settings['app_name']) && !empty($settings['app_name']) ? $settings['app_name'] : '';

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

        $order_itam_ids = [];

        if ($request->input('status') == 'cancelled' || $request->input('status') == 'returned') {
            $order_itam_ids = $request->input('order_item_id');
        } else {
            $orderItemId = OrderItems::where('order_id', $request->input('order_id'))
                ->where('seller_id', $request->input('seller_id'))
                ->where('active_status', '!=', 'cancelled')
                ->pluck('id')
                ->toArray();
            foreach ($orderItemId as $ids) {
                array_push($order_itam_ids, $ids->id);
            }
        }

        if (empty($order_itam_ids)) {
            return response()->json([
                'error' => true,
                'message' =>
                    labels('admin_labels.cannot_assign_delivery_boy_to_cancelled_orders', 'You cannot assign a delivery boy to cancelled orders.'),
                'data' => [],
            ]);
        }

        $s = [];

        foreach ($order_itam_ids as $ids) {
            $order_detail = fetchDetails(OrderItems::class, ['id' => $ids], ['is_sent', 'hash_link', 'product_variant_id', 'order_type']);
            $product_data = fetchDetails(Product_variants::class, ['id' => $order_detail[0]->product_variant_id], 'product_id');
            $product_detail = fetchDetails(Product::class, ['id' => $product_data[0]->product_id], 'type');
            if (empty($order_detail[0]->hash_link) || $order_detail[0]->hash_link == '' || $order_detail[0]->hash_link == null) {
                array_push($s, $order_detail[0]->is_sent);
            }
        }
        if (isset($order_detail[0]->order_type) && $order_detail[0]->order_type != 'combo_order') {
            $order_data = fetchDetails(OrderItems::class, ['id' => $order_itam_ids[0]], 'product_variant_id')[0]->product_variant_id;
            $product_id = fetchDetails(Product_variants::class, ['id' => $order_data], 'product_id')[0]->product_id;
            $product_type = fetchDetails(Product::class, ['id' => $product_id], 'type')[0]->type;
        } else {
            $product_type = fetchDetails(ComboProduct::class, ['id' => $order_detail[0]->product_variant_id], 'product_type');
            $product_type = isset($product_type) && !empty($product_type) ? $product_type[0]->product_type : '';
        }


        if ($product_type == 'digital_product' && in_array(0, $s)) {
            return response()->json([
                'error' => true,
                'message' =>
                    labels('admin_labels.items_not_sent_select_sent_item', 'Some of the selected items have not been sent. Please select an item that has been sent.'),
                'data' => [],
            ]);
        }
        $order_items = fetchDetails(OrderItems::class, "", '*', "", "", "", "", "id", $order_itam_ids);


        if (empty($order_items)) {
            return response()->json([
                'error' => true,
                'message' => 'No Order Item Found.',
                'data' => [],
            ]);
        }

        if (count($order_itam_ids) != count($order_items)) {
            return response()->json([
                'error' => true,
                'message' =>
                    labels('admin_labels.item_not_found_on_status_update', 'Some item was not found on status update.'),
                'data' => [],
            ]);
        }

        $order_id = $order_items[0]->order_id;
        $store_id = $order_items[0]->store_id;

        $order_method = fetchDetails(Order::class, ['id' => $order_id], 'payment_method');
        $bank_receipt = fetchDetails(OrderBankTransfers::class, ['order_id' => $order_id]);
        $transaction_status = fetchDetails(Transaction::class, ['order_id' => $order_id], 'status');

        /* validate bank transfer method status */
        if (isset($order_method[0]->payment_method) && $order_method[0]->payment_method == 'bank_transfer') {
            if ($request->input('status') != 'cancelled' && (empty($bank_receipt) || strtolower($transaction_status[0]->status) != 'success' || $bank_receipt[0]->status == "0" || $bank_receipt[0]->status == "1")) {
                return response()->json([
                    'error' => true,
                    'message' =>
                        labels('admin_labels.order_item_status_cant_update_bank_verification_remain', "Order item status can't update, Bank verification is remain from transactions for this order."),
                    'data' => [],
                ]);
            }
        }

        $current_status = fetchDetails(OrderItems::class, ['seller_id' => $request->input('seller_id'), 'order_id' => $request->input('order_id')], ['active_status', 'delivery_boy_id']);

        $awaitingPresent = false;

        foreach ($current_status as $item) {
            if ($item->active_status === 'awaiting') {
                $awaitingPresent = true;
                break;
            }
        }

        // delivery boy update here
        $message = '';
        $delivery_error = false;
        $delivery_boy_updated = 0;
        $delivery_boy_id = $request->filled('deliver_by') ? $request->input('deliver_by') : 0;

        // validate delivery boy when status is shipped

        if ($request->filled('status') && $request->input('status') === 'shipped') {
            if (!isset($current_status[0]->delivery_boy_id) || empty($current_status[0]->delivery_boy_id) || $current_status[0]->delivery_boy_id == 0) {
                if (!isset($delivery_boy_id) && empty($delivery_boy_id)) {
                    return response()->json([
                        'error' => true,
                        'message' =>
                            labels('admin_labels.select_delivery_boy_to_mark_order_shipped', 'Please select a delivery boy to mark this order as shipped.'),
                        'data' => [],
                    ]);
                }
            }
        }

        if (!empty($delivery_boy_id)) {
            if ($awaitingPresent) {
                return response()->json([
                    'error' => true,
                    'message' =>
                        labels('admin_labels.delivery_boy_cant_assign_to_awaiting_orders', "Delivery Boy can't assign to awaiting orders ! please confirm the order first."),
                    'data' => [],
                ]);
            } else {

                $delivery_boy = fetchDetails(User::class, ['id' => trim($delivery_boy_id)], 'id');
                if ($delivery_boy->isEmpty()) {
                    return response()->json([
                        'error' => true,
                        'message' => "Invalid Delivery Boy",
                        'data' => [],
                    ]);
                } else {

                    if (isset($order_items[0]->delivery_boy_id) && !empty($order_items[0]->delivery_boy_id)) {
                        $user_res = fetchDetails(User::class, "", ['fcm_id', 'username'], "", "", "", "", "id", array_column($order_items, "delivery_boy_id"));
                        $results = UserFcm::with('user:id,id,is_notification_on')
                            ->where('user_id', $order_items[0]->delivery_boy_id)
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
                    } else {
                        $user_res = fetchDetails(User::class, ['id' => $delivery_boy_id], ['fcm_id', 'username']);

                        $results = UserFcm::with('user:id,id,is_notification_on')
                            ->where('user_id', $delivery_boy_id->delivery_boy_id)
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
                    }
                    $fcm_ids = array();
                    foreach ($results as $result) {
                        $fcm_ids[] = $result['fcm_id'];
                    }
                    if (isset($user_res[0]) && !empty($user_res[0])) {
                        //custom message
                        $current_delivery_boy = array_column($order_items, "delivery_boy_id");

                        $custom_notification = fetchDetails(CustomMessage::class, $type, '*');
                        if (!empty($current_delivery_boy[0]) && count($current_delivery_boy) > 1) {
                            for ($i = 0; $i < count($order_items); $i++) {
                                $username = isset($user_res[$i]->username) ? $user_res[$i]->username : '';
                                $hashtag_customer_name = '< customer_name >';
                                $hashtag_order_id = '< order_item_id >';
                                $hashtag_application_name = '< application_name >';
                                $string = !$custom_notification->isEmpty() ? json_encode($custom_notification[0]->message, JSON_UNESCAPED_UNICODE) : '';
                                $hashtag = html_entity_decode($string);
                                $data = str_replace(array($hashtag_customer_name, $hashtag_order_id, $hashtag_application_name), array($username, $order_items[0]->order_id, $app_name), $hashtag);
                                $message = outputEscaping(trim($data, '"'));
                                $customer_msg = !$custom_notification->isEmpty() ? $message : 'Hello Dear ' . $username . ' ' . 'Order status updated to' . $request->input('status') . ' for order ID #' . $order_items[0]->order_id . ' please take note of it! Thank you. Regards ' . $app_name . '';
                                $title = !$custom_notification->isEmpty() ? $custom_notification[0]->title : "Order status updated";
                                $order_id = $order_items[0]->order_id;
                                $fcmMsg = array(
                                    'title' => "$title",
                                    'body' => "$customer_msg",
                                    'type' => "order",
                                    'order_id' => "$order_id",
                                    'store_id' => "$store_id",
                                );
                            }
                            $message = 'Delivery Boy Updated.';
                            $delivery_boy_updated = 1;
                        } else {
                            if (isset($order_items[0]->delivery_boy_id) && $order_items[0]->delivery_boy_id == $request->input('deliver_by')) {

                                $custom_notification = fetchDetails(CustomMessage::class, $type, '*');
                                $hashtag_customer_name = '< customer_name >';
                                $hashtag_order_id = '< order_item_id >';
                                $hashtag_application_name = '< application_name >';
                                $string = !$custom_notification->isEmpty() ? json_encode($custom_notification[0]->message, JSON_UNESCAPED_UNICODE) : '';
                                $hashtag = html_entity_decode($string);
                                $data = str_replace(array($hashtag_customer_name, $hashtag_order_id, $hashtag_application_name), array($user_res[0]->username, $order_items[0]->order_id, $app_name), $hashtag);
                                $message = outputEscaping(trim($data, '"'));
                                $customer_msg = !$custom_notification->isEmpty() ? $message : 'Hello Dear ' . $user_res[0]->username . ' ' . 'Order status updated to' . $request->input('status') . ' for order ID #' . $order_items[0]->order_id . ' please take note of it! Thank you. Regards ' . $app_name . '';
                                $title = !$custom_notification->isEmpty() ? $custom_notification[0]->title : "Order status updated";
                                $order_id = $order_items[0]->order_id;
                                $fcmMsg = array(
                                    'title' => "$title",
                                    'body' => "$customer_msg",
                                    'type' => "order",
                                    'order_id' => "$order_id",
                                    'store_id' => "$store_id",
                                );
                                $delivery_boy_updated = 1;
                            } else {
                                $custom_notification = fetchDetails(CustomMessage::class, ['type' => "delivery_boy_order_deliver"], '*');

                                $hashtag_customer_name = '< customer_name >';
                                $hashtag_order_id = '< order_id >';
                                $hashtag_application_name = '< application_name >';
                                $string = !$custom_notification->isEmpty() ? json_encode($custom_notification[0]->message, JSON_UNESCAPED_UNICODE) : '';
                                $hashtag = html_entity_decode($string);
                                $data = str_replace(array($hashtag_customer_name, $hashtag_order_id, $hashtag_application_name), array($user_res[0]->username, $order_items[0]->order_id, $app_name), $hashtag);
                                $message = outputEscaping(trim($data, '"'));
                                $customer_msg = !$custom_notification->isEmpty() ? $message : 'Hello Dear ' . $user_res[0]->username . ' ' . ' you have new order to be deliver order ID #' . $order_items[0]->order_id . ' please take note of it! Thank you. Regards ' . $app_name . '';
                                $order_id = $order_items[0]->order_id;
                                $title = !$custom_notification->isEmpty() ? $custom_notification[0]->title : " Order status updated";
                                $fcmMsg = array(
                                    'title' => "$title",
                                    'body' => "$customer_msg",
                                    'type' => "order",
                                    'order_id' => "$order_id",
                                    'store_id' => "$store_id"
                                );
                                $message = 'Delivery Boy Updated.';
                                $delivery_boy_updated = 1;
                            }
                        }
                    }
                    if (!empty($fcm_ids)) {
                        app(FirebaseNotificationService::class)->sendNotification('', $fcm_ids, $fcmMsg);
                    }

                    if (app(OrderService::class)->updateOrder(['delivery_boy_id' => $delivery_boy_id], $order_itam_ids, false, "order_items", false, 0, OrderItems::class)) {
                        $delivery_error = false;
                    }
                }
            }
        }

        $item_ids = implode(",", $order_itam_ids);

        $res = app(OrderService::class)->validateOrderStatus($item_ids, $request->input('status'));

        if ($res['error']) {
            return response()->json([
                'error' => $delivery_boy_updated == 1 ? false : true,
                'message' => ($request->filled('status') && $delivery_error == false) ? $message . $res['message'] : $message,
                'data' => [],
            ]);
        }

        if (!empty($order_items)) {
            for ($j = 0; $j < count($order_items); $j++) {
                $order_item_id = $order_items[$j]->id;
                /* velidate bank transfer method status */

                if ($order_method[0]->payment_method == 'bank_transfer') {

                    if ($request->input('status') != 'cancelled' && (empty($bank_receipt) || strtolower($transaction_status[0]->status) != 'success' || $bank_receipt[0]->status == "0" || $bank_receipt[0]->status == "1")) {
                        return response()->json([
                            'error' => true,
                            'message' =>
                                labels('admin_labels.order_item_status_cant_update_bank_verification_remain', 'Order item status can not update, Bank verification is remain from transactions for this order.'),
                            'data' => [],
                        ]);
                    }
                }

                // processing order items

                $order_item_res = OrderItems::select('order_items.*')
                    ->selectRaw('(SELECT COUNT(id) FROM order_items WHERE order_id = order_items.order_id) AS order_counter')
                    ->selectRaw('(SELECT COUNT(active_status) FROM order_items WHERE active_status = "cancelled" AND order_id = order_items.order_id) AS order_cancel_counter')
                    ->selectRaw('(SELECT COUNT(active_status) FROM order_items WHERE active_status = "returned" AND order_id = order_items.order_id) AS order_return_counter')
                    ->selectRaw('(SELECT COUNT(active_status) FROM order_items WHERE active_status = "delivered" AND order_id = order_items.order_id) AS order_delivered_counter')
                    ->selectRaw('(SELECT COUNT(active_status) FROM order_items WHERE active_status = "processed" AND order_id = order_items.order_id) AS order_processed_counter')
                    ->selectRaw('(SELECT COUNT(active_status) FROM order_items WHERE active_status = "shipped" AND order_id = order_items.order_id) AS order_shipped_counter')
                    ->selectRaw('(SELECT status FROM orders WHERE id = order_items.order_id) AS order_status')
                    ->where('order_items.id', $order_item_id)
                    ->get()
                    ->toArray();

                if (app(OrderService::class)->updateOrder(['status' => $request->input('status')], ['id' => $order_item_res[0]->id], true, "order_items", false, 0, OrderItems::class)) {
                    app(OrderService::class)->updateOrder(['active_status' => $request->input('status')], ['id' => $order_item_res[0]->id], false, "order_items", false, 0, OrderItems::class);
                    app(OrderService::class)->process_refund($order_item_res[0]->id, $request->input('status'), 'order_items');
                    if (trim($request->input('status')) == 'cancelled' || trim($request->input('status')) == 'returned') {
                        $data = fetchDetails(OrderItems::class, ['id' => $order_item_id], ['product_variant_id', 'quantity', 'order_type']);

                        if ($data[0]->order_type == 'regular_order') {
                            app(ProductService::class)->updateStock($data[0]->product_variant_id, $data[0]->quantity, 'plus');
                        }
                        if ($data[0]->order_type == 'combo_order') {
                            app(ComboProductService::class)->updateComboStock($data[0]->product_variant_id, $data[0]->quantity, 'plus');
                        }
                    }

                    if (($order_item_res[0]->order_counter == intval($order_item_res[0]->order_cancel_counter) + 1 && $request->input('status') == 'cancelled') || ($order_item_res[0]->order_counter == intval($order_item_res[0]->order_return_counter) + 1 && $request->input('status') == 'returned') || ($order_item_res[0]->order_counter == intval($order_item_res[0]->order_delivered_counter) + 1 && $request->input('status') == 'delivered') || ($order_item_res[0]->order_counter == intval($order_item_res[0]->order_processed_counter) + 1 && $request->input('status') == 'processed') || ($order_item_res[0]->order_counter == intval($order_item_res[0]->order_shipped_counter) + 1 && $request->input('status') == 'shipped')) {
                        /* process the refer and earn */
                        $user = fetchDetails(Order::class, ['id' => $order_item_res[0]->order_id], 'user_id');
                        $user_id = $user[0]->user_id;
                        $response = processReferralBonus($user_id, $order_item_res[0]->order_id, $request->input('status'));
                    }
                }
                // Update login id in order_item table

                updateDetails(['updated_by' => auth()->id()], ['order_id' => $order_item_res[0]->order_id, 'seller_id' => $order_item_res[0]->seller_id], OrderItems::class);
            }

            $user = fetchDetails(Order::class, ['id' => $order_item_res[0]->order_id], 'user_id');
            $user_res = fetchDetails(User::class, ['id' => $user[0]->user_id], ['username', 'fcm_id']);


            $results = UserFcm::with('user:id,id,is_notification_on')
                ->where('user_id', $user[0]->user_id)
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
            $custom_notification = fetchDetails(CustomMessage::class, $type, '*');
            $hashtag_customer_name = '< customer_name >';
            $hashtag_order_id = '< order_item_id >';
            $hashtag_application_name = '< application_name >';
            $string = !$custom_notification->isEmpty() ? json_encode($custom_notification[0]->message, JSON_UNESCAPED_UNICODE) : '';
            $hashtag = html_entity_decode($string);
            $data = str_replace(array($hashtag_customer_name, $hashtag_order_id, $hashtag_application_name), array($user_res[0]->username, $order_item_res[0]->id, $app_name), $hashtag);
            $message = outputEscaping(trim($data, '"'));
            $customer_msg = !$custom_notification->isEmpty() ? $message : 'Hello Dear ' . $user_res[0]->username . ' Order status updated to' . $request->input('val') . ' for order ID #' . $order_item_res[0]->order_id . ' please take note of it! Thank you. Regards ' . $app_name . '';

            $title = !$custom_notification->isEmpty() ? $custom_notification[0]->title : " Order status updated";
            $order_id = $order_item_res[0]->order_id;

            $fcmMsg = array(
                'title' => "$title",
                'body' => "$customer_msg",
                'type' => "order",
                'order_id' => "$order_id",
                'store_id' => "$store_id",
            );
            $registrationIDs_chunks = array_chunk($fcm_ids, 1000);
            app(FirebaseNotificationService::class)->sendNotification('', $registrationIDs_chunks, $fcmMsg);

            $seller_id = Seller::where('id', $order_item_res[0]->seller_id)->value('user_id');
            $seller_res = fetchDetails(User::class, ['id' => $seller_id], ['username', 'fcm_id']);

            $seller_fcm_ids = array();
            $seller_results = fetchDetails(UserFcm::class, ['user_id' => $seller_id], 'fcm_id');
            foreach ($seller_results as $result) {
                if (is_object($result)) {
                    $seller_fcm_ids[] = $result->fcm_id;
                }
            }
            if (!empty($seller_res[0]->fcm_id)) {
                $hashtag_customer_name = '< customer_name >';
                $hashtag_order_id = '< order_item_id >';
                $hashtag_application_name = '< application_name >';
                $string = !$custom_notification->isEmpty() ? json_encode($custom_notification[0]->message, JSON_UNESCAPED_UNICODE) : '';
                $hashtag = html_entity_decode($string);
                $data = str_replace(array($hashtag_customer_name, $hashtag_order_id, $hashtag_application_name), array($seller_res[0]->username, $order_item_res[0]->id, $app_name), $hashtag);
                $message = outputEscaping(trim($data, '"'));
                $customer_msg = !$custom_notification->isEmpty() ? $message : 'Hello Dear ' . $seller_res[0]->username . ' Order status updated to ' . $request->input('status') . ' for your order ID #' . $order_item_res[0]->order_id . ' please take note of it! Regards ' . $app_name . '';

                $title = !$custom_notification->isEmpty() ? $custom_notification[0]->title : " Order status updated";
                $order_id = $order_item_res[0]->order_id;
                $fcmMsg = array(
                    'title' => "$title",
                    'body' => "$customer_msg",
                    'type' => "order",
                    'order_id' => "$order_id",
                    'store_id' => "$store_id",
                );
                $seller_registrationIDs_chunks = array_chunk($seller_fcm_ids, 1000);
                app(FirebaseNotificationService::class)->sendNotification('', $seller_registrationIDs_chunks, $fcmMsg);
            }

            return response()->json([
                'error' => false,
                'message' => labels('admin_labels.status_updated_successfully', 'Status updated successfully.'),
                'data' => [],
            ]);
        }
    }

    public function update_order_tracking(Request $request)
    {
        $rules = [
            'courier_agency' => 'required|string',
            'tracking_id' => 'required',
            'url' => 'required|url',
            'order_id' => 'required|numeric|exists:orders,id',
            'seller_id' => 'required',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }
        $order_id = $request->input('order_id');
        $order_item_id = $request->input('order_item_id');
        $seller_id = $request->input('seller_id');
        $courier_agency = $request->input('courier_agency');
        $tracking_id = $request->input('tracking_id');
        $url = $request->input('url');

        $order_item_ids = fetchDetails(OrderItems::class, ['order_id' => $order_id, 'seller_id' => $seller_id], 'id');

        foreach ($order_item_ids as $ids) {
            $data = [
                'order_id' => $order_id,
                'order_item_id' => $ids->id,
                'courier_agency' => $courier_agency,
                'tracking_id' => $tracking_id,
                'url' => $url,
            ];

            if (isExist(['order_item_id' => $ids->id, 'order_id' => $order_id], OrderTracking::class, null)) {
                if (updateDetails($data, ['order_id' => $order_id, 'order_item_id' => $ids->id], OrderTracking::class) == TRUE) {
                    $response['error'] = false;
                    $response['message'] =
                        labels('admin_labels.tracking_details_update_successfully', 'Tracking details Update Successfuly.');
                } else {
                    $response['error'] = true;
                    $response['message'] =
                        labels('admin_labels.tracking_details_update_failed', 'Not Updated. Try again later.');
                }
            } else {
                if (OrderTracking::create($data)) {
                    $response['error'] = false;
                    $response['message'] =
                        labels('admin_labels.tracking_details_insert_successfully', 'Tracking details Insert Successfuly.');
                } else {
                    $response['error'] = true;
                    $response['message'] =
                        labels('admin_labels.tracking_details_insert_failed', 'Not Inserted. Try again later.');
                }
            }
        }
        return response()->json($response);
    }

    public function create_shiprocket_order(Request $request)
    {
        $rules = [
            'pickup_location' => 'required',
            'parcel_weight' => 'required',
            'parcel_height' => 'required',
            'parcel_breadth' => 'required',
            'parcel_length' => 'required',
        ];
        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }
        $request['order_items'] = json_decode($request['order_items'][0], 1);
        $shiprocket = new Shiprocket();
        $order_items = $request['order_items'];
        $items = [];
        $subtotal = 0;
        $order_id = 0;

        $pickup_location_pincode = fetchDetails(PickupLocation::class, ['pickup_location' => $request['pickup_location']], 'pincode');
        $user_data = fetchDetails(User::class, ['id' => $request['user_id']], ['username', 'email']);
        $order_data = fetchDetails(Order::class, ['id' => $request['order_id']], ['created_at', 'address_id', 'mobile', 'payment_method', 'delivery_charge']);
        $address_data = fetchDetails(Address::class, ['id' => $order_data[0]->address_id], ['address', 'city_id', 'pincode', 'state', 'country']);
        $city_data = fetchDetails(City::class, ['id' => $address_data[0]->city_id], 'name');

        $availibility_data = [
            'pickup_postcode' => $pickup_location_pincode[0]->pincode,
            'delivery_postcode' => $address_data[0]->pincode,
            'cod' => (strtoupper($order_data[0]->payment_method) == 'COD') ? '1' : '0',
            'weight' => $request['parcel_weight'],
        ];

        $check_deliveribility = $shiprocket->check_serviceability($availibility_data);

        $get_currier_id = app(ShiprocketService::class)->shiprocketRecomendedData($check_deliveribility);

        foreach ($order_items as $row) {
            if ($row['pickup_location'] == $_POST['pickup_location'] && $row['seller_id'] == $_POST['shiprocket_seller_id']) {
                $order_item_id[] = $row['id'];
                $order_id .= '-' . $row['id'];
                $order_item_data = fetchDetails(OrderItems::class, ['id' => $row['id']], 'sub_total');
                $subtotal += $order_item_data[0]->sub_total;
                if (isset($row['product_variants']) && !empty($row['product_variants'])) {
                    $sku = $row['product_variants'][0]['sku'];
                } else {
                    $sku = $row['sku'];
                }
                $row['product_slug'] = strlen($row['product_slug']) > 8 ? substr($row['product_slug'], 0, 8) : $row['product_slug'];
                $temp['name'] = $row['pname'];
                $temp['sku'] = isset($sku) && !empty($sku) ? $sku : $row['product_slug'];
                $temp['units'] = $row['quantity'];
                $temp['selling_price'] = $row['price'];
                $temp['discount'] = $row['discounted_price'];
                $temp['tax'] = $row['tax_amount'];
                array_push($items, $temp);
            }
        }

        $order_item_ids = implode(",", $order_item_id);
        $random_id = '-' . rand(10, 10000);
        $delivery_charge = (strtoupper($order_data[0]->payment_method) == 'COD') ? $order_data[0]->delivery_charge : 0;
        $create_order = [
            'order_id' => $request['order_id'] . $order_id . $random_id,
            'order_date' => $order_data[0]->created_at,
            'pickup_location' => $request['pickup_location'],
            'billing_customer_name' => $user_data[0]->username,
            'billing_last_name' => "",
            'billing_address' => $address_data[0]->address,
            'billing_city' => $city_data[0]->name,
            'billing_pincode' => $address_data[0]->pincode,
            'billing_state' => $address_data[0]->state,
            'billing_country' => $address_data[0]->country,
            'billing_email' => $user_data[0]->email,
            'billing_phone' => $order_data[0]->mobile,
            'shipping_is_billing' => true,
            'order_items' => $items,
            'payment_method' => (strtoupper($order_data[0]->payment_method) == 'COD') ? 'COD' : 'Prepaid',
            'sub_total' => $subtotal + $delivery_charge,
            'length' => $request['parcel_length'],
            'breadth' => $request['parcel_breadth'],
            'height' => $request['parcel_height'],
            'weight' => $request['parcel_weight'],
        ];

        $response = $shiprocket->create_order($create_order);

        if (isset($response['status_code']) && $response['status_code'] == 1) {
            $courier_company_id = $get_currier_id['courier_company_id'];
            $order_tracking_data = [
                'order_id' => $request['order_id'],
                'order_item_id' => $order_item_ids,
                'shiprocket_order_id' => $response['order_id'],
                'shipment_id' => $response['shipment_id'],
                'courier_company_id' => $courier_company_id,
                'pickup_status' => 0,
                'pickup_scheduled_date' => '',
                'pickup_token_number' => '',
                'status' => 0,
                'others' => '',
                'pickup_generated_date' => '',
                'data' => '',
                'date' => '',
                'manifest_url' => '',
                'label_url' => '',
                'invoice_url' => '',
                'is_canceled' => 0,
                'tracking_id' => '',
                'url' => ''
            ];
            OrderTracking::create($order_tracking_data);
        }
        if (isset($response['status_code']) && $response['status_code'] == 1) {
            $response['error'] = false;
            $response['message'] =
                labels('admin_labels.shiprocket_order_created_successfully', 'Shiprocket order created successfully');
            $response['data'] = $response;
        } else {
            $response['error'] = true;
            $response['message'] =
                labels('admin_labels.shiprocket_order_not_created_successfully', 'Shiprocket order not created successfully');
            $response['data'] = $response;
        }
        return response()->json($response);
    }

    public function generate_awb(Request $request)
    {
        $res = app(ShiprocketService::class)->generateAwb($request['shipment_id']);
        if (!empty($res) && $res['awb_assign_status'] == 1) {
            $response['error'] = false;
            $response['message'] =
                labels('admin_labels.awb_generated_successfully', 'AWB generated successfully');
            $response['data'] = $res;
        } else {
            $response['error'] = true;
            $response['message'] =
                labels('admin_labels.awb_not_generated', 'AWB not generated');
            $response['data'] = array();
        }
        return response()->json($response);
    }

    public function send_pickup_request(Request $request)
    {
        $res = app(ShiprocketService::class)->sendPickupRequest($request['shipment_id']);

        if (!empty($res)) {
            $response['error'] = false;
            $response['message'] =
                labels('admin_labels.request_send_successfully', 'Request send successfully');
            $response['data'] = $res;
        } else {
            $response['error'] = true;
            $response['message'] =
                labels('admin_labels.request_not_sent', 'Request not sent');
            $response['data'] = array();
        }
        return response()->json($response);
    }

    public function cancel_shiprocket_order(Request $request)
    {
        $res = app(ShiprocketService::class)->cancelShiprocketOrder($request['shiprocket_order_id']);
        if (!empty($res) && $res['status'] == 200) {
            $response['error'] = false;
            $response['message'] =
                labels('admin_labels.order_cancelled_successfully', 'Order cancelled successfully');
            $response['data'] = $res;
        } else {
            $response['error'] = true;
            $response['message'] =
                labels('admin_labels.order_not_cancelled', 'Order not cancelled');
            $response['data'] = array();
        }
        return response()->json($response);
    }

    public function generate_label(Request $request)
    {
        $res = app(ShiprocketService::class)->generateLabel($request['shipment_id']);
        if (!empty($res)) {
            $response['error'] = false;
            $response['message'] =
                labels('admin_labels.label_generated_successfully', 'Label generated successfully');
            $response['data'] = $res;
        } else {
            $response['error'] = true;
            $response['message'] =
                labels('admin_labels.label_not_generated', 'Label not generated');
            $response['data'] = array();
        }
        return response()->json($response);
    }

    public function generate_invoice(Request $request)
    {
        $res = app(ShiprocketService::class)->generateInvoice($request['order_id']);
        if (!empty($res) && isset($res['is_invoice_created']) && $res['is_invoice_created'] == 1) {
            $response['error'] = false;
            $response['message'] =
                labels('admin_labels.invoice_generated_successfully', 'Invoice generated successfully');
            $response['data'] = $res;
        } else {
            $response['error'] = true;
            $response['message'] =
                labels('admin_labels.invoice_not_generated', 'Invoice not generated');
            $response['data'] = array();
        }
        return response()->json($response);
    }

    public function send_digital_product(Request $request)
    {

        $rules = [
            'message' => 'required',
            'subject' => 'required',
            'pro_input_file' => 'required',
        ];

        $messages = [
            'pro_input_file.required' => labels('admin_labels.select_attachment_file', 'Please select Attachment file.'),
        ];

        if ($response = $this->HandlesValidation($request, $rules, $messages)) {
            return $response;
        }

        $message = str_replace('\r\n', '&#13;&#10;', $request['message']);

        $attachment = asset(config('constants.MEDIA_PATH') . $request['pro_input_file']);
        $to = $request['email'];
        $subject = $request['subject'];

        $mail = app(MailService::class)->sendDigitalProductMail($to, $subject, $message, $attachment);

        if ($mail['error'] == true) {
            $response['error'] = true;
            $response['message'] =
                labels('admin_labels.mail_not_sent_try_manually', 'Cannot send mail. You can try to send mail manually.');
            $response['data'] = $mail['message'];
            return response()->json($response);
        } else {
            $response['error'] = false;
            $response['message'] = 'Mail sent successfully.';
            $response['data'] = array();

            updateDetails(['active_status' => 'delivered'], ['id' => $request['order_item_id']], OrderItems::class);
            updateDetails(['is_sent' => 1], ['id' => $request['order_item_id']], OrderItems::class);
            $data = [
                'order_id' => $request['order_id'],
                'order_item_id' => $request['order_item_id'],
                'subject' => $request['subject'],
                'message' => $request['message'],
                'file_url' => $request['pro_input_file'],
            ];
            DigitalOrdersMail::create($data);

            return response()->json($response);
        }
    }


    public function destroyReceipt($id)
    {

        if (empty($id)) {
            $response['error'] = true;
            $response['message'] = labels('admin_labels.something_went_wrong', 'Something went wrong');
        }

        $data = fetchDetails(OrderBankTransfers::class, ['id' => $id], '*');

        if ($data[0]->disk == 's3') {
            // Specify the path and disk from which you want to delete the file

            $path = $data[0]->attachments;
            // Call the removeFile method to delete the file
            app(MediaService::class)->removeMediaFile($path, $data[0]->disk);
            deleteDetails(['id' => $id], OrderBankTransfers::class);

            $response['error'] = false;
            $response['message'] =
                labels('admin_labels.deleted_successfully', 'Deleted Successfully');
        } else if (deleteDetails(['id' => $id], OrderBankTransfers::class)) {
            $response['error'] = false;
            $response['message'] =
                labels('admin_labels.deleted_successfully', 'Deleted Successfully');
        } else {
            $response['error'] = true;
            $response['message'] = labels('admin_labels.something_went_wrong', 'Something went wrong');
        }
        return response()->json($response);
    }

    public function update_receipt_status(Request $request)
    {

        $rules = [
            'order_id' => 'required|exists:orders,id',
            'user_id' => 'required|exists:users,id',
            'status' => 'required',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        } else {
            $order_id = $request->input('order_id');
            $store_id = fetchDetails(Order::class, ['id' => $order_id], 'store_id');
            $store_id = !$store_id->isEmpty() ? $store_id[0]->store_id : "";
            $user_id = $request->input('user_id');
            $status = $request->input('status');
            // dd($status == 2);
            if (updateDetails(['status' => $status], ['order_id' => $order_id], OrderBankTransfers::class)) {
                if ($status == 1) {
                    $status = "Rejected";
                } else if ($status == 2) {
                    $status = "Accepted";
                    updateDetails(['active_status' => 'received'], ['order_id' => $order_id], OrderItems::class);
                    $status = json_encode(array(array('received', date("d-m-Y h:i:sa"))));
                    updateDetails(['status' => $status], ['order_id' => $order_id], OrderItems::class);
                } else {
                    $status = "Pending";
                }
                //custom message
                $custom_notification = fetchDetails(CustomMessage::class, ['type' => "bank_transfer_receipt_status"], '*');
                $hashtag_status = '< status >';
                $hashtag_order_id = '< order_id >';
                $string = !$custom_notification->isEmpty() ? json_encode($custom_notification[0]->message, JSON_UNESCAPED_UNICODE) : '';
                $hashtag = html_entity_decode($string);
                $data = str_replace(array($hashtag_status, $hashtag_order_id), array($status, $order_id), $hashtag);
                $message = outputEscaping(trim($data, '"'));
                $customer_title = !$custom_notification->isEmpty() ? $custom_notification[0]->title : 'Bank Transfer Receipt Status';
                $customer_msg = !$custom_notification->isEmpty() ? $message : 'Bank Transfer Receipt' . $status . ' for order ID: ' . $order_id;
                $user = fetchDetails(User::class, ['id' => $user_id], ['email', 'fcm_id']);

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

                if (!empty($fcm_ids)) {
                    $fcmMsg = array(
                        'title' => "$customer_title",
                        'body' => "$customer_msg",
                        'type' => "order",
                        'store_id' => "$store_id",
                    );
                    app(FirebaseNotificationService::class)->sendNotification('', $fcm_ids, $fcmMsg);
                }
                $response['error'] = false;
                $response['message'] =
                    labels('admin_labels.updated_successfully', 'Updated Successfully');
            } else {
                $response['error'] = true;
                $response['message'] = labels('admin_labels.something_went_wrong', 'Something went wrong');
            }
            return response()->json($response);
        }
    }
    public function destroy($id)
    {

        $delete = [
            "order_items" => 0,
            "orders" => 0,
            "order_bank_transfer" => 0
        ];

        $orders = OrderItems::where('order_id', $id)
            ->with('order')
            ->get();

        if (!empty($orders)) {
            // delete orders

            if (deleteDetails(['order_id' => $id], OrderItems::class)) {
                $delete['order_items'] = 1;
            }
            if (deleteDetails(['id' => $id], Order::class)) {
                $delete['orders'] = 1;
            }
            if (deleteDetails(['order_id' => $id], OrderBankTransfers::class)) {
                $delete['order_bank_transfer'] = 1;
            }
        }

        if ($delete['order_items']) {
            return response()->json([
                'error' => false,
                'message' => labels('admin_labels.deleted_successfully', 'Deleted Successfully'),
            ]);
        } else {
            return response()->json(['error' => labels('admin_labels.something_went_wrong', 'Something went wrong')]);
        }
    }


    public function order_item_destroy($id)
    {
        $delete = array(
            "order_items" => 0,
            "orders" => 0,
            "order_bank_transfer" => 0
        );
        /* check order items */
        $order_items = fetchDetails(OrderItems::class, ['id' => $id], ['id', 'order_id']);
        if (deleteDetails(['id' => $id], OrderItems::class)) {
            $delete['order_items'] = 1;
        }
        $res_order_id = array_values(array_unique(array_column($order_items, "order_id")));

        for ($i = 0; $i < count($res_order_id); $i++) {
            $orders = Order::with('orderItems')
                ->whereHas('orderItems', function ($query) use ($res_order_id) {
                    $query->where('order_id', $res_order_id);
                })
                ->get();

            if (empty($orders)) {
                // delete orders
                if (deleteDetails(['id' => $res_order_id[$i]], Order::class)) {
                    $delete['orders'] = 1;
                }
                if (deleteDetails(['order_id' => $res_order_id[$i]], OrderBankTransfers::class)) {
                    $delete['order_bank_transfer'] = 1;
                }
            }
        }

        if ($delete['order_items'] == true) {
            return response()->json([
                'error' => false,
                'message' => labels('admin_labels.deleted_successfully', 'Deleted Successfully'),
            ]);
        } else {
            return response()->json(['error' => labels('admin_labels.something_went_wrong', 'Something went wrong')]);
        }
    }
}
