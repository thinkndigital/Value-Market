<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\SidebarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Unified Dynamic Sidebar Engine (32-phase SaaS brief, Phase 3 - docs/SIDEBAR_ENGINE.md). The most
 * important property this guards: a config/sidebar.php node with a typo'd or renamed route name is
 * SILENTLY dropped by SidebarService's Route::has() guard (by design, so future-phase placeholders like
 * the Creator Marketplace items don't 500) - which means a real bug there produces no error anywhere, just
 * a permanently missing sidebar item. This was caught once already (seller.wholesaler_marketplace.orders
 * was missing its .index suffix, hiding "My Supplier Orders" since the engine was first built) purely by
 * accident during unrelated work - this test exists so the next one doesn't need luck.
 */
class SidebarEngineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A short, explicit allow-list of route names config/sidebar.php intentionally references before
     * they're built (Creator Marketplace - master architecture prompt Phase 8) - Route::has() hides them
     * for now and they should start appearing the moment their routes exist, with zero further sidebar
     * edits. Any OTHER missing route name is a real bug (typo, renamed route, wrong route file).
     */
    private function notYetBuiltRoutes(): array
    {
        return [
            'admin.creator.marketplace.index',
            'seller.creator_marketplace.index',
            'affiliate.creator.dashboard',
            'affiliate.creator.requests.index',
            'affiliate.creator.content.index',
            'affiliate.creator.profile.edit',
        ];
    }

    private function collectRouteNames(array $nodes): array
    {
        $names = [];
        foreach ($nodes as $node) {
            if (!empty($node['route'])) {
                $names[] = $node['route'];
            }
            if (!empty($node['children'])) {
                $names = array_merge($names, $this->collectRouteNames($node['children']));
            }
        }

        return $names;
    }

    public function test_every_sidebar_route_name_resolves_except_the_documented_not_yet_built_ones(): void
    {
        $config = config('sidebar');
        $this->assertNotEmpty($config, 'config/sidebar.php should define at least one role');

        $allRouteNames = [];
        foreach ($config as $roleKey => $nodes) {
            $allRouteNames = array_merge($allRouteNames, $this->collectRouteNames($nodes));
        }

        $this->assertNotEmpty($allRouteNames);

        $missing = array_filter($allRouteNames, fn ($name) => !Route::has($name));
        $unexpectedlyMissing = array_diff($missing, $this->notYetBuiltRoutes());

        $this->assertEmpty(
            $unexpectedlyMissing,
            'These config/sidebar.php route names do not resolve to a registered route (typo, renamed '
            . 'route, or wrong route file - not one of the documented not-yet-built placeholders): '
            . implode(', ', $unexpectedlyMissing)
        );
    }

    public function test_every_documented_not_yet_built_route_is_still_actually_missing(): void
    {
        // If one of these starts resolving (its phase got built), this test should fail as a prompt to
        // remove it from the allow-list above - it's no longer "not yet built" and deserves to be caught
        // by the strict check if it ever breaks again later.
        foreach ($this->notYetBuiltRoutes() as $name) {
            $this->assertFalse(Route::has($name), "{$name} now resolves - remove it from notYetBuiltRoutes() so it's checked strictly.");
        }
    }

    public function test_wholesaler_sidebar_hides_the_supplier_only_seller_requests_route_from_no_one_since_it_has_no_permission_gate(): void
    {
        // A quick end-to-end sanity check (not just static config) that the engine actually resolves a
        // real route for a real user, using one of this session's own newest items.
        $user = User::forceCreate([
            'username' => 'wh_sidebar_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::WHOLESALER, 'active' => 1,
        ]);

        $tree = app(SidebarService::class)->build($user, 'wholesaler');
        $keys = array_column($tree, 'key');

        $this->assertContains('seller_requests', $keys);
        $this->assertContains('wallet', $keys);
        $this->assertContains('pricing', $keys);
    }
}
