<?php

namespace App\Services;

use App\Models\AffiliateLink;
use App\Models\CommissionRule;
use App\Models\LinkClick;
use App\Models\ReferralConversion;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 7 (docs/PHASE_7_AFFILIATE_ENGINE.md): trackable affiliate links, click tracking, and a configurable
 * commission rule engine. Deliberately separate from the pre-existing refer-a-friend wallet bonus
 * (app/function_helper.php's processReferralBonus()) - that feature is untouched.
 */
class AffiliateService
{
    public function createLink(int $userId, string $targetType, ?int $targetId = null): AffiliateLink
    {
        if (!in_array($targetType, [AffiliateLink::TARGET_PLATFORM, AffiliateLink::TARGET_STORE, AffiliateLink::TARGET_CATEGORY, AffiliateLink::TARGET_PRODUCT], true)) {
            throw new \InvalidArgumentException('Invalid affiliate link target type.');
        }

        do {
            $code = Str::lower(Str::random(8));
        } while (AffiliateLink::where('code', $code)->exists());

        return AffiliateLink::forceCreate([
            'user_id' => $userId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'code' => $code,
            'status' => AffiliateLink::STATUS_ACTIVE,
        ]);
    }

    public function trackClick(string $code, ?string $ip = null, ?string $userAgent = null, ?string $referrer = null): ?AffiliateLink
    {
        $link = AffiliateLink::where('code', $code)->where('status', AffiliateLink::STATUS_ACTIVE)->first();
        if (!$link) {
            return null;
        }

        DB::transaction(function () use ($link, $ip, $userAgent, $referrer) {
            LinkClick::forceCreate([
                'affiliate_link_id' => $link->id,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'referrer' => $referrer,
                'clicked_at' => now(),
            ]);
            $link->increment('clicks_count');
        });

        return $link;
    }

    /**
     * Most specific applicable rule wins - product > category > vendor > affiliate > platform (see
     * CommissionRule::SCOPE_PRECEDENCE). Returns null if nothing is configured at any level; callers treat
     * that as "no commission for this sale," not a zero-rate rule.
     */
    public function resolveCommissionRule(AffiliateLink $link, ?int $productId = null, ?int $categoryId = null, ?int $sellerId = null): ?CommissionRule
    {
        $candidates = [
            CommissionRule::SCOPE_PRODUCT => $productId,
            CommissionRule::SCOPE_CATEGORY => $categoryId,
            CommissionRule::SCOPE_VENDOR => $sellerId,
            CommissionRule::SCOPE_AFFILIATE => $link->user_id,
            CommissionRule::SCOPE_PLATFORM => null,
        ];

        foreach (CommissionRule::SCOPE_PRECEDENCE as $scope) {
            $scopeId = $candidates[$scope];
            if ($scope !== CommissionRule::SCOPE_PLATFORM && $scopeId === null) {
                continue;
            }

            $rule = CommissionRule::where('scope', $scope)
                ->where('status', CommissionRule::STATUS_ACTIVE)
                ->when($scope === CommissionRule::SCOPE_PLATFORM, fn ($q) => $q->whereNull('scope_id'), fn ($q) => $q->where('scope_id', $scopeId))
                ->first();

            if ($rule) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Attributes a completed order to an affiliate link and records its commission at 'pending' status -
     * money doesn't move yet (see approveConversionsForOrder()). Returns null (no conversion recorded, sale
     * still proceeds untracked) if the code is invalid/inactive or no commission rule resolves at any scope.
     */
    public function recordConversion(string $code, int $orderId, ?int $buyerUserId, float $orderTotal, ?int $productId = null, ?int $categoryId = null, ?int $sellerId = null): ?ReferralConversion
    {
        $link = AffiliateLink::where('code', $code)->where('status', AffiliateLink::STATUS_ACTIVE)->first();
        if (!$link) {
            return null;
        }

        // A link that specifically promotes a product/category carries that as its own commission context,
        // even if the caller (e.g. a multi-vendor cart checkout) can't cleanly derive one product/category
        // for the whole order - the link's own target is more meaningful than nothing.
        if ($productId === null && $link->target_type === AffiliateLink::TARGET_PRODUCT) {
            $productId = $link->target_id;
        }
        if ($categoryId === null && $link->target_type === AffiliateLink::TARGET_CATEGORY) {
            $categoryId = $link->target_id;
        }

        $rule = $this->resolveCommissionRule($link, $productId, $categoryId, $sellerId);
        if (!$rule) {
            return null;
        }

        $commissionAmount = $rule->rate_type === CommissionRule::RATE_PERCENTAGE
            ? round($orderTotal * ((float) $rule->rate_value / 100), 4)
            : (float) $rule->rate_value;

        return DB::transaction(function () use ($link, $orderId, $buyerUserId, $orderTotal, $rule, $commissionAmount) {
            $conversion = ReferralConversion::forceCreate([
                'affiliate_link_id' => $link->id,
                'order_id' => $orderId,
                'buyer_user_id' => $buyerUserId,
                'order_total' => $orderTotal,
                'commission_rate_type' => $rule->rate_type,
                'commission_rate_value' => $rule->rate_value,
                'commission_amount' => $commissionAmount,
                'status' => ReferralConversion::STATUS_PENDING,
            ]);
            $link->increment('conversions_count');

            return $conversion;
        });
    }

    /**
     * Approves every still-pending conversion for a delivered order and credits the affiliate's wallet -
     * mirrors the exact pattern app/function_helper.php's processReferralBonus() already uses (credit on
     * delivery, not on order placement, and an idempotency key so re-processing the same order never pays
     * twice). Reuses the existing wallet/Transaction system rather than a new ledger - Phase 9's unified
     * ledger is the natural home for a formal commission ledger later (see PHASE_7_AFFILIATE_ENGINE.md §5).
     */
    public function approveConversionsForOrder(int $orderId): void
    {
        $pending = ReferralConversion::where('order_id', $orderId)->where('status', ReferralConversion::STATUS_PENDING)->get();

        foreach ($pending as $conversion) {
            $referenceId = 'affiliate-commission-' . $conversion->id;
            if (Transaction::where('order_id', $referenceId)->exists()) {
                continue; // already processed - defensive, forceCreate below only runs once per conversion anyway
            }

            $conversion->status = ReferralConversion::STATUS_APPROVED;
            $conversion->approved_at = now();
            $conversion->save();

            app(WalletService::class)->updateWalletBalance(
                'credit',
                $conversion->link->user_id,
                (float) $conversion->commission_amount,
                'Affiliate commission credited',
                $referenceId
            );
        }
    }
}
