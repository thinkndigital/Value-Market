<?php

namespace App\Http\Controllers\Seller;

use App\Models\ProcurementVendor;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function list()
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        $suppliers = ProcurementVendor::where('seller_id', $sellerId)->orderByDesc('id')->get();

        return response()->json(['error' => false, 'data' => $suppliers]);
    }

    public function store(Request $request)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();
        if ($sellerId === null) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:256',
            'contact_person' => 'nullable|string|max:256',
            'phone' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:256',
            'address' => 'nullable|string|max:512',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $supplier = ProcurementVendor::forceCreate([
            'seller_id' => $sellerId,
            'name' => $request->input('name'),
            'contact_person' => $request->input('contact_person'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'status' => ProcurementVendor::STATUS_ACTIVE,
        ]);

        return response()->json(['error' => false, 'message' => labels('seller.supplier_added', 'Supplier Added Successfully'), 'data' => $supplier]);
    }

    public function update(Request $request, $id)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        $supplier = ProcurementVendor::where('id', $id)->where('seller_id', $sellerId)->first();
        if (!$supplier) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        // Security audit finding (docs/SECURITY_AUDIT.md §6, Finding 13): update() had no validation at all
        // (store() validates the same fields) - an email field wasn't required to look like an email, and
        // status could be filled with any value outside the STATUS_ACTIVE/STATUS_INACTIVE enum.
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:256',
            'contact_person' => 'nullable|string|max:256',
            'phone' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:256',
            'address' => 'nullable|string|max:512',
            'status' => ['sometimes', Rule::in([ProcurementVendor::STATUS_ACTIVE, ProcurementVendor::STATUS_INACTIVE])],
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $supplier->fill($request->only(['name', 'contact_person', 'phone', 'email', 'address', 'status']));
        $supplier->save();

        return response()->json(['error' => false, 'message' => labels('seller.supplier_updated', 'Supplier Updated Successfully'), 'data' => $supplier]);
    }

    public function destroy($id)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        $supplier = ProcurementVendor::where('id', $id)->where('seller_id', $sellerId)->first();
        if (!$supplier) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $supplier->delete();

        return response()->json(['error' => false, 'message' => labels('seller.supplier_deleted', 'Supplier Deleted Successfully')]);
    }
}
