<?php

namespace App\Http\Controllers\Admin;

use App\Models\CommissionRule;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

/**
 * Phase 7 (docs/PHASE_7_AFFILIATE_ENGINE.md): admin-managed commission rules -
 * AffiliateService::resolveCommissionRule() reads these to price a conversion.
 */
class CommissionRuleController extends Controller
{
    public function list()
    {
        return response()->json(['error' => false, 'data' => CommissionRule::orderByDesc('id')->get()]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scope' => 'required|in:platform,vendor,affiliate,category,product',
            'scope_id' => 'nullable|integer',
            'rate_type' => 'required|in:percentage,flat',
            'rate_value' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }
        if ($request->input('scope') !== CommissionRule::SCOPE_PLATFORM && !$request->filled('scope_id')) {
            return response()->json(['error' => true, 'message' => 'scope_id is required for every scope except platform.']);
        }

        $rule = CommissionRule::forceCreate([
            'scope' => $request->input('scope'),
            'scope_id' => $request->input('scope') === CommissionRule::SCOPE_PLATFORM ? null : $request->input('scope_id'),
            'rate_type' => $request->input('rate_type'),
            'rate_value' => $request->input('rate_value'),
            'status' => CommissionRule::STATUS_ACTIVE,
        ]);

        // Phase 15 (docs/SECURITY_AUDIT.md): commission_rules has no built-in history/versioning, and a
        // rate change silently affects every future affiliate payout platform-wide - worth a record, same
        // class of event Phase 2 already logs for privilege-boundary changes.
        auditLog('commission_rule.created', ['rule_id' => $rule->id, 'scope' => $rule->scope, 'scope_id' => $rule->scope_id, 'rate_type' => $rule->rate_type, 'rate_value' => (float) $rule->rate_value]);

        return response()->json(['error' => false, 'message' => labels('admin_labels.commission_rule_created', 'Commission Rule Created Successfully'), 'data' => $rule]);
    }

    public function update(Request $request, $id)
    {
        $rule = CommissionRule::find($id);
        if (!$rule) {
            return response()->json(['error' => true, 'message' => labels('admin_labels.data_not_found', 'Data Not Found')]);
        }

        $validator = Validator::make($request->all(), [
            'rate_type' => 'sometimes|in:percentage,flat',
            'rate_value' => 'sometimes|numeric|min:0',
            'status' => 'nullable|in:0,1',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        $before = ['rate_type' => $rule->rate_type, 'rate_value' => (float) $rule->rate_value, 'status' => $rule->status];
        $rule->fill($request->only(['rate_type', 'rate_value', 'status']));
        $rule->save();

        auditLog('commission_rule.updated', ['rule_id' => $rule->id, 'before' => $before, 'after' => ['rate_type' => $rule->rate_type, 'rate_value' => (float) $rule->rate_value, 'status' => $rule->status]]);

        return response()->json(['error' => false, 'message' => labels('admin_labels.commission_rule_updated', 'Commission Rule Updated Successfully'), 'data' => $rule]);
    }
}
