<?php

namespace App\Http\Controllers\Seller;

use App\Models\OrderItems;
use App\Services\CrmService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Phase 11 (docs/PHASE_11_CRM.md): a seller's private CRM view of their own customers - scoped via
 * TenantContext (never a request-supplied seller_id), and further scoped to customers who have actually
 * ordered from this seller (a seller can't note/tag an arbitrary platform user they've never sold to).
 */
class CrmController extends Controller
{
    private function customerBelongsToSeller(int $customerUserId, int $sellerId): bool
    {
        return OrderItems::where('user_id', $customerUserId)->where('seller_id', $sellerId)->exists();
    }

    public function addNote(Request $request)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();
        if ($sellerId === null) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $validator = Validator::make($request->all(), [
            'customer_user_id' => 'required|integer',
            'note' => 'required|string|max:2048',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $customerUserId = (int) $request->input('customer_user_id');
        if (!$this->customerBelongsToSeller($customerUserId, $sellerId)) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $note = app(CrmService::class)->addNote($customerUserId, (int) Auth::id(), $request->input('note'), $sellerId);

        return response()->json(['error' => false, 'message' => labels('seller.note_added', 'Note Added Successfully'), 'data' => $note]);
    }

    public function listNotes(Request $request, $customerUserId)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        if (!$this->customerBelongsToSeller((int) $customerUserId, $sellerId)) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        return response()->json(['error' => false, 'data' => app(CrmService::class)->listNotes((int) $customerUserId, $sellerId)]);
    }

    public function tagCustomer(Request $request)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();
        if ($sellerId === null) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $validator = Validator::make($request->all(), [
            'customer_user_id' => 'required|integer',
            'tag_name' => 'required|string|max:100',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $customerUserId = (int) $request->input('customer_user_id');
        if (!$this->customerBelongsToSeller($customerUserId, $sellerId)) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $crm = app(CrmService::class);
        $tag = $crm->createTag($request->input('tag_name'), $sellerId, $request->input('color'));
        $crm->tagCustomer($customerUserId, $tag->id, Auth::id());

        return response()->json(['error' => false, 'message' => labels('seller.tag_applied', 'Tag Applied Successfully'), 'data' => $tag]);
    }

    public function customerLifetimeValue(Request $request, $customerUserId)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        if (!$this->customerBelongsToSeller((int) $customerUserId, $sellerId)) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $clv = app(CrmService::class)->customerLifetimeValue((int) $customerUserId, $sellerId);

        return response()->json(['error' => false, 'data' => ['customer_user_id' => (int) $customerUserId, 'lifetime_value' => $clv]]);
    }
}
