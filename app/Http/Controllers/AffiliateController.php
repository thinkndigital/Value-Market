<?php

namespace App\Http\Controllers;

use App\Models\AffiliateLink;
use App\Models\PaymentRequest;
use App\Models\Product;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\MediaService;
use App\Services\WalletService;
use App\Traits\HandlesValidation;
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
    use HandlesValidation;

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
     * docs/CHANGELOG_FEATURE_AUDIT.md (v1.0.7, "Generate unique product referral links"): the backend for
     * this already fully existed (AffiliateService::createLink() already accepts target_type='product' and
     * store() above already routes it through) - what was missing was purely a way for an affiliate to find
     * a product id to generate a link for, since this repo has no customer-facing web storefront to browse
     * from. A minimal name-search endpoint for the affiliate portal's own "Generate a product link" widget.
     */
    public function searchProducts(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $products = Product::where('status', 1)
            ->when($search !== '', fn($query) => $query->where('name->en', 'like', '%' . $search . '%'))
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'name', 'image']);

        $data = $products->map(fn($product) => [
            'id' => $product->id,
            'name' => json_decode($product->name, true)['en'] ?? '',
            'image' => app(MediaService::class)->getMediaImageUrl($product->image),
        ]);

        return response()->json(['error' => false, 'data' => $data]);
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
            'balance' => Auth::user()->balance ?? 0,
        ]);
    }

    /**
     * Changelog v1.0.7 ("Admin can process affiliate payouts"): confirmed genuinely missing - no
     * AffiliatePayout/withdrawal-request flow existed for affiliates specifically. The admin side already
     * fully supports this with zero changes needed: PaymentRequest is a generic, user_id-scoped model (not
     * seller/delivery-boy specific), and Admin\PaymentRequestController::list()/update() already work for
     * any user_id - approving an affiliate's request needs no new admin code. What was missing was purely
     * the affiliate-facing self-service submission, so this mirrors Seller\PaymentRequestController::
     * add_withdrawal_request()'s pattern exactly (including its just-fixed IDOR fix: the authenticated
     * user's own id, never a client-supplied one), with payment_type='affiliate' so admin's existing
     * payment_type filter can distinguish it from seller/delivery_boy requests.
     *
     * Commission an affiliate has earned is already real wallet balance by the time it reaches here -
     * AffiliateService::approveConversionsForOrder() credits WalletService::updateWalletBalance() the
     * moment a conversion is approved (after delivery + the return window, per that method's own
     * commission-timing rule) - so this withdraws from the same balance/WalletService::updateBalance()
     * path every other panel's withdrawal flow already uses, not a separate affiliate-only ledger.
     */
    public function requestWithdrawal(Request $request)
    {
        $rules = [
            'payment_address' => 'required',
            'amount' => 'required|numeric|gt:0',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }

        $userId = Auth::id();
        $amount = $request->input('amount');
        $paymentAddress = $request->input('payment_address');

        $user = User::find($userId);
        if (!$user || $amount > $user->balance) {
            return response()->json([
                'error' => true,
                'message' => labels('affiliate.insufficient_balance_for_withdrawal', "You don't have enough balance to send this withdrawal request."),
            ]);
        }

        PaymentRequest::create([
            'user_id' => $userId,
            'payment_address' => $paymentAddress,
            'payment_type' => 'affiliate',
            'amount_requested' => $amount,
        ]);

        app(WalletService::class)->updateBalance($amount, $userId, 'deduct');

        return response()->json([
            'error' => false,
            'message' => labels('affiliate.withdrawal_request_sent', 'Withdrawal request sent successfully.'),
            'balance' => User::find($userId)->balance,
        ]);
    }

    /**
     * Per-conversion breakdown behind dashboard()'s aggregate approved/pending sums - an affiliate has had
     * no way to see which order earned them what beyond those two totals.
     */
    public function conversionsHistory()
    {
        $link = AffiliateLink::where('user_id', Auth::id())->first();
        if (!$link) {
            return response()->json(['error' => false, 'data' => []]);
        }

        $conversions = \App\Models\ReferralConversion::where('affiliate_link_id', $link->id)
            ->orderByDesc('id')
            ->get(['order_id', 'order_total', 'commission_amount', 'status', 'created_at']);

        return response()->json(['error' => false, 'data' => $conversions]);
    }

    public function withdrawalHistory(Request $request)
    {
        $requests = PaymentRequest::where('user_id', Auth::id())
            ->orderByDesc('id')
            ->get(['id', 'amount_requested', 'payment_address', 'status', 'remarks', 'created_at']);

        return response()->json(['error' => false, 'data' => $requests]);
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
