<?php

namespace Tests\Feature\Phase18;

use App\Http\Controllers\Admin\HomeController;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * docs/PHASE_18_PERFORMANCE_ADMIN_HOME.md: /admin/home ran 26 queries per load, empirically measured via
 * DB::listen(). The single largest cause: getWeeklySalesData() (which already computes all 7 days of the
 * week in one query) was called again inside a `for ($i=0;$i<7;$i++)` loop that only ever read a single
 * index of its result - 6 of those 7 calls were fully redundant. A second, smaller cause: getMonthlyData()
 * was called 3 times with an identical GROUP BY, differing only in which column got SUM()'d.
 * countNewUsers() (called once per /admin/home load) had the same shape of problem - 5 separate COUNT
 * queries against the same role_id set. All three fixed to run once instead of N times, same output.
 */
class AdminHomePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_home_runs_significantly_fewer_queries_than_the_26_query_baseline(): void
    {
        $admin = User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN,
        ]);
        Auth::login($admin);

        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        app(HomeController::class)->index();

        // Baseline was 26 (measured against this exact controller before the fix); this must stay well
        // below it, not just "less than 26" - the weekly-sales loop alone accounted for 6 fully redundant
        // queries, monthly-data merging removed 2 more.
        //
        // Phase 19 (docs/PHASE_19_ADMIN_HOME_QUERY_PROFILING.md) raised this threshold from 16 to 23: real
        // profiling found home.blade.php calling ordersCount() 24 TIMES inline in the template during
        // rendering - invisible to this test, which only ever measured the controller in isolation, never
        // ->render(). Those 24 blade-side calls collapsed to 7 (one per distinct status + the all-statuses
        // total) and moved into the controller so the view can read precomputed values instead of querying
        // per call site - a net reduction from ~24+14=38 real queries per page load down to ~21, even
        // though this specific controller-only count goes up by 7. See
        // tests/Feature/Phase19/AdminHomeQueryProfilingTest.php for the render-phase assertion this test
        // could never see.
        $this->assertLessThanOrEqual(23, $count, '/admin/home must not regress back toward its old 26-query baseline.');
    }

    public function test_count_new_users_returns_identical_results_to_the_original_five_query_version(): void
    {
        $now = now();
        User::forceCreate([
            'username' => 'u1', 'password' => 'x', 'disk' => 'public', 'serviceable_cities' => '',
            'type' => 'phone', 'role_id' => Role::CUSTOMER, 'active' => 1, 'created_at' => $now,
        ]);
        User::forceCreate([
            'username' => 'u2', 'password' => 'x', 'disk' => 'public', 'serviceable_cities' => '',
            'type' => 'phone', 'role_id' => Role::CUSTOMER, 'active' => 0, 'created_at' => $now->copy()->subMonth(),
        ]);
        User::forceCreate([
            'username' => 'u3', 'password' => 'x', 'disk' => 'public', 'serviceable_cities' => '',
            'type' => 'phone', 'role_id' => Role::CUSTOMER, 'active' => null, 'created_at' => $now->copy()->subMonths(2),
        ]);
        // A seller (role_id != CUSTOMER) must not be counted - proves the role_id filter still applies.
        User::forceCreate([
            'username' => 'u4', 'password' => 'x', 'disk' => 'public', 'serviceable_cities' => '',
            'type' => 'phone', 'role_id' => Role::SELLER, 'active' => 1, 'created_at' => $now,
        ]);

        $result = countNewUsers();

        $this->assertSame(3, $result['total_users']);
        $this->assertSame(1, $result['current_month_users']);
        $this->assertSame(1, $result['previous_month_users']);
        $this->assertSame(1, $result['active_user']);
        $this->assertSame(2, $result['inactive_user'], 'Both active=0 and active=null must count as inactive.');
        $this->assertSame(0.0, $result['percentage_change']);
    }

    public function test_count_new_users_runs_a_single_query(): void
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        countNewUsers();

        $this->assertSame(1, $count, 'countNewUsers() must run one query, not five.');
    }
}
