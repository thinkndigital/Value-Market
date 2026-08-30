<?php

namespace Tests\Feature\Phase9;

use App\Models\Currency;
use App\Models\Role;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 32-phase SaaS brief, Phase 9/10 (docs/PHASE_9_10_POS_CONCURRENCY_AND_BRANCHES.md): the POS page now
 * renders inside a dedicated full-screen shell (resources/views/seller/pos_layout.blade.php - no sidebar/
 * header chrome, its own responsive two-panel CSS) instead of the normal seller/layout wrapper. Proves the
 * page still renders cleanly end to end (a real risk when re-parenting a 1000+ line view under a new
 * layout - an unbalanced tag from moving markup around is exactly the kind of thing that only a real
 * render catches, not a syntax check) and that the new shell's markers are present.
 */
class PosFullscreenLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function shareBaseViewData(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market', 'favicon' => ''])]);
        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'system_settings' => ['app_name' => 'Value Market', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);
    }

    public function test_the_pos_page_renders_inside_the_fullscreen_shell_with_no_sidebar_chrome(): void
    {
        $this->shareBaseViewData();
        $user = User::forceCreate([
            'username' => 'pos_ui_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        Seller::forceCreate(['user_id' => $user->id, 'disk' => 'public', 'status' => 1]);

        $response = $this->actingAs($user)->get(route('seller.point_of_sale.index'));

        $response->assertOk();
        $response->assertSee('pos-fullscreen-topbar', false);
        $response->assertSee('pos-fullscreen-content', false);
        // The normal seller/layout sidebar wrapper must NOT appear - proves this page really uses the
        // dedicated full-screen shell, not the standard chrome with extra CSS bolted on.
        $response->assertDontSee('id="db-wrapper"', false);
    }
}
