<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Found while assessing redesign progress on /admin/home (docs/IMPLEMENTATION_ROADMAP.md's dashboard
 * redesign task): the dashboard rendered as a fully blank content area in a real browser, despite the raw
 * server response containing all the real card/chart markup. Root cause: admin/pages/forms/home.blade.php
 * (and its seller equivalent) @include'd Chatify::layouts.headLinks - a partial containing <title>, five
 * <meta> tags, and a <style> block - partway through @yield('content'), i.e. inside <body>. Per the HTML5
 * parsing spec, a stray <title>/<meta>/<style>/<script> encountered in the "in body" insertion mode is
 * reprocessed using the "in head" insertion mode's rules; browsers were silently relocating (or losing) all
 * dashboard markup that followed it, leaving nothing rendered.
 *
 * Fixed by defining a `chatify_head` Blade stack in the real <head> (admin/seller include_css.blade.php) and
 * having home.blade.php @push() the Chatify partial into it instead of @include-ing it inline - the
 * standard Laravel mechanism for exactly this situation. These tests assert the fix at the HTML-string level
 * (everything Chatify's headLinks contributes appears before </head>) since PHPUnit has no real HTML parser
 * to reproduce the browser's reparenting behavior directly; the actual dashboard-renders-visibly claim was
 * verified with a real headless-browser screenshot during development, not by this test.
 */
class DashboardHeadStackingTest extends TestCase
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

        // AppServiceProvider::boot() shares these to every view on a real HTTP request, but only when
        // !runningInConsole() - PHPUnit's own process is itself a console process (even for a simulated HTTP
        // request via $this->get()), so this is the same stand-in used elsewhere in this test suite
        // (tests/Feature/InvoicePdfGenerationTest.php etc.).
        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'system_settings' => ['app_name' => 'Value Market', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);
    }

    private function makeAdmin(): User
    {
        return User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN, 'active' => 1,
        ]);
    }

    private function makeSeller(): array
    {
        $sellerUser = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'active' => 1,
        ]);
        $seller = \App\Models\Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public']);
        \App\Models\SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 1, 'status' => 1,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Test Store', 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);
        return [$sellerUser, $seller];
    }

    public function test_admin_dashboard_puts_chatify_head_markup_inside_head_not_body(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('admin.home'));

        $response->assertOk();
        $content = $response->getContent();

        $headEnd = strpos($content, '</head>');
        $this->assertNotFalse($headEnd, 'Response must contain a </head> tag.');

        $messengerMeta = strpos($content, 'name="messenger-color"');
        $this->assertNotFalse($messengerMeta, 'Chatify headLinks content must be present in the response.');
        $this->assertLessThan(
            $headEnd,
            $messengerMeta,
            'Chatify\'s messenger-color <meta> tag must land inside <head>, not be pushed into <body> where the HTML5 parser would corrupt everything that follows it.'
        );

        // The dashboard's own real content (not just chrome) must still be present and after </head>.
        $dashboardMarker = strpos($content, 'admin_statistic_chart');
        $this->assertNotFalse($dashboardMarker, 'The dashboard\'s own chart markup must still render.');
        $this->assertGreaterThan($headEnd, $dashboardMarker, 'Dashboard content must render in <body>, after </head>.');
    }

    public function test_seller_dashboard_puts_chatify_head_markup_inside_head_not_body(): void
    {
        $this->shareBaseViewData();
        [$sellerUser] = $this->makeSeller();

        $response = $this->actingAs($sellerUser)->get(route('seller.home'));

        $response->assertOk();
        $content = $response->getContent();

        $headEnd = strpos($content, '</head>');
        $this->assertNotFalse($headEnd);

        $messengerMeta = strpos($content, 'name="messenger-color"');
        $this->assertNotFalse($messengerMeta);
        $this->assertLessThan($headEnd, $messengerMeta);
    }
}
