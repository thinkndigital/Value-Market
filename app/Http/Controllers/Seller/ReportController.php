<?php

namespace App\Http\Controllers\seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Seller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Services\TranslationService;
use App\Services\StoreService;
class ReportController extends Controller
{
    public function index()
    {
        return view('seller.pages.tables.sales_report');
    }

    public function list(Request $request)
    {
        $store_id = app(StoreService::class)->getStoreId();
        $search = trim($request->input('search'));
        $offset = $search || $request->has('pagination_offset') ? $request->input('pagination_offset', 0) : 0;
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'ASC');
        $user = Auth::user();
        $seller_id = Seller::where('user_id', $user->id)->value('id');
        // Fetch orders with relationships
        $ordersQuery = Order::with(['items.productVariant.product', 'store'])
            ->where('store_id', $store_id)
            ->where('is_pos_order', 0)
            ->whereHas('items', function ($query) use ($seller_id) {
                $query->where('seller_id', $seller_id);
            });
        // Optional search on order ID or product name
        if (!empty($search)) {
            $ordersQuery->where(function ($query) use ($search) {
                $query->where('id', 'like', '%' . $search . '%')
                    ->orWhereHas('items', function ($itemQuery) use ($search) {
                        $itemQuery->where('product_name', 'like', '%' . $search . '%');
                    });
            });
        }

        // Clone to get total before pagination
        $total = (clone $ordersQuery)->count();

        // Apply pagination and sorting
        $orders = $ordersQuery
            ->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit)
            ->get();

        $language_code = app(TranslationService::class)->getLanguageCode();
        $rows = [];

        foreach ($orders as $order) {
            $adminCommission = 0;
            $sellerCommission = 0;
            $productCost = 0;
            $productName = '';

            foreach ($order->items as $item) {
                $adminCommission += $item->admin_commission_amount;
                $sellerCommission += $item->seller_commission_amount;
                $productCost += $item->price * $item->quantity;

                // Get product name via variant -> product relationship
                if ($item->productVariant && $item->productVariant->product) {
                    $productName = app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $item->productVariant->product->id, $language_code);
                }
            }

            $totalCommissions = $adminCommission + $sellerCommission;
            $netRevenue = $order->total - $order->promo_discount + $order->delivery_charge;
            $totalCosts = $productCost + $totalCommissions;
            $profit = max($netRevenue - $totalCosts, 0);
            $loss = max(abs(min($netRevenue - $totalCosts, 0)), 0);
            // dd($order->orderItems);
            $rows[] = [
                'id' => $order->id,
                'product_name' => $productName,
                'final_total' => (float) $order->final_total,
                'date_added' => Carbon::parse($order->created_at)->format('d-m-Y'),
                'payment_method' => $order->payment_method,
                'loss' => (float) $loss,
            ];
        }

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }
    public function get_sales_list(Request $request)
    {
        $store_id = $request->input('store_id', 0);
        $search = trim($request->input('search'));
        $offset = $search || $request->input('pagination_offset') ? $request->input('pagination_offset') : 0;
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'ASC');

        $user = Auth::user();
        $seller = Seller::where('user_id', $user->id)->first();

        if (!$seller) {
            return [
                'error' => true,
                'message' => "Seller not found",
                'total' => 0,
                'grand_total' => 0,
                'total_delivery_charge' => 0,
                'grand_final_total' => 0,
                'rows' => [],
            ];
        }

        // Build base query
        $ordersQuery = Order::with([
            'user:id,username,email,mobile',
            'orderItems' => function ($q) use ($seller) {
                $q->where('seller_id', $seller->id);
            },
            'orderItems.sellerData.sellerStore'
        ])
            ->whereHas('orderItems', function ($q) use ($seller) {
                $q->where('seller_id', $seller->id);
            })
            ->where('store_id', $store_id);

        // Date filter
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $ordersQuery->whereBetween('created_at', [$request->input('start_date'), $request->input('end_date')]);
        }

        // Search filter
        if (!empty($search)) {
            $ordersQuery->where(function ($q) use ($search) {
                $q->where('payment_method', 'LIKE', "%$search%")
                    ->orWhereHas('orderItems', function ($q2) use ($search) {
                        $q2->where('product_name', 'LIKE', "%$search%");
                    });
            });
        }

        $total = $ordersQuery->count();
        $orders = $ordersQuery->orderBy($sort, $order)->offset($offset)->limit($limit)->get();

        $total_amount = 0;
        $final_total_amount = 0;
        $total_delivery_charge = 0;
        $rows = [];

        foreach ($orders as $order) {
            $total_amount += (int) $order->total;
            $final_total_amount += (int) $order->final_total;
            $total_delivery_charge += (int) $order->delivery_charge;

            $item = $order->orderItems->first();
            // dd($item->sellerStore);
            $rows[] = [
                'id' => $order->id,
                'name' => $order->user->username ?? '',
                'total' => $order->total ?? '',
                'tax_amount' => $order->tax_amount ?? '',
                'discounted_price' => $item->discounted_price ?? '0',
                'delivery_charge' => $order->delivery_charge ?? '',
                'final_total' => $order->final_total ?? '',
                'payment_method' => $order->payment_method ?? '',
                'store_name' => $item->sellerStore->store_name ?? '',
                'seller_name' => $item->sellerData->user->username ?? '',
                'date_added' => Carbon::parse($order->created_at)->format('d-m-Y') ?? '',
            ];
        }

        if (count($rows) > 0) {
            return [
                'error' => false,
                'message' => "Data Retrieved Successfully",
                'total' => $total,
                'grand_total' => "$total_amount",
                'total_delivery_charge' => "$total_delivery_charge",
                'grand_final_total' => "$final_total_amount",
                'rows' => $rows,
            ];
        }

        return [
            'error' => true,
            'message' => "No data found",
            'total' => 0,
            'grand_total' => "0",
            'total_delivery_charge' => "0",
            'grand_final_total' => "0",
            'rows' => [],
        ];
    }
}
