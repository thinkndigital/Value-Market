<?php

namespace Tests\Feature\Phase7;

use App\Models\AffiliateLink;
use App\Models\CommissionRule;
use App\Models\ReferralConversion;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AffiliateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::forceCreate([
            'username' => 'user_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
            'balance' => 0,
        ]);
    }

    public function test_create_link_generates_a_unique_code(): void
    {
        $user = $this->makeUser();

        $link = app(AffiliateService::class)->createLink($user->id, AffiliateLink::TARGET_PLATFORM);

        $this->assertNotEmpty($link->code);
        $this->assertSame(AffiliateLink::STATUS_ACTIVE, $link->status);
    }

    public function test_track_click_logs_a_click_and_increments_the_counter(): void
    {
        $user = $this->makeUser();
        $link = app(AffiliateService::class)->createLink($user->id, AffiliateLink::TARGET_PLATFORM);

        $result = app(AffiliateService::class)->trackClick($link->code, '1.2.3.4', 'TestAgent', 'https://example.com');

        $this->assertNotNull($result);
        $this->assertSame(1, $link->fresh()->clicks_count);
        $this->assertSame(1, \App\Models\LinkClick::where('affiliate_link_id', $link->id)->count());
    }

    public function test_track_click_returns_null_for_an_unknown_code(): void
    {
        $this->assertNull(app(AffiliateService::class)->trackClick('does-not-exist'));
    }

    public function test_resolve_commission_rule_prefers_the_most_specific_scope(): void
    {
        $user = $this->makeUser();
        $link = app(AffiliateService::class)->createLink($user->id, AffiliateLink::TARGET_PRODUCT, 55);

        CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_PLATFORM, 'scope_id' => null, 'rate_type' => 'percentage', 'rate_value' => 2, 'status' => 1]);
        CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_VENDOR, 'scope_id' => 9, 'rate_type' => 'percentage', 'rate_value' => 5, 'status' => 1]);
        CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_PRODUCT, 'scope_id' => 55, 'rate_type' => 'percentage', 'rate_value' => 10, 'status' => 1]);

        $rule = app(AffiliateService::class)->resolveCommissionRule($link, productId: 55, sellerId: 9);

        $this->assertSame(CommissionRule::SCOPE_PRODUCT, $rule->scope);
        $this->assertSame(10.0, (float) $rule->rate_value);
    }

    public function test_resolve_commission_rule_falls_back_to_platform_when_nothing_more_specific_matches(): void
    {
        $user = $this->makeUser();
        $link = app(AffiliateService::class)->createLink($user->id, AffiliateLink::TARGET_PLATFORM);
        CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_PLATFORM, 'scope_id' => null, 'rate_type' => 'flat', 'rate_value' => 3, 'status' => 1]);

        $rule = app(AffiliateService::class)->resolveCommissionRule($link);

        $this->assertSame(CommissionRule::SCOPE_PLATFORM, $rule->scope);
    }

    public function test_resolve_commission_rule_returns_null_when_nothing_is_configured(): void
    {
        $user = $this->makeUser();
        $link = app(AffiliateService::class)->createLink($user->id, AffiliateLink::TARGET_PLATFORM);

        $this->assertNull(app(AffiliateService::class)->resolveCommissionRule($link));
    }

    public function test_record_conversion_computes_percentage_commission_and_marks_pending(): void
    {
        $user = $this->makeUser();
        $buyer = $this->makeUser();
        $link = app(AffiliateService::class)->createLink($user->id, AffiliateLink::TARGET_PLATFORM);
        CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_PLATFORM, 'scope_id' => null, 'rate_type' => 'percentage', 'rate_value' => 10, 'status' => 1]);

        $conversion = app(AffiliateService::class)->recordConversion($link->code, 1001, $buyer->id, 200.0);

        $this->assertNotNull($conversion);
        $this->assertSame(ReferralConversion::STATUS_PENDING, $conversion->status);
        $this->assertSame(20.0, (float) $conversion->commission_amount);
        $this->assertSame(1, $link->fresh()->conversions_count);
    }

    public function test_record_conversion_returns_null_when_no_commission_rule_is_configured(): void
    {
        $user = $this->makeUser();
        $link = app(AffiliateService::class)->createLink($user->id, AffiliateLink::TARGET_PLATFORM);

        $conversion = app(AffiliateService::class)->recordConversion($link->code, 1002, $user->id, 200.0);

        $this->assertNull($conversion);
        $this->assertSame(0, ReferralConversion::count());
    }

    public function test_approve_conversions_for_order_credits_the_affiliates_wallet_once(): void
    {
        $affiliate = $this->makeUser();
        $buyer = $this->makeUser();
        $link = app(AffiliateService::class)->createLink($affiliate->id, AffiliateLink::TARGET_PLATFORM);
        CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_PLATFORM, 'scope_id' => null, 'rate_type' => 'flat', 'rate_value' => 15, 'status' => 1]);
        app(AffiliateService::class)->recordConversion($link->code, 2001, $buyer->id, 100.0);

        app(AffiliateService::class)->approveConversionsForOrder(2001);

        $this->assertSame(15.0, (float) $affiliate->fresh()->balance);
        $this->assertSame(ReferralConversion::STATUS_APPROVED, ReferralConversion::where('order_id', 2001)->first()->status);

        // Calling it again for the same order must not pay twice.
        app(AffiliateService::class)->approveConversionsForOrder(2001);
        $this->assertSame(15.0, (float) $affiliate->fresh()->balance);
    }

    /**
     * Security audit finding (docs/SECURITY_AUDIT.md §6, Finding 3): an affiliate buying from their own
     * link must not be recorded as a conversion - otherwise they can pay themselves real wallet money on
     * their own purchases, repeatably, with the attacker controlling the order total.
     */
    public function test_a_self_referral_is_not_recorded_as_a_conversion(): void
    {
        $affiliate = $this->makeUser();
        $link = app(AffiliateService::class)->createLink($affiliate->id, AffiliateLink::TARGET_PLATFORM);
        CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_PLATFORM, 'scope_id' => null, 'rate_type' => 'percentage', 'rate_value' => 10, 'status' => 1]);

        $conversion = app(AffiliateService::class)->recordConversion($link->code, 3001, $affiliate->id, 200.0);

        $this->assertNull($conversion);
        $this->assertSame(0, ReferralConversion::where('order_id', 3001)->count());
    }

    /**
     * Security audit finding (docs/SECURITY_AUDIT.md §6, Finding 4): a return/cancellation after an
     * affiliate commission was already paid out must claw the money back, otherwise a colluding
     * buyer/affiliate pair can buy, get paid, then return for a full refund while keeping the commission.
     */
    public function test_reverse_conversions_for_order_debits_back_an_approved_commission(): void
    {
        $affiliate = $this->makeUser();
        $buyer = $this->makeUser();
        $link = app(AffiliateService::class)->createLink($affiliate->id, AffiliateLink::TARGET_PLATFORM);
        CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_PLATFORM, 'scope_id' => null, 'rate_type' => 'flat', 'rate_value' => 15, 'status' => 1]);
        app(AffiliateService::class)->recordConversion($link->code, 4001, $buyer->id, 100.0);
        app(AffiliateService::class)->approveConversionsForOrder(4001);
        $this->assertSame(15.0, (float) $affiliate->fresh()->balance);

        app(AffiliateService::class)->reverseConversionsForOrder(4001);

        $this->assertSame(0.0, (float) $affiliate->fresh()->balance);
        $this->assertSame(ReferralConversion::STATUS_REVERSED, ReferralConversion::where('order_id', 4001)->first()->status);

        // Calling it again for the same order must not debit twice.
        app(AffiliateService::class)->reverseConversionsForOrder(4001);
        $this->assertSame(0.0, (float) $affiliate->fresh()->balance);
    }

    public function test_reverse_conversions_for_order_is_a_no_op_when_nothing_was_approved(): void
    {
        // Never recorded/approved for this order id - must not throw or create anything.
        app(AffiliateService::class)->reverseConversionsForOrder(9999);
        $this->assertSame(0, ReferralConversion::where('order_id', 9999)->count());
    }
}
