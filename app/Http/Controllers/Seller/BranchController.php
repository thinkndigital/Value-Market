<?php

namespace App\Http\Controllers\Seller;

use App\Models\Branch;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

/**
 * Phase 4 (docs/PHASE_4_VENDOR_SYSTEM.md): lets a seller manage their own physical locations. Scoped via
 * TenantContext (not a raw inline `Seller::where('user_id', ...)` query) since this is new Phase 4 code -
 * see TenantContext::sellerIdFor()'s docblock for why that matters for employee logins.
 */
class BranchController extends Controller
{
    public function list()
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        $branches = Branch::where('seller_id', $sellerId)->orderByDesc('id')->get();

        return response()->json(['error' => false, 'data' => $branches]);
    }

    public function store(Request $request)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();
        if ($sellerId === null) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        // Security audit finding (docs/SECURITY_AUDIT.md §6, Finding 14): latitude/longitude were accepted
        // and stored without any validation at all - not range-checked, not even type-checked.
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:256',
            'address' => 'nullable|string|max:512',
            'city' => 'nullable|integer',
            'zipcode' => 'nullable|integer',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:32',
            'is_default' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $branch = Branch::forceCreate([
            'seller_id' => $sellerId,
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'city' => $request->input('city'),
            'zipcode' => $request->input('zipcode'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'phone' => $request->input('phone'),
            'is_default' => (bool) $request->input('is_default', false),
            'status' => Branch::STATUS_ACTIVE,
        ]);

        return response()->json(['error' => false, 'message' => labels('seller.branch_added', 'Branch Added Successfully'), 'data' => $branch]);
    }

    public function update(Request $request, $id)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        $branch = Branch::where('id', $id)->where('seller_id', $sellerId)->first();
        if (!$branch) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        // Security audit finding (docs/SECURITY_AUDIT.md §6, Finding 14): see store() above.
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:256',
            'address' => 'nullable|string|max:512',
            'city' => 'nullable|integer',
            'zipcode' => 'nullable|integer',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:32',
            'status' => 'nullable|in:0,1',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $branch->fill($request->only(['name', 'address', 'city', 'zipcode', 'latitude', 'longitude', 'phone', 'status']));
        $branch->save();

        return response()->json(['error' => false, 'message' => labels('seller.branch_updated', 'Branch Updated Successfully'), 'data' => $branch]);
    }

    public function destroy($id)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        $branch = Branch::where('id', $id)->where('seller_id', $sellerId)->first();
        if (!$branch) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $branch->delete();

        return response()->json(['error' => false, 'message' => labels('seller.branch_deleted', 'Branch Deleted Successfully')]);
    }
}
