<?php

namespace Tests\Feature\Phase16;

use App\Models\AffiliateLink;
use App\Models\CommissionRule;
use App\Models\ReferralConversion;
use App\Models\Role;
use App\Models\User;
use App\Services\AffiliateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 16 (docs/PHASE_16_PERFORMANCE_OPTIMIZATION.md): approveConversionsForOrder()/
 * reverseConversionsForOrder() read $conversion->link->user_id inside a loop over every conversion for an
 * order - without eager-loading, each row fires its own AffiliateLink query (N+1). Proves the fix by
 * counting actual queries against `affiliate_links`, not just asserting a ->with() call exists in the
 * source.
 */
class AffiliateServiceQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::forceCreate([
            'username' => 'user_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'balance' => 0,
        ]);
    }

    private function countAffiliateLinkQueries(callable $action): int
    {
        $count = 0;
        DB::listen(function ($query) use (&$count) {
            if (str_starts_with(trim($query->sql), 'select * from `affiliate_links`') || str_starts_with(trim($query->sql), 'select `affiliate_links`')) {
                $count++;
            }
        });

        $action();

        return $count;
    }

    public function test_approve_conversions_for_order_queries_affiliate_links_once_regardless_of_conversion_count(): void
    {
        $buyer = $this->makeUser();
        CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_PLATFORM, 'scope_id' => null, 'rate_type' => 'flat', 'rate_value' => 5, 'status' => 1]);

        // Three distinct affiliates, three distinct conversions, all on the same order - as would happen
        // for a multi-vendor cart where different lines were referred by different affiliate links.
        for ($i = 0; $i < 3; $i++) {
            $affiliate = $this->makeUser();
            $link = app(AffiliateService::class)->createLink($affiliate->id, AffiliateLink::TARGET_PLATFORM);
            ReferralConversion::forceCreate([
                'affiliate_link_id' => $link->id, 'order_id' => 7001, 'buyer_user_id' => $buyer->id,
                'order_total' => 50.0, 'commission_rate_type' => 'flat', 'commission_rate_value' => 5,
                'commission_amount' => 5.0, 'status' => ReferralConversion::STATUS_PENDING,
            ]);
        }

        $queryCount = $this->countAffiliateLinkQueries(function () {
            app(AffiliateService::class)->approveConversionsForOrder(7001);
        });

        $this->assertSame(1, $queryCount, 'approveConversionsForOrder() should eager-load affiliate_links in one query, not one per conversion.');
        $this->assertSame(3, ReferralConversion::where('order_id', 7001)->where('status', ReferralConversion::STATUS_APPROVED)->count());
    }

    public function test_reverse_conversions_for_order_queries_affiliate_links_once_regardless_of_conversion_count(): void
    {
        $buyer = $this->makeUser();
        CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_PLATFORM, 'scope_id' => null, 'rate_type' => 'flat', 'rate_value' => 5, 'status' => 1]);

        for ($i = 0; $i < 3; $i++) {
            $affiliate = $this->makeUser();
            $link = app(AffiliateService::class)->createLink($affiliate->id, AffiliateLink::TARGET_PLATFORM);
            ReferralConversion::forceCreate([
                'affiliate_link_id' => $link->id, 'order_id' => 7002, 'buyer_user_id' => $buyer->id,
                'order_total' => 50.0, 'commission_rate_type' => 'flat', 'commission_rate_value' => 5,
                'commission_amount' => 5.0, 'status' => ReferralConversion::STATUS_APPROVED,
            ]);
        }

        $queryCount = $this->countAffiliateLinkQueries(function () {
            app(AffiliateService::class)->reverseConversionsForOrder(7002);
        });

        $this->assertSame(1, $queryCount, 'reverseConversionsForOrder() should eager-load affiliate_links in one query, not one per conversion.');
        $this->assertSame(3, ReferralConversion::where('order_id', 7002)->where('status', ReferralConversion::STATUS_REVERSED)->count());
    }
}
