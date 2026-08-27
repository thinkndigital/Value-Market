<?php

namespace App\Http\Controllers\Seller;

use App\Models\Branch;
use App\Models\PosShift;
use App\Services\PosShiftService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PosShiftController extends Controller
{
    public function list()
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        $shifts = PosShift::where('seller_id', $sellerId)->orderByDesc('id')->get();

        return response()->json(['error' => false, 'data' => $shifts]);
    }

    public function open(Request $request)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();
        if ($sellerId === null) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $validator = Validator::make($request->all(), [
            'branch_id' => 'nullable|integer',
            'opening_cash' => 'required|numeric|min:0',
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
            $shift = app(PosShiftService::class)->open($sellerId, $request->input('branch_id'), (int) Auth::id(), (float) $request->input('opening_cash'), $request->input('notes'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()]);
        }

        return response()->json(['error' => false, 'message' => labels('seller.shift_opened', 'Shift Opened Successfully'), 'data' => $shift]);
    }

    public function close(Request $request, $id)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        $shift = PosShift::where('id', $id)->where('seller_id', $sellerId)->first();
        if (!$shift) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $validator = Validator::make($request->all(), [
            'closing_cash' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        try {
            $shift = app(PosShiftService::class)->close($shift, (float) $request->input('closing_cash'), $request->input('notes'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()]);
        }

        return response()->json(['error' => false, 'message' => labels('seller.shift_closed', 'Shift Closed Successfully'), 'data' => $shift]);
    }
}
