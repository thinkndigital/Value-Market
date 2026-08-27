<?php

namespace App\Http\Controllers\Seller;

use App\Models\Supplier;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    public function list()
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        $suppliers = Supplier::where('seller_id', $sellerId)->orderByDesc('id')->get();

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

        $supplier = Supplier::forceCreate([
            'seller_id' => $sellerId,
            'name' => $request->input('name'),
            'contact_person' => $request->input('contact_person'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'status' => Supplier::STATUS_ACTIVE,
        ]);

        return response()->json(['error' => false, 'message' => labels('seller.supplier_added', 'Supplier Added Successfully'), 'data' => $supplier]);
    }

    public function update(Request $request, $id)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        $supplier = Supplier::where('id', $id)->where('seller_id', $sellerId)->first();
        if (!$supplier) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $supplier->fill($request->only(['name', 'contact_person', 'phone', 'email', 'address', 'status']));
        $supplier->save();

        return response()->json(['error' => false, 'message' => labels('seller.supplier_updated', 'Supplier Updated Successfully'), 'data' => $supplier]);
    }

    public function destroy($id)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        $supplier = Supplier::where('id', $id)->where('seller_id', $sellerId)->first();
        if (!$supplier) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $supplier->delete();

        return response()->json(['error' => false, 'message' => labels('seller.supplier_deleted', 'Supplier Deleted Successfully')]);
    }
}
