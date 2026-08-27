<?php

namespace Tests\Feature\Phase2;

use App\Http\Controllers\Admin\UserPermissionController;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Phase 2 (Task 21, minimal audit logging): auditLog() (app/function_helper.php) writes to the dedicated
 * `security` log channel (config/logging.php). Wired into this phase's highest-value privilege-boundary
 * events - granting/revoking the Super Admin role - so a granted or denied attempt leaves a record even
 * though the request itself is otherwise handled silently (just a JSON error/success response).
 */
class AuditLogTest extends TestCase
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

    public function test_auditlog_writes_to_the_security_channel_with_actor_context(): void
    {
        $actor = $this->makeUser(Role::SUPER_ADMIN);
        Auth::login($actor);

        Log::shouldReceive('channel')
            ->once()
            ->with('security')
            ->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->with('test.event', \Mockery::on(function ($context) use ($actor) {
                return $context['foo'] === 'bar'
                    && $context['actor_id'] === $actor->id
                    && $context['actor_role_id'] === Role::SUPER_ADMIN;
            }));

        auditLog('test.event', ['foo' => 'bar']);
    }

    public function test_denied_super_admin_grant_is_logged(): void
    {
        $editor = $this->makeUser(Role::EDITOR);
        Auth::login($editor);

        Log::shouldReceive('channel')->with('security')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->with('system_user.super_admin_grant_denied', \Mockery::type('array'));

        $request = new Request([
            'username' => 'attacker',
            'mobile' => (string) random_int(6000000000, 6999999999),
            'email' => uniqid() . '@example.test',
            'password' => 'password123',
            'confirm_password' => 'password123',
            'role' => Role::SUPER_ADMIN,
        ]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        app(UserPermissionController::class)->store($request);
    }

    public function test_successful_super_admin_deletion_is_logged(): void
    {
        $target = $this->makeUser(Role::SUPER_ADMIN);
        $superAdmin = $this->makeUser(Role::SUPER_ADMIN);
        Auth::login($superAdmin);

        Log::shouldReceive('channel')->with('security')->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->with('system_user.super_admin_deleted', \Mockery::type('array'));

        app(UserPermissionController::class)->destroy($target->id);
    }
}
