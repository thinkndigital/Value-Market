<?php

namespace App\Http\Controllers\Seller;

use App\Models\Branch;
use App\Models\Employee;
use App\Services\EmployeeService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

/**
 * Phase 4 (docs/PHASE_4_VENDOR_SYSTEM.md): lets a seller manage their own staff roster. See
 * docs/PHASE_4_VENDOR_SYSTEM.md for the explicit boundary on what an employee can do once logged in today
 * (this controller itself, and anything else routed through TenantContext, work correctly for them - the
 * ~90 pre-existing Seller-panel controllers that inline the seller lookup instead of using TenantContext do
 * not yet).
 */
class EmployeeController extends Controller
{
    public function list()
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        $employees = Employee::with(['user:id,username,mobile,email', 'branch:id,name'])
            ->where('seller_id', $sellerId)
            ->orderByDesc('id')
            ->get();

        return response()->json(['error' => false, 'data' => $employees]);
    }

    public function store(Request $request)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();
        if ($sellerId === null) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'mobile' => 'required|string|unique:users,mobile',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:8',
            'position' => 'nullable|string|max:256',
            'branch_id' => 'nullable|integer',
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

        $employee = app(EmployeeService::class)->create($sellerId, $request->only(['name', 'mobile', 'email', 'password', 'position', 'branch_id']));

        return response()->json(['error' => false, 'message' => labels('seller.employee_added', 'Employee Added Successfully'), 'data' => $employee]);
    }

    public function update(Request $request, $id)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        $employee = Employee::where('id', $id)->where('seller_id', $sellerId)->first();
        if (!$employee) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        $validator = Validator::make($request->all(), [
            'position' => 'nullable|string|max:256',
            'branch_id' => 'nullable|integer',
            'status' => 'nullable|in:0,1',
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

        $employee->fill($request->only(['position', 'branch_id', 'status']));
        $employee->save();

        return response()->json(['error' => false, 'message' => labels('seller.employee_updated', 'Employee Updated Successfully'), 'data' => $employee]);
    }

    public function destroy($id)
    {
        $sellerId = app(TenantContext::class)->currentSellerId();

        $employee = Employee::where('id', $id)->where('seller_id', $sellerId)->first();
        if (!$employee) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')]);
        }

        // Deactivate rather than delete the login-capable user - deleting it here would silently strip a
        // real users row (order/audit history may reference it) as a side effect of removing the employee
        // *assignment*, which isn't what "delete employee" should mean.
        $employee->status = Employee::STATUS_INACTIVE;
        $employee->save();

        return response()->json(['error' => false, 'message' => labels('seller.employee_deactivated', 'Employee Deactivated Successfully')]);
    }
}
