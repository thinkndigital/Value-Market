<?php

namespace Tests\Feature\Phase2;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Phase 2 (Task 3): regression test for a confirmed crash - AuthServiceProvider::Gate::before(),
 * RoleMiddleware, and CheckPermissions all previously did `$user->role->name` with no null check. A user
 * with role_id = NULL (a legitimate, nullable column - most plain customers likely have no role_id at all)
 * or a role_id pointing at a deleted role row crashed with "Attempt to read property 'name' on null" the
 * moment any of these ran. Confirmed empirically in Phase 1; fixed in Phase 2 via null-safe
 * User::isSuperAdmin() etc. helpers. This test proves the crash is gone, not just that the code changed.
 */
class RoleNullSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(?int $roleId): User
    {
        return User::forceCreate([
            'username' => 'roleless_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => $roleId,
        ]);
    }

    public function test_gate_before_does_not_crash_for_a_user_with_no_role(): void
    {
        $user = $this->makeUser(null);

        // Previously: ErrorException "Attempt to read property 'name' on null" the instant any Gate/Policy
        // check ran for this user. Now: Gate::before() returns null (not a crash), so Laravel falls
        // through to the normal ability check - which resolves to false here since no policy grants it.
        $result = Gate::forUser($user)->allows('view', $user);

        $this->assertFalse($result);
    }

    public function test_gate_before_does_not_crash_for_a_role_id_pointing_at_no_row(): void
    {
        // role_id has no FK constraint (docs/DATABASE_GAP_ANALYSIS.md §2) - a dangling reference like this
        // is possible in the real schema, not just a contrived test case.
        $user = $this->makeUser(999999);

        $result = Gate::forUser($user)->allows('view', $user);

        $this->assertFalse($result);
    }

    public function test_super_admin_is_still_granted_every_ability(): void
    {
        // Role::SUPER_ADMIN (1) is guaranteed to exist and mean "super_admin" thanks to the Phase 2
        // roles-seed migration (2025_02_01_000000_seed_roles_reference_data.php) - isSuperAdmin() compares
        // role_id against this constant directly, not a role row's name, so use the real one rather than
        // an ad-hoc same-named row with an unrelated id.
        $superAdmin = $this->makeUser(Role::SUPER_ADMIN);

        $this->assertTrue(Gate::forUser($superAdmin)->allows('view', $superAdmin));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('any-made-up-ability-name'));
    }

    public function test_role_middleware_denies_rather_than_crashes_for_a_roleless_user(): void
    {
        $user = $this->makeUser(null);
        Auth::login($user);

        $middleware = new \App\Http\Middleware\RoleMiddleware();
        $request = \Illuminate\Http\Request::create('/admin/dashboard', 'GET');

        $this->expectException(\Spatie\Permission\Exceptions\UnauthorizedException::class);

        $middleware->handle($request, fn($req) => $req, 'super_admin', 'admin', 'editor');
    }

    public function test_check_permissions_middleware_denies_rather_than_crashes_for_a_roleless_user(): void
    {
        // A real permission must exist for hasPermissionTo() to check against - Spatie throws
        // PermissionDoesNotExist for an unknown name, which is a separate, pre-existing behavior this test
        // isn't about; seed one so the test exercises the null-role path this test actually targets.
        \Illuminate\Support\Facades\DB::table('permissions')->insert([
            'name' => 'view_products', 'guard_name' => 'web',
        ]);

        $user = $this->makeUser(null);
        Auth::login($user);

        $middleware = new \App\Http\Middleware\CheckPermissions();
        $request = \Illuminate\Http\Request::create('/admin/products', 'GET');
        $request->headers->set('Accept', 'application/json');
        // unauthorizedResponse() checks the global request() helper, not the $request passed to handle() -
        // bind it into the container so it resolves to this request, matching real HTTP handling (same
        // gotcha documented in tests/Feature/Phase1/AddressOwnershipTest.php).
        $this->app->instance('request', $request);

        $response = $middleware->handle($request, fn($req) => $req, 'view_products');

        $this->assertSame(403, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertTrue($payload['error']);
    }
}
