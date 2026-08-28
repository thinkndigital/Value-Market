<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Found while investigating docs/PHASE_20_DASHBOARD_RENDERING_BUG.md's flagged "Orders Overview shows
 * illogical numbers on an empty test database" follow-up: it turned out the dev database wasn't empty at all
 * (145k real order_items rows across 3 stores, from Phase 19's own performance-testing seed) - the real bug
 * was that none of those 3 stores had is_default_store=1, which `is_default_store` is *never* set
 * automatically for (only ever by an admin explicitly checking a box in Admin\StoreController). With no
 * default, SetDefaultStore's own `Store::where('is_default_store', 1)->first()` always returned null, so
 * `session('store_id')` stayed permanently empty for every request - and downstream code disagreed on what
 * an empty store scope means: some queries (`Product::where('store_id', '')`) matched nothing and showed 0,
 * while OrderService::ordersCount()'s `if (!empty($store_id))` guard skipped its filter entirely and summed
 * every store's orders together, producing a dashboard that showed "0 sellers/0 products" next to "29,646
 * delivered orders" on the very same page.
 *
 * Fixed by falling back to the earliest active store when no store is explicitly flagged default. These
 * tests prove: (1) the fallback fires and picks the right store when nothing is marked default, (2) an
 * explicitly-flagged default store is still preferred over the fallback when one exists, (3) a store that's
 * flagged default but inactive (status != 1) is correctly skipped in favor of the fallback.
 */
class SetDefaultStoreFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN, 'active' => 1,
        ]);
    }

    private function makeStore(int $id, bool $isDefault = false, int $status = 1): Store
    {
        return Store::forceCreate([
            'id' => $id, 'name' => json_encode(['en' => 'Store ' . $id]), 'slug' => 'store-' . $id,
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => $status,
            'is_default_store' => $isDefault ? 1 : 0,
        ]);
    }

    public function test_falls_back_to_the_earliest_active_store_when_none_is_flagged_default(): void
    {
        $this->makeStore(2, isDefault: false);
        $this->makeStore(3, isDefault: false);
        $this->makeStore(1, isDefault: false);

        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get('/');

        $this->assertSame(1, session('store_id'));
    }

    public function test_an_explicitly_flagged_default_store_is_still_preferred(): void
    {
        $this->makeStore(1, isDefault: false);
        $this->makeStore(2, isDefault: true);
        $this->makeStore(3, isDefault: false);

        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get('/');

        $this->assertSame(2, session('store_id'));
    }

    public function test_a_default_flagged_but_inactive_store_is_skipped_for_the_fallback(): void
    {
        $this->makeStore(1, isDefault: true, status: 0);
        $this->makeStore(2, isDefault: false, status: 1);

        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get('/');

        $this->assertSame(2, session('store_id'));
    }
}
