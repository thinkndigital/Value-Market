<?php

namespace App\Http\Controllers\Seller;

use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\WholesaleOrder;
use App\Models\WholesalerProduct;
use App\Services\MediaService;
use App\Services\StoreService;
use App\Traits\HandlesValidation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * "Browse/order" half of the Wholesaler Marketplace (see docs/WHOLESALER_MODULE.md v2): a seller browses
 * active (admin-approved) wholesaler_products rows and places a real purchase order for the ones they want
 * to stock - quantity, their own chosen resale price. This does NOT touch the wholesaler's own listing or
 * the seller's own catalog directly; it creates a `WholesaleOrder` row the wholesaler must accept and
 * fulfill. Only on fulfillment (Wholesaler\OrderController::fulfill(), via
 * App\Services\WholesaleOrderService) does the seller's own Product get created/restocked, reusing all
 * existing product/order/storefront machinery unchanged - same as v1's direct import did, just gated behind
 * a real order now instead of happening the instant a seller clicked a button.
 */
class WholesalerMarketplaceController extends Controller
{
    use HandlesValidation;

    private function currentSeller(): ?Seller
    {
        return Seller::where('user_id', Auth::id())->first();
    }

    public function index()
    {
        return view('seller.pages.views.wholesaler_marketplace.index');
    }

    public function list(Request $request)
    {
        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 12);
        $search = trim((string) $request->input('search', ''));
        $seller = $this->currentSeller();

        $query = WholesalerProduct::with('wholesaler')
            ->where('status', 1) // admin-approved only
            ->when($search !== '', function ($q) use ($search) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) like ?", ["%$search%"]);
            });

        $total = $query->count();
        $products = $query->orderBy('id', 'DESC')->skip($offset)->take($limit)->get();

        $rows = $products->map(function ($p) use ($seller) {
            $name = json_decode($p->name, true);
            // The price shown/pre-filled at the listing's own minimum quantity - master architecture Phase
            // 6 pricing tiers (WholesalerProduct::priceFor()) may make this lower than wholesale_price;
            // the authoritative per-quantity price is always recomputed server-side at order time.
            $priceAtMoq = $p->priceFor($seller?->id ?? 0, (int) $p->min_order_qty);

            return [
                'id' => $p->id,
                'image' => '<img src="' . app(MediaService::class)->getMediaImageUrl($p->image) . '" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">',
                'name' => $name['en'] ?? '',
                'wholesaler' => optional($p->wholesaler)->business_name,
                'wholesale_price' => $priceAtMoq,
                'min_order_qty' => $p->min_order_qty,
                'stock' => $p->stock,
                'operate' => '<button type="button" class="btn btn-sm btn-primary place-wholesale-order" data-id="' . $p->id . '" data-price="' . $priceAtMoq . '" data-min-qty="' . $p->min_order_qty . '">' . labels('wholesaler_labels.place_order', 'Place Order') . '</button>',
            ];
        });

        return response()->json(['rows' => $rows, 'total' => $total]);
    }

    /** Live price preview as the seller changes quantity in the order modal (master architecture Phase 6
     *  pricing tiers) - the client-side number is just a preview; placeOrder() recomputes authoritatively. */
    public function previewPrice(Request $request, $id)
    {
        $wholesalerProduct = WholesalerProduct::where('status', 1)->find($id);
        if (!$wholesalerProduct) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')], 404);
        }

        $quantity = max(1, (int) $request->input('quantity', $wholesalerProduct->min_order_qty));
        $seller = $this->currentSeller();
        $unitPrice = $wholesalerProduct->priceFor($seller?->id ?? 0, $quantity);

        return response()->json([
            'unit_price' => $unitPrice,
            'total_amount' => round($unitPrice * $quantity, 4),
        ]);
    }

    public function placeOrder(Request $request, $id)
    {
        $wholesalerProduct = WholesalerProduct::where('status', 1)->find($id);
        if (!$wholesalerProduct) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')], 404);
        }

        $rules = [
            'quantity' => 'required|integer|min:' . max(1, (int) $wholesalerProduct->min_order_qty),
            'retail_price' => 'required|numeric|min:0',
            'seller_note' => 'nullable|string|max:1000',
        ];
        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }

        $storeId = app(StoreService::class)->getStoreId();
        if (!SellerStore::where('user_id', Auth::id())->where('store_id', $storeId)->exists()) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')], 404);
        }
        $seller = $this->currentSeller();
        $unitPrice = $wholesalerProduct->priceFor($seller->id, (int) $request->quantity);

        $order = WholesaleOrder::create([
            'wholesaler_id' => $wholesalerProduct->wholesaler_id,
            'wholesaler_product_id' => $wholesalerProduct->id,
            'seller_id' => $seller->id,
            'store_id' => $storeId,
            'quantity' => $request->quantity,
            'unit_price' => $unitPrice,
            'total_amount' => round($unitPrice * $request->quantity, 4),
            'retail_price' => $request->retail_price,
            'status' => WholesaleOrder::STATUS_PENDING,
            'seller_note' => $request->seller_note,
        ]);

        return response()->json(['message' => labels('wholesaler_labels.order_placed', 'Order placed successfully. The wholesaler will review and confirm it.'), 'id' => $order->id]);
    }

    public function myOrdersPage()
    {
        return view('seller.pages.views.wholesaler_marketplace.orders');
    }

    public function myOrdersList(Request $request)
    {
        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 10);
        $seller = $this->currentSeller();

        $query = WholesaleOrder::with(['wholesalerProduct', 'wholesaler'])->where('seller_id', $seller?->id);
        $total = $query->count();
        $orders = $query->orderBy('id', 'DESC')->skip($offset)->take($limit)->get();

        $statusLabels = [
            WholesaleOrder::STATUS_PENDING => '<span class="badge bg-warning">' . labels('wholesaler_labels.pending', 'Pending') . '</span>',
            WholesaleOrder::STATUS_ACCEPTED => '<span class="badge bg-info">' . labels('wholesaler_labels.accepted', 'Accepted') . '</span>',
            WholesaleOrder::STATUS_SHIPPED => '<span class="badge bg-primary">' . labels('wholesaler_labels.shipped', 'Shipped') . '</span>',
            WholesaleOrder::STATUS_DELIVERED => '<span class="badge bg-success">' . labels('wholesaler_labels.delivered', 'Delivered') . '</span>',
            WholesaleOrder::STATUS_REJECTED => '<span class="badge bg-danger">' . labels('wholesaler_labels.rejected', 'Rejected') . '</span>',
            WholesaleOrder::STATUS_CANCELLED => '<span class="badge bg-secondary">' . labels('wholesaler_labels.cancelled', 'Cancelled') . '</span>',
        ];

        $rows = $orders->map(function ($o) use ($statusLabels) {
            $name = json_decode(optional($o->wholesalerProduct)->name, true);
            return [
                'id' => $o->id,
                'product' => $name['en'] ?? '',
                'wholesaler' => optional($o->wholesaler)->business_name,
                'quantity' => $o->quantity,
                'total_amount' => $o->total_amount,
                'status' => $statusLabels[(int) $o->status] ?? $o->status,
                'created_at' => $o->created_at?->format('Y-m-d H:i'),
                'operate' => (int) $o->status === WholesaleOrder::STATUS_PENDING
                    ? '<button type="button" class="btn btn-sm btn-outline-danger cancel-wholesale-order" data-id="' . $o->id . '">' . labels('admin_labels.cancel', 'Cancel') . '</button>'
                    : '',
            ];
        });

        return response()->json(['rows' => $rows, 'total' => $total]);
    }

    public function cancelOrder($id)
    {
        $seller = $this->currentSeller();
        $order = WholesaleOrder::where('seller_id', $seller?->id)->where('status', WholesaleOrder::STATUS_PENDING)->find($id);

        if (!$order) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')], 404);
        }

        $order->status = WholesaleOrder::STATUS_CANCELLED;
        $order->save();

        return response()->json(['message' => labels('wholesaler_labels.order_cancelled', 'Order cancelled.')]);
    }
}
