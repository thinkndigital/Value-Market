<?php

namespace App\Http\Controllers\Seller;

use App\Models\Branch;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderController extends Controller
{
    public function list()
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        $orders = PurchaseOrder::with(['items', 'supplier:id,name', 'branch:id,name'])
            ->where('seller_id', $sellerId)
            ->orderByDesc('id')
            ->get();

        return response()->json(['error' => false, 'data' => $orders]);
    }

    public function store(Request $request)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();
        if ($sellerId === null) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|integer',
            'branch_id' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.product_variant_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $ownsSupplier = Supplier::where('id', $request->input('supplier_id'))->where('seller_id', $sellerId)->exists();
        if (!$ownsSupplier) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }
        if ($request->filled('branch_id')) {
            $ownsBranch = Branch::where('id', $request->input('branch_id'))->where('seller_id', $sellerId)->exists();
            if (!$ownsBranch) {
                return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
            }
        }

        try {
            $po = app(PurchaseOrderService::class)->create(
                $sellerId,
                (int) $request->input('supplier_id'),
                $request->input('branch_id'),
                $request->input('items'),
                ['created_by' => Auth::id()]
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()]);
        }

        return response()->json(['error' => false, 'message' => labels('seller.purchase_order_created', 'Purchase Order Created Successfully'), 'data' => $po->load('items')]);
    }

    public function receive(Request $request, $id)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        $po = PurchaseOrder::where('id', $id)->where('seller_id', $sellerId)->first();
        if (!$po) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $validator = Validator::make($request->all(), [
            'branch_id' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|integer',
            'items.*.quantity_received' => 'required|integer|min:1',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        if ($request->filled('branch_id')) {
            $ownsBranch = Branch::where('id', $request->input('branch_id'))->where('seller_id', $sellerId)->exists();
            if (!$ownsBranch) {
                return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
            }
        }

        try {
            $grn = app(PurchaseOrderService::class)->receiveGoods(
                $po,
                $request->input('branch_id'),
                $request->input('items'),
                Auth::id(),
                $request->input('notes')
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()]);
        }

        return response()->json(['error' => false, 'message' => labels('seller.goods_received', 'Goods Received Successfully'), 'data' => $grn->load('items')]);
    }
}
