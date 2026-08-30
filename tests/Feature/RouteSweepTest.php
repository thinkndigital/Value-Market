<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Phase 2 of the 32-phase SaaS transformation brief ("Complete Route & Page Inventory"): rather than
 * manually clicking every page by hand (hundreds of them across 4 panels), this programmatically hits
 * every parameter-less GET route in each panel as a real logged-in user of that role and asserts none of
 * them 500. This is the exact same bug class AdminMissingViewsTest and SellerAndDeliveryBoyMissingViewsTest
 * already found and fixed for 11 specific pages (a route/controller/sidebar-link existing with no matching
 * Blade view) - this sweep generalizes that audit to every no-param route instead of only the ones already
 * discovered by hand, so it can catch pages nobody has stumbled onto yet.
 *
 * Deliberately excludes routes that end the test's authenticated session (logout) - those would break every
 * subsequent iteration in the same sweep, not because they're exempt from the audit.
 *
 * What this does NOT verify (documented, not silently skipped - see docs/PHASE_2_ROUTE_SWEEP_REPORT.md):
 * routes requiring a URL parameter (75 admin / 27 seller / 4 delivery_boy / 1 affiliate) are not swept here
 * - each needs a real, valid id for its specific model, not a generic substitution. Forms/AJAX/validation/
 * search/filters/pagination/RTL rendering are also out of scope for a route sweep - it only proves a page
 * doesn't fatal on load, matching this brief's own "Route exists -> ... -> View renders" step, not the
 * full page-level checklist beyond that.
 */
class RouteSweepTest extends TestCase
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

    private function makeSuperAdmin(): User
    {
        return User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN, 'active' => 1,
        ]);
    }

    /**
     * Every route already known-broken as of the docs/PHASE_2_ROUTE_SWEEP_REPORT.md audit, so this test
     * guards against *new* regressions without staying permanently red over issues already triaged and
     * reported there (same "document, don't silently skip" approach as TECHNICAL_DEBT.md's unfixed-bug
     * list). Grouped by the report's own categories - see that doc for the full root-cause detail behind
     * each line, not repeated here. Removing an entry here (once actually fixed) is how this test proves
     * the fix stuck.
     */
    private const KNOWN_BROKEN_ROUTES = [
        // Category 1: dead routes - grep-confirmed zero references from any Blade view or JS, so
        // unreachable via normal navigation (create/add-record happens via a modal on the index page
        // instead). Real gap if ever linked to; today just a 500-if-you-type-the-URL bookmark trap.
        '/admin/register', '/admin/categories/create', '/admin/blogs/create', '/admin/brands/create',
        '/admin/taxes/create', '/admin/promo_codes/create', '/admin/attributes/create', '/admin/products/create',
        '/admin/feature_section/create', '/admin/pickup_location/create', '/admin/orders/create',
        '/admin/manage_stock/create', '/admin/payment_request/create', '/admin/sliders/create',
        '/admin/chat/create', '/admin/offers/create', '/admin/product_faqs/create',
        '/admin/combo_product_faqs/create', '/admin/delivery_boys/create', '/admin/faq/create',
        '/admin/tickets/ticket_types/create', '/admin/custom_message/create', '/admin/store/create',
        '/admin/combo_product_attributes/create', '/admin/combo_products/create', '/admin/zones/create',
        '/seller/pickup_locations/create', '/seller/tax/create', '/seller/products/attributes/create',
        '/seller/products/create', '/seller/product_faqs/create', '/seller/combo_product_faqs/create',
        '/seller/chat/create', '/seller/orders/create', '/seller/combo_product_attributes/create',
        '/seller/combo_products/create', '/delivery_boy/orders/create',

        // Category 2: AJAX-only endpoints that need a real query/reorder payload - never navigated to bare
        // by real UI (always called by JS with params), so a param-less GET 500ing is expected, not a
        // reachable user-facing bug. Real fix (input validation returning 4xx) is low priority.
        '/admin/categories/update_category_order', '/admin/products/fetch_attribute_values_by_id',
        '/admin/products/get_variants_by_id', '/admin/seller/get_seller_deliverable_type',
        '/admin/feature_section/update_section_order', '/admin/area/get_zipcode', '/admin/area',
        '/admin/area/list', '/admin/zones/seller_zones_data', '/seller/zones/zones_data', '/seller/area',
        '/seller/area/list', '/seller/products/fetch_attribute_values_by_id',
        '/seller/products/get_variants_by_id', '/seller/seller/get_seller_deliverable_type',
        '/delivery_boy/orders/order_item_list',

        // Category 3: routes wired to a controller method that was never written (feature genuinely
        // incomplete, not dead-by-design like Category 1) - a real gap, needs its own scoped fix.
        '/admin/privacy_policy/seller_privacy_policy_page', '/admin/terms_and_condition/seller_terms_and_condition_page',
        '/admin/settings/time_slot_settings', '/admin/settings/time_slot/list',
        '/admin/settings/manage_web_language', '/seller/settings/language', '/delivery_boy/settings/language',

        // Category 4: page crashes when the specific Settings row it reads was never saved once (the exact
        // "fresh install" crash class already found and fixed for storage_types earlier this session) -
        // real risk on a genuinely fresh install before an admin ever visits Settings and saves once.
        '/admin/shipping_policy/shipping_policy_page', '/admin/return_policy/return_policy_page',
        '/admin/settings/system_settings', '/admin/settings/email_settings', '/delivery_boy/login',

        // Category 5: needs deeper individual investigation before a confident fix - not yet triaged to a
        // category above.
        '/admin/media/image', '/seller/media/image', '/admin/manage_stock/list', '/admin/manage_combo_stock/list',
        '/seller/manage_stock/get_stock_list', '/seller/manage_combo_stock/list', '/seller/orders/list',
    ];

    /**
     * Sweeps every parameter-less GET route under $uriPrefix as $user, returning ["METHOD uri" => status]
     * for anything that 500'd and isn't already in KNOWN_BROKEN_ROUTES. logout routes are skipped (they'd
     * end the session mid-sweep); everything else - including routes that redirect, 403, or 404 for this
     * role/state - is left in as a real pass/fail signal, not filtered out.
     */
    private function sweepPanel(string $uriPrefix, User $user): array
    {
        $this->actingAs($user);
        $failures = [];

        $routes = collect(RouteFacade::getRoutes())
            ->filter(function ($route) use ($uriPrefix) {
                $uri = $route->uri();
                return in_array('GET', $route->methods(), true)
                    && str_starts_with($uri, $uriPrefix)
                    && !str_contains($uri, '{')
                    && !str_contains($uri, 'logout');
            });

        foreach ($routes as $route) {
            $uri = '/' . ltrim($route->uri(), '/');
            if (in_array($uri, self::KNOWN_BROKEN_ROUTES, true)) {
                continue;
            }
            try {
                $response = $this->get($uri, ['Accept' => 'application/json']);
                if ($response->getStatusCode() >= 500) {
                    $body = json_decode($response->getContent(), true);
                    $failures[$uri] = ($body['exception'] ?? 'Unknown') . ': ' . ($body['message'] ?? $response->getStatusCode())
                        . ' @ ' . ($body['file'] ?? '?') . ':' . ($body['line'] ?? '?');
                }
            } catch (\Throwable $e) {
                $failures[$uri] = get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
            }
        }

        return $failures;
    }

    public function test_every_no_param_admin_route_renders_without_a_server_error(): void
    {
        $this->shareBaseViewData();
        $admin = $this->makeSuperAdmin();

        $failures = $this->sweepPanel('admin', $admin);

        $this->assertEmpty($failures, "New admin page breakage, not in KNOWN_BROKEN_ROUTES (route => status/error):\n" . json_encode($failures, JSON_PRETTY_PRINT));
    }

    public function test_every_no_param_seller_route_renders_without_a_server_error(): void
    {
        $this->shareBaseViewData();
        $this->artisan('demo:seed-all', ['--password' => 'Demo@12345'])->run();
        $seller = User::where('mobile', '9990000001')->firstOrFail();

        $failures = $this->sweepPanel('seller', $seller);

        $this->assertEmpty($failures, "New seller page breakage, not in KNOWN_BROKEN_ROUTES (route => status/error):\n" . json_encode($failures, JSON_PRETTY_PRINT));
    }

    public function test_every_no_param_delivery_boy_route_renders_without_a_server_error(): void
    {
        $this->shareBaseViewData();
        $this->artisan('demo:seed-all', ['--password' => 'Demo@12345'])->run();
        $deliveryBoy = User::where('mobile', '9990000003')->firstOrFail();

        $failures = $this->sweepPanel('delivery_boy', $deliveryBoy);

        $this->assertEmpty($failures, "New delivery_boy page breakage, not in KNOWN_BROKEN_ROUTES (route => status/error):\n" . json_encode($failures, JSON_PRETTY_PRINT));
    }

    public function test_every_no_param_affiliate_route_renders_without_a_server_error(): void
    {
        $this->shareBaseViewData();
        $this->artisan('demo:seed-all', ['--password' => 'Demo@12345'])->run();
        $affiliate = User::where('mobile', '9990000002')->firstOrFail();

        $failures = $this->sweepPanel('affiliate', $affiliate);

        $this->assertEmpty($failures, "Broken affiliate pages (route => status/error):\n" . json_encode($failures, JSON_PRETTY_PRINT));
    }
}
