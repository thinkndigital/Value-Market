<?php

namespace App\Http\Controllers\Wholesaler;

use App\Models\Wholesaler;
use App\Models\WholesalerSellerRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Master architecture prompt Phase 6 (Supplier architecture, section 18 "Sellers" group: Explore Sellers /
 * Seller Requests / Approved Sellers / Pending Sellers). Mirrors Seller\AffiliateProgramController's
 * public/private visibility + request/approve flow exactly, one level up: a wholesaler that switches to
 * 'private' only shows its marketplace listing to sellers it has approved. Public (the default, and every
 * pre-existing wholesaler/test) is unchanged - open to any seller, same as before this pass.
 */
class SellerRequestController extends Controller
{
    private function currentWholesaler(): Wholesaler
    {
        return Wholesaler::where('user_id', Auth::id())->firstOrFail();
    }

    public function index()
    {
        $wholesaler = $this->currentWholesaler();
        $requests = WholesalerSellerRequest::with('seller.user')
            ->where('wholesaler_id', $wholesaler->id)
            ->orderByDesc('id')
            ->get();

        return view('wholesaler.pages.views.seller_requests.index', compact('wholesaler', 'requests'));
    }

    public function updateVisibility(Request $request)
    {
        $validator = Validator::make($request->all(), ['buyer_visibility' => 'required|in:public,private']);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $wholesaler = $this->currentWholesaler();
        $wholesaler->buyer_visibility = $request->input('buyer_visibility');
        $wholesaler->save();

        return response()->json(['error' => false, 'message' => labels('wholesaler_labels.visibility_updated', 'Marketplace visibility updated.')]);
    }

    public function respond(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'request_id' => 'required|integer',
            'status' => 'required|in:approved,rejected',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $wholesaler = $this->currentWholesaler();
        $sellerRequest = WholesalerSellerRequest::where('id', $request->input('request_id'))
            ->where('wholesaler_id', $wholesaler->id)
            ->first();
        if (!$sellerRequest) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $sellerRequest->status = $request->input('status');
        $sellerRequest->save();

        return response()->json(['error' => false, 'message' => labels('wholesaler_labels.request_updated', 'Request updated.')]);
    }
}
