<?php

namespace App\Http\Controllers\Wholesaler;

use App\Models\SellerStore;
use App\Models\WholesaleOrder;
use App\Models\Wholesaler;
use App\Models\WholesalerProduct;
use App\Services\WholesaleOrderService;
use App\Traits\HandlesValidation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Order management for the Wholesaler panel (docs/WHOLESALER_MODULE.md v2): the incoming-orders queue every
 * seller purchase order lands in, plus a "Create Order" quick-entry action (the "POS" ask from the product
 * owner - a wholesaler logging a phone/in-person order on a seller's behalf, same accept-it-yourself
 * shortcut a seller's own POS gives them over the storefront checkout flow).
 */
class OrderController extends Controller
{
    use HandlesValidation;

    private function currentWholesaler(): Wholesaler
    {
        return Wholesaler::where('user_id', Auth::id())->firstOrFail();
    }

    public function index()
    {
        return view('wholesaler.pages.views.orders.index');
    }

    public function list(Request $request)
    {
        $wholesaler = $this->currentWholesaler();
        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 10);
        $statusFilter = $request->input('status_filter', '');

        $query = WholesaleOrder::with(['wholesalerProduct', 'seller.user', 'store'])
            ->where('wholesaler_id', $wholesaler->id)
            ->when($statusFilter !== '', fn($q) => $q->where('status', (int) $statusFilter));

        $total = $query->count();
        $orders = $query->orderBy('id', 'DESC')->skip($offset)->take($limit)->get();

        $rows = $orders->map(function ($o) {
            $name = json_decode(optional($o->wholesalerProduct)->name, true);
            return [
                'id' => $o->id,
                'product' => $name['en'] ?? '',
                'seller' => optional(optional($o->seller)->user)->username,
                'store' => optional($o->store)->store_name ?? optional($o->store)->slug,
                'quantity' => $o->quantity,
                'total_amount' => $o->total_amount,
                'status' => $this->statusBadge((int) $o->status),
                'payment_status' => (int) $o->payment_status === 1
                    ? '<span class="badge bg-success">' . labels('wholesaler_labels.paid', 'Paid') . '</span>'
                    : '<span class="badge bg-secondary">' . labels('wholesaler_labels.unpaid', 'Unpaid') . '</span>',
                'created_at' => $o->created_at?->format('Y-m-d H:i'),
                'operate' => $this->operateButtons($o),
            ];
        });

        return response()->json(['rows' => $rows, 'total' => $total]);
    }

    private function statusBadge(int $status): string
    {
        return match ($status) {
            WholesaleOrder::STATUS_PENDING => '<span class="badge bg-warning">' . labels('wholesaler_labels.pending', 'Pending') . '</span>',
            WholesaleOrder::STATUS_ACCEPTED => '<span class="badge bg-info">' . labels('wholesaler_labels.accepted', 'Accepted') . '</span>',
            WholesaleOrder::STATUS_SHIPPED => '<span class="badge bg-primary">' . labels('wholesaler_labels.shipped', 'Shipped') . '</span>',
            WholesaleOrder::STATUS_DELIVERED => '<span class="badge bg-success">' . labels('wholesaler_labels.delivered', 'Delivered') . '</span>',
            WholesaleOrder::STATUS_REJECTED => '<span class="badge bg-danger">' . labels('wholesaler_labels.rejected', 'Rejected') . '</span>',
            WholesaleOrder::STATUS_CANCELLED => '<span class="badge bg-secondary">' . labels('wholesaler_labels.cancelled', 'Cancelled') . '</span>',
            default => (string) $status,
        };
    }

    private function operateButtons(WholesaleOrder $o): string
    {
        $buttons = [];
        $base = route('wholesaler.orders.transition', ['id' => $o->id]);

        if ((int) $o->status === WholesaleOrder::STATUS_PENDING) {
            $buttons[] = '<a href="' . $base . '?to=accept" class="btn btn-sm btn-success wholesale-order-transition"><i class="bx bx-check"></i></a>';
            $buttons[] = '<a href="' . $base . '?to=reject" class="btn btn-sm btn-danger wholesale-order-transition"><i class="bx bx-x"></i></a>';
        } elseif ((int) $o->status === WholesaleOrder::STATUS_ACCEPTED) {
            $buttons[] = '<a href="' . $base . '?to=ship" class="btn btn-sm btn-primary wholesale-order-transition">' . labels('wholesaler_labels.mark_shipped', 'Mark Shipped') . '</a>';
        } elseif ((int) $o->status === WholesaleOrder::STATUS_SHIPPED) {
            $buttons[] = '<a href="' . $base . '?to=deliver" class="btn btn-sm btn-success wholesale-order-transition">' . labels('wholesaler_labels.mark_delivered', 'Mark Delivered') . '</a>';
        }

        if ((int) $o->payment_status === 0 && (int) $o->status !== WholesaleOrder::STATUS_REJECTED && (int) $o->status !== WholesaleOrder::STATUS_CANCELLED) {
            $buttons[] = '<a href="' . route('wholesaler.orders.mark_paid', ['id' => $o->id]) . '" class="btn btn-sm btn-outline-success wholesale-order-mark-paid">' . labels('wholesaler_labels.mark_paid', 'Mark Paid') . '</a>';
        }

        return implode(' ', $buttons);
    }

    public function transition(Request $request, $id, WholesaleOrderService $service)
    {
        $wholesaler = $this->currentWholesaler();
        $order = WholesaleOrder::where('wholesaler_id', $wholesaler->id)->find($id);
        if (!$order) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')], 404);
        }

        $to = $request->input('to');
        $allowed = [
            'accept' => [WholesaleOrder::STATUS_PENDING, WholesaleOrder::STATUS_ACCEPTED],
            'reject' => [WholesaleOrder::STATUS_PENDING, WholesaleOrder::STATUS_REJECTED],
            'ship' => [WholesaleOrder::STATUS_ACCEPTED, WholesaleOrder::STATUS_SHIPPED],
            'deliver' => [WholesaleOrder::STATUS_SHIPPED, WholesaleOrder::STATUS_DELIVERED],
        ];

        if (!isset($allowed[$to]) || (int) $order->status !== $allowed[$to][0]) {
            return response()->json(['error' => true, 'message' => labels('wholesaler_labels.invalid_transition', 'This order cannot be moved to that status right now.')], 422);
        }

        if ($to === 'deliver') {
            $service->fulfill($order);
        } else {
            $order->status = $allowed[$to][1];
            $order->save();
        }

        return response()->json(['message' => labels('wholesaler_labels.order_updated', 'Order updated successfully.')]);
    }

    public function markPaid($id)
    {
        $wholesaler = $this->currentWholesaler();
        $order = WholesaleOrder::where('wholesaler_id', $wholesaler->id)->find($id);
        if (!$order) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')], 404);
        }

        $order->payment_status = 1;
        $order->save();

        return response()->json(['message' => labels('wholesaler_labels.marked_paid', 'Order marked as paid.')]);
    }

    /** "POS" quick-entry: the wholesaler logs an order on a seller's behalf (phone/in-person), pre-accepted
     * since the wholesaler is the one creating it - no separate accept step needed. */
    public function createPage()
    {
        $wholesaler = $this->currentWholesaler();
        $products = WholesalerProduct::where('wholesaler_id', $wholesaler->id)->where('status', 1)->get();
        $sellerStores = SellerStore::with(['seller', 'user:id,username'])->where('status', 1)->get();

        return view('wholesaler.pages.views.orders.create', compact('products', 'sellerStores'));
    }

    public function store(Request $request)
    {
        $wholesaler = $this->currentWholesaler();

        $rules = [
            'wholesaler_product_id' => 'required|exists:wholesaler_products,id',
            'seller_store_id' => 'required|exists:seller_store,id',
            'quantity' => 'required|integer|min:1',
            'retail_price' => 'required|numeric|min:0',
        ];
        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }

        $product = WholesalerProduct::where('wholesaler_id', $wholesaler->id)->find($request->wholesaler_product_id);
        if (!$product) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')], 404);
        }

        $sellerStore = SellerStore::find($request->seller_store_id);
        if (!$sellerStore) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')], 404);
        }

        WholesaleOrder::create([
            'wholesaler_id' => $wholesaler->id,
            'wholesaler_product_id' => $product->id,
            'seller_id' => $sellerStore->seller_id,
            'store_id' => $sellerStore->store_id,
            'quantity' => $request->quantity,
            'unit_price' => $product->wholesale_price,
            'total_amount' => $product->wholesale_price * $request->quantity,
            'retail_price' => $request->retail_price,
            'status' => WholesaleOrder::STATUS_ACCEPTED,
            'wholesaler_note' => $request->wholesaler_note,
        ]);

        return response()->json(['message' => labels('wholesaler_labels.order_created', 'Order created and accepted.')]);
    }
}
