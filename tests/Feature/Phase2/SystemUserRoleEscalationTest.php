<?php

namespace Tests\Feature\Phase2;

use App\Http\Controllers\Admin\UserPermissionController;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Phase 2 (Task 15, super-admin isolation): UserPermissionController::store() (the "add system user" form)
 * offered Super Admin/Admin/Editor as assignable roles to ANY caller who could reach the route - gated only
 * by the `create system_user` permission, which the outer route group's role check (role:super_admin,admin,
 * editor) allows an editor account to hold. An editor granted that one permission could create a brand-new
 * Super Admin account for themselves or an accomplice - full privilege escalation, with no super-admin
 * involvement required at all. Proves: a non-super-admin caller is denied when requesting the super-admin
 * role, a super admin can still grant it, and a non-super-admin can still create ordinary admin/editor
 * accounts (the fix must not block legitimate staff-management delegation).
 */
class SystemUserRoleEscalationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(int $roleId): User
    {
        return User::forceCreate([
            'username' => 'user_' . uniqid(),
            'mobile' => (string) random_int(6000000000, 6999999999),
            'email' => uniqid() . '@example.test',
            'password' => bcrypt('x'),
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => $roleId,
        ]);
    }

    private function storeRequest(int $requestedRole): Request
    {
        $request = new Request([
            'username' => 'new_user_' . uniqid(),
            'mobile' => (string) random_int(6000000000, 6999999999),
            'email' => uniqid() . '@example.test',
            'password' => 'password123',
            'confirm_password' => 'password123',
            'role' => $requestedRole,
        ]);
        // store() only returns a JSON response body on the ajax path - the non-ajax path (validation
        // failure included) redirects back instead, which this test suite's other assertions rely on too.
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        return $request;
    }

    public function test_an_editor_cannot_create_a_super_admin_account(): void
    {
        $editor = $this->makeUser(Role::EDITOR);
        Auth::login($editor);

        $response = app(UserPermissionController::class)->store($this->storeRequest(Role::SUPER_ADMIN));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error'] ?? false, 'A non-super-admin must never be able to create a Super Admin account.');
        $this->assertSame(0, User::where('role_id', Role::SUPER_ADMIN)->count());
    }

    public function test_a_super_admin_can_still_create_a_super_admin_account(): void
    {
        $superAdmin = $this->makeUser(Role::SUPER_ADMIN);
        Auth::login($superAdmin);

        app(UserPermissionController::class)->store($this->storeRequest(Role::SUPER_ADMIN));

        $this->assertSame(2, User::where('role_id', Role::SUPER_ADMIN)->count());
    }

    public function test_an_editor_can_still_create_an_ordinary_admin_account(): void
    {
        $editor = $this->makeUser(Role::EDITOR);
        Auth::login($editor);

        $response = app(UserPermissionController::class)->store($this->storeRequest(Role::ADMIN));
        $data = json_decode($response->getContent(), true);

        // The success path (unlike the failure paths above) has no 'error' key at all - just 'message'/
        // 'location' - so its absence, plus the row actually existing, is what proves this worked.
        $this->assertArrayNotHasKey('error', $data);
        $this->assertSame(1, User::where('role_id', Role::ADMIN)->count(), 'Delegated staff management (creating admin/editor accounts) must keep working.');
    }

    public function test_an_invalid_role_value_is_rejected(): void
    {
        $superAdmin = $this->makeUser(Role::SUPER_ADMIN);
        Auth::login($superAdmin);

        $response = app(UserPermissionController::class)->store($this->storeRequest(Role::CUSTOMER));

        // HandlesValidation's non-ajax path redirects back with errors rather than a json error body.
        $this->assertNotSame(200, method_exists($response, 'status') ? $response->status() : 200);
    }
}
