<?php

namespace Tests\Feature;

use App\Models\AffiliateLink;
use App\Models\Currency;
use App\Models\ReferralConversion;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AffiliateController::dashboard() has only ever shown two aggregate totals (approved/pending commission);
 * conversionsHistory() adds the per-order breakdown behind them. Scoped to the caller's own link the same
 * way list()/withdrawalHistory() already are - AffiliateWithdrawalTest's IDOR-style scoping test is the
 * pattern this mirrors.
 */
class AffiliateConversionsHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function shareBaseViewData(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market', 'favicon' => ''])]);
        Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);
        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'system_settings' => ['app_name' => 'Value Market', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);
    }

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'username' => 'affiliate_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
    }

    public function test_returns_the_callers_own_conversions_ordered_newest_first(): void
    {
        $this->shareBaseViewData();
        $customer = $this->makeCustomer();
        $link = AffiliateLink::forceCreate([
            'user_id' => $customer->id, 'target_type' => AffiliateLink::TARGET_PLATFORM, 'target_id' => null,
            'code' => 'mycode', 'status' => AffiliateLink::STATUS_ACTIVE,
        ]);
        ReferralConversion::forceCreate([
            'affiliate_link_id' => $link->id, 'order_id' => 101, 'buyer_user_id' => null, 'order_total' => 100,
            'commission_rate_type' => 'percentage', 'commission_rate_value' => 5, 'commission_amount' => 5,
            'status' => ReferralConversion::STATUS_APPROVED,
        ]);
        ReferralConversion::forceCreate([
            'affiliate_link_id' => $link->id, 'order_id' => 102, 'buyer_user_id' => null, 'order_total' => 40,
            'commission_rate_type' => 'percentage', 'commission_rate_value' => 5, 'commission_amount' => 2,
            'status' => ReferralConversion::STATUS_PENDING,
        ]);

        $response = $this->actingAs($customer)->getJson(route('affiliate.conversions.list'));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertSame(102, $data[0]['order_id'], 'Newest conversion must come first.');
        $this->assertSame(101, $data[1]['order_id']);
    }

    public function test_never_returns_another_affiliates_conversions(): void
    {
        $this->shareBaseViewData();
        $customer = $this->makeCustomer();
        $otherCustomer = $this->makeCustomer();
        $otherLink = AffiliateLink::forceCreate([
            'user_id' => $otherCustomer->id, 'target_type' => AffiliateLink::TARGET_PLATFORM, 'target_id' => null,
            'code' => 'othercode', 'status' => AffiliateLink::STATUS_ACTIVE,
        ]);
        ReferralConversion::forceCreate([
            'affiliate_link_id' => $otherLink->id, 'order_id' => 999, 'buyer_user_id' => null, 'order_total' => 80,
            'commission_rate_type' => 'percentage', 'commission_rate_value' => 5, 'commission_amount' => 4,
            'status' => ReferralConversion::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($customer)->getJson(route('affiliate.conversions.list'));

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    public function test_returns_an_empty_list_before_any_link_exists(): void
    {
        $this->shareBaseViewData();
        $customer = $this->makeCustomer();

        $response = $this->actingAs($customer)->getJson(route('affiliate.conversions.list'));

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }
}
