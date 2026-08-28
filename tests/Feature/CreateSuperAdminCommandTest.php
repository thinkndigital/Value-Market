<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_new_super_admin_when_mobile_is_unused(): void
    {
        $this->artisan('admin:create-super-admin', [
            '--username' => 'Store Owner',
            '--mobile' => '0799181518',
            '--email' => 'owner@example.com',
            '--password' => 'a-strong-password',
        ])->assertSuccessful();

        $user = User::where('mobile', '0799181518')->first();

        $this->assertNotNull($user);
        $this->assertSame(Role::SUPER_ADMIN, $user->role_id);
        $this->assertSame(1, $user->active);
    }

    public function test_promotes_an_existing_account_to_super_admin_by_mobile(): void
    {
        $user = User::forceCreate([
            'username' => 'customer_' . uniqid(), 'password' => 'original-hash', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'mobile' => '0799181518', 'role_id' => Role::CUSTOMER,
        ]);
        $originalPasswordHash = $user->password;

        $this->artisan('admin:create-super-admin', [
            '--mobile' => '0799181518',
        ])->assertSuccessful();

        $user->refresh();

        $this->assertSame(Role::SUPER_ADMIN, $user->role_id);
        // No --password given: the existing password must be left untouched, not silently reset.
        $this->assertSame($originalPasswordHash, $user->password);
    }

    public function test_promoting_an_existing_account_can_also_reset_its_password(): void
    {
        $user = User::forceCreate([
            'username' => 'customer_' . uniqid(), 'password' => 'original-hash', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'mobile' => '0799181518', 'role_id' => Role::CUSTOMER,
        ]);
        $originalPasswordHash = $user->password;

        $this->artisan('admin:create-super-admin', [
            '--mobile' => '0799181518',
            '--password' => 'a-new-strong-password',
        ])->assertSuccessful();

        $user->refresh();

        $this->assertSame(Role::SUPER_ADMIN, $user->role_id);
        $this->assertNotSame($originalPasswordHash, $user->password);
    }
}
