<?php

namespace App\Http\Controllers\Admin;

use App\Models\Seller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Phase 11 (docs/PHASE_11_SUBSCRIPTIONS.md): admin-managed subscription tiers, seeded with 3 placeholder
 * defaults (Basic/Pro/Premium - 2025_02_20_000000_create_subscription_plans.php) the admin edits from
 * here. Mirrors Admin\CommissionRuleController's structure (index page / list JSON / store / update),
 * the closest existing analog - a small admin-managed pricing/rule table with the same shape.
 */
class SubscriptionPlanController extends Controller
{
    public function index()
    {
        return view('admin.pages.tables.subscription_plans');
    }

    public function list()
    {
        return response()->json([
            'error' => false,
            'data' => SubscriptionPlan::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:128',
            'billing_cycle' => 'required|in:monthly,yearly',
            'price' => 'required|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'max_products' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $slug = Str::slug($request->input('name'));
        if (SubscriptionPlan::where('slug', $slug)->exists()) {
            $slug .= '-' . uniqid();
        }

        $plan = SubscriptionPlan::forceCreate([
            'name' => $request->input('name'),
            'slug' => $slug,
            'billing_cycle' => $request->input('billing_cycle'),
            'price' => $request->input('price'),
            'commission_rate' => $request->input('commission_rate'),
            'max_products' => $request->input('max_products'),
            'description' => $request->input('description'),
            'features' => array_values(array_filter($request->input('features', []))),
            'status' => SubscriptionPlan::STATUS_ACTIVE,
            'sort_order' => $request->input('sort_order', 0),
        ]);

        auditLog('subscription_plan.created', ['plan_id' => $plan->id, 'name' => $plan->name, 'price' => (float) $plan->price]);

        return response()->json(['error' => false, 'message' => labels('admin_labels.subscription_plan_created', 'Subscription Plan Created Successfully'), 'data' => $plan]);
    }

    public function update(Request $request, $id)
    {
        $plan = SubscriptionPlan::find($id);
        if (!$plan) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.data_not_found', 'Data Not Found')]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:128',
            'billing_cycle' => 'sometimes|in:monthly,yearly',
            'price' => 'sometimes|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'max_products' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'nullable|in:0,1',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $before = $plan->only(['name', 'price', 'commission_rate', 'max_products', 'status']);

        $data = $request->only(['name', 'billing_cycle', 'price', 'commission_rate', 'max_products', 'description', 'sort_order', 'status']);
        if ($request->has('features')) {
            $data['features'] = array_values(array_filter($request->input('features', [])));
        }
        $plan->fill($data);
        $plan->save();

        auditLog('subscription_plan.updated', ['plan_id' => $plan->id, 'before' => $before, 'after' => $plan->only(['name', 'price', 'commission_rate', 'max_products', 'status'])]);

        return response()->json(['error' => false, 'message' => labels('admin_labels.subscription_plan_updated', 'Subscription Plan Updated Successfully'), 'data' => $plan]);
    }

    /** Blocked when any seller is currently on this plan - deleting out from under them would silently null their plan reference. */
    public function destroy($id)
    {
        $plan = SubscriptionPlan::find($id);
        if (!$plan) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.data_not_found', 'Data Not Found')]);
        }

        $sellerCount = Seller::where('subscription_plan_id', $plan->id)->count();
        if ($sellerCount > 0) {
            return response()->json([
                'error' => true,
                'message' => labels('admin_labels.subscription_plan_in_use', 'Cannot delete: ' . $sellerCount . ' seller(s) are currently on this plan.'),
            ]);
        }

        auditLog('subscription_plan.deleted', ['plan_id' => $plan->id, 'name' => $plan->name]);
        $plan->delete();

        return response()->json(['error' => false, 'message' => labels('admin_labels.subscription_plan_deleted', 'Subscription Plan Deleted Successfully')]);
    }

    /** Assigns (or clears, when plan_id is empty) a seller's subscription plan - the admin-controlled side of Phase 11. */
    public function assignToSeller(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'seller_id' => 'required|integer',
            'plan_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $seller = Seller::find($request->input('seller_id'));
        if (!$seller) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.seller_not_found', 'Seller not found.')]);
        }

        $planId = $request->input('plan_id');
        if ($planId) {
            $plan = SubscriptionPlan::find($planId);
            if (!$plan) {
                return response()->json(['error' => true, 'message' => labels('admin_labels.data_not_found', 'Data Not Found')]);
            }
            $seller->subscription_plan_id = $plan->id;
            $seller->subscription_started_at = now();
            $seller->subscription_expires_at = $plan->billing_cycle === SubscriptionPlan::BILLING_YEARLY ? now()->addYear() : now()->addMonth();
        } else {
            $seller->subscription_plan_id = null;
            $seller->subscription_started_at = null;
            $seller->subscription_expires_at = null;
        }
        $seller->save();

        auditLog('subscription_plan.assigned', ['seller_id' => $seller->id, 'plan_id' => $planId]);

        return response()->json(['error' => false, 'message' => labels('admin_labels.subscription_assigned', 'Seller subscription updated.')]);
    }
}
