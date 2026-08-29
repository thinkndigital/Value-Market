<?php

namespace App\Http\Controllers;

use App\Models\AffiliateLink;
use App\Services\AffiliateService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Phase 7 (docs/PHASE_7_AFFILIATE_ENGINE.md): self-service affiliate links - any authenticated user
 * (customer, seller, or a dedicated affiliate account) can create one, not scoped to a single panel the way
 * Seller\* controllers are.
 */
class AffiliateController extends Controller
{
    public function list()
    {
        $links = AffiliateLink::where('user_id', Auth::id())->orderByDesc('id')->get();

        return response()->json(['error' => false, 'data' => $links]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target_type' => 'required|in:platform,store,category,product',
            'target_id' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        try {
            $link = app(AffiliateService::class)->createLink(Auth::id(), $request->input('target_type'), $request->input('target_id'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()]);
        }

        return response()->json(['error' => false, 'message' => labels('affiliate.link_created', 'Affiliate Link Created Successfully'), 'data' => $link]);
    }

    /**
     * The affiliate portal's own dashboard (AffiliateAuthController handles its login) - a self-service
     * summary of the logged-in user's own standing: their link (auto-created on first visit, one per user,
     * platform-wide - matching the reference eShop Plus product's own "one affiliate account, one link"
     * shape rather than this engine's more general per-target-type links), clicks/conversions, and
     * approved/pending commission. Read-only same as Admin\AffiliateController's report - a conversion's
     * status is still only ever changed by the order lifecycle
     * (AffiliateService::approveConversionsForOrder()/reverseConversionsForOrder()).
     */
    public function dashboard()
    {
        $userId = Auth::id();
        $link = AffiliateLink::where('user_id', $userId)->first();
        if (!$link) {
            $link = app(AffiliateService::class)->createLink($userId, AffiliateLink::TARGET_PLATFORM);
        }

        $conversions = \App\Models\ReferralConversion::where('affiliate_link_id', $link->id)
            ->selectRaw('status, SUM(commission_amount) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('affiliate.dashboard', [
            'link' => $link,
            'shareUrl' => route('affiliate.track', ['code' => $link->code]),
            'approvedCommission' => $conversions[\App\Models\ReferralConversion::STATUS_APPROVED] ?? 0,
            'pendingCommission' => $conversions[\App\Models\ReferralConversion::STATUS_PENDING] ?? 0,
        ]);
    }

    /**
     * Public - not behind auth. This is the link a visitor actually clicks; tracking their click and
     * redirecting them onward doesn't require them to have an account.
     */
    public function trackAndRedirect(Request $request, string $code)
    {
        $link = app(AffiliateService::class)->trackClick($code, $request->ip(), $request->userAgent(), $request->headers->get('referer'));

        if (!$link) {
            return redirect('/');
        }

        $destination = match ($link->target_type) {
            AffiliateLink::TARGET_PRODUCT => '/product/' . $link->target_id,
            AffiliateLink::TARGET_STORE => '/store/' . $link->target_id,
            AffiliateLink::TARGET_CATEGORY => '/category/' . $link->target_id,
            default => '/',
        };

        // affiliate_code rides along so the eventual checkout (OrderService::placeOrder()) can attribute
        // the sale - the storefront's own checkout flow is responsible for carrying it from here through to
        // that request, same as any other query-string-driven referral pattern.
        return redirect($destination . '?affiliate_code=' . urlencode($code));
    }
}
