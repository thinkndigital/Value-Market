<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 11 (docs/PHASE_11_SUBSCRIPTIONS.md): read-only - a seller sees their own current plan, its
 * limits/features, and when it expires. Changing plans is an admin action
 * (Admin\SubscriptionPlanController::assignToSeller()) - the product owner's ask was admin control, not
 * seller self-service upgrades, so this page has no "change plan" button, just a note to contact support.
 */
class SubscriptionController extends Controller
{
    public function index()
    {
        $seller = Seller::where('user_id', Auth::id())->first();
        $plan = $seller && $seller->subscription_plan_id
            ? SubscriptionPlan::find($seller->subscription_plan_id)
            : null;

        return view('seller.pages.tables.my_subscription', [
            'plan' => $plan,
            'seller' => $seller,
            'productCount' => $seller ? \App\Models\Product::where('seller_id', $seller->id)->count() : 0,
        ]);
    }
}
