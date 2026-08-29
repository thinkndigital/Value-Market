<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\CommissionRule;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\StoreAffiliateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Seller-facing side of the affiliate engine (docs/PHASE_7_AFFILIATE_ENGINE.md): until now only admin
 * could create a commission_rules row - a seller had no way to opt their own products into the program or
 * set their own rate. This is that self-service layer, plus the public/private catalog switch and the
 * approve/reject flow for private stores (see the 2025_02_09_000000 migration's docblock).
 *
 * Every method here scopes to the logged-in seller's own seller_id/store - never a client-supplied one, the
 * same ownership-check pattern applied throughout this app's other seller-panel controllers.
 */
class AffiliateProgramController extends Controller
{
    private function sellerId(): ?int
    {
        return Seller::where('user_id', Auth::id())->value('id');
    }

    private function sellerStore(): ?SellerStore
    {
        $sellerId = $this->sellerId();
        if (!$sellerId) {
            return null;
        }

        return SellerStore::where('seller_id', $sellerId)->first();
    }

    public function index()
    {
        $sellerId = $this->sellerId();
        $store = $this->sellerStore();

        $products = Product::where('seller_id', $sellerId)
            ->where('status', 1)
            ->orderByDesc('id')
            ->get();

        $productIds = $products->pluck('id')->all();
        $rules = CommissionRule::where('scope', CommissionRule::SCOPE_PRODUCT)
            ->whereIn('scope_id', $productIds)
            ->get()
            ->keyBy('scope_id');

        $requests = $store
            ? StoreAffiliateRequest::with('user')->where('store_id', $store->store_id)->orderByDesc('id')->get()
            : collect();

        return view('seller.pages.tables.affiliate_program', [
            'products' => $products,
            'rules' => $rules,
            'store' => $store,
            'requests' => $requests,
        ]);
    }

    /**
     * A product's commission_rules row is upserted (not deleted) on disable - the seller-chosen rate is
     * kept around so re-enabling doesn't ask them to re-enter it, mirroring how the platform/vendor-scope
     * rules Admin\CommissionRuleController manages already just flip `status`.
     */
    public function toggleProduct(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer',
            'enabled' => 'required|boolean',
            'rate_type' => 'required_if:enabled,1|in:percentage,flat',
            'rate_value' => 'required_if:enabled,1|numeric|gt:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $sellerId = $this->sellerId();
        $product = Product::where('id', $request->input('product_id'))->where('seller_id', $sellerId)->first();
        if (!$product) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.product_not_found', 'Product not found.')]);
        }

        $enabled = (bool) $request->input('enabled');

        $rule = CommissionRule::where('scope', CommissionRule::SCOPE_PRODUCT)->where('scope_id', $product->id)->first();

        if ($enabled) {
            $data = [
                'scope' => CommissionRule::SCOPE_PRODUCT,
                'scope_id' => $product->id,
                'rate_type' => $request->input('rate_type'),
                'rate_value' => $request->input('rate_value'),
                'status' => CommissionRule::STATUS_ACTIVE,
            ];
            if ($rule) {
                $rule->forceFill($data)->save();
            } else {
                CommissionRule::forceCreate($data);
            }
        } elseif ($rule) {
            $rule->status = CommissionRule::STATUS_INACTIVE;
            $rule->save();
        }

        return response()->json(['error' => false, 'message' => labels('admin_labels.affiliate_program_updated', 'Affiliate program setting updated.')]);
    }

    public function updateVisibility(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'affiliate_visibility' => 'required|in:public,private',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $store = $this->sellerStore();
        if (!$store) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.store_not_found', 'Store not found.')]);
        }

        $store->affiliate_visibility = $request->input('affiliate_visibility');
        $store->save();

        return response()->json(['error' => false, 'message' => labels('admin_labels.affiliate_visibility_updated', 'Affiliate program visibility updated.')]);
    }

    public function respondToRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_id' => 'required|integer',
            'status' => 'required|in:approved,rejected',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $store = $this->sellerStore();
        if (!$store) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.store_not_found', 'Store not found.')]);
        }

        $affiliateRequest = StoreAffiliateRequest::where('id', $request->input('request_id'))
            ->where('store_id', $store->store_id)
            ->first();
        if (!$affiliateRequest) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.request_not_found', 'Request not found.')]);
        }

        $affiliateRequest->status = $request->input('status');
        $affiliateRequest->save();

        return response()->json(['error' => false, 'message' => labels('admin_labels.request_updated', 'Request updated.')]);
    }
}
