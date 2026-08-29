<?php

namespace Tests\Feature;

use App\Models\AffiliateLink;
use App\Models\CommissionRule;
use App\Models\Currency;
use App\Models\ReferralConversion;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7 (docs/PHASE_7_AFFILIATE_ENGINE.md) built the affiliate link/commission-rule engine as pure JSON
 * endpoints with no admin-panel page ever pointing at them - admin.commission_rules.* had no index route at
 * all, and there was no admin-facing report on affiliate links/conversions whatsoever. This adds both: a
 * Commission Rules management page (wired to the existing store/update endpoints) and a read-only Affiliate
 * Links report (new Admin\AffiliateController, since no reporting endpoint existed before).
 */
class AdminAffiliateManagementTest extends TestCase
{
    use RefreshDatabase;

    private function shareBaseViewData(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market'])]);
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

    private function makeSuperAdmin(): User
    {
        return User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN, 'active' => 1,
        ]);
    }

    public function test_commission_rules_page_renders(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->get(route('admin.commission_rules.index'));

        $response->assertOk();
        // Regression check: this page's submit handlers were originally wrapped in @push('scripts'), but
        // none of this app's layouts (admin/seller/delivery_boy) define a matching @stack('scripts') -
        // confirmed by grepping all three - so the pushed content was silently dropped and never reached
        // the page at all. Asserting the handler function name actually appears in the rendered HTML is
        // the only way to catch that class of bug; assertOk() alone does not.
        $response->assertSee('commissionRulesResponseHandler', false);
    }

    public function test_creating_a_commission_rule_via_the_page_form_still_works(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->post(route('admin.commission_rules.store'), [
            'scope' => 'platform',
            'rate_type' => 'percentage',
            'rate_value' => 5,
        ]);

        $response->assertOk();
        $response->assertJson(['error' => false]);
        $this->assertDatabaseHas('commission_rules', ['scope' => 'platform', 'rate_value' => 5]);
    }

    public function test_affiliate_links_page_renders(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->get(route('admin.affiliate.links.index'));

        $response->assertOk();
    }

    public function test_affiliate_links_list_reports_clicks_and_commission_totals(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();

        $affiliateUser = User::forceCreate([
            'username' => 'affiliate_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'active' => 1,
        ]);
        $link = AffiliateLink::forceCreate([
            'user_id' => $affiliateUser->id, 'target_type' => AffiliateLink::TARGET_PLATFORM, 'target_id' => null,
            'code' => 'TESTCODE1', 'clicks_count' => 3, 'conversions_count' => 1, 'status' => AffiliateLink::STATUS_ACTIVE,
        ]);
        ReferralConversion::forceCreate([
            'affiliate_link_id' => $link->id, 'order_id' => 999, 'buyer_user_id' => null, 'order_total' => 100,
            'commission_rate_type' => 'percentage', 'commission_rate_value' => 5, 'commission_amount' => 5,
            'status' => ReferralConversion::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.affiliate.links.list'));

        $response->assertOk();
        $response->assertJsonFragment(['code' => 'TESTCODE1', 'clicks_count' => 3]);
        $body = $response->json();
        $this->assertStringContainsString('5', $body['rows'][0]['approved_commission']);
    }
}
