<?php

namespace Tests\Feature\Phase4;

use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_makes_a_login_capable_user_and_an_employee_row(): void
    {
        $ownerUser = User::forceCreate([
            'username' => 'owner_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $ownerUser->id, 'disk' => 'public', 'status' => 1]);

        $employee = app(EmployeeService::class)->create($seller->id, [
            'name' => 'Branch Manager',
            'mobile' => (string) random_int(6000000000, 6999999999),
            'password' => 'password123',
            'position' => 'Branch Manager',
        ]);

        $this->assertSame($seller->id, $employee->seller_id);
        $this->assertSame('Branch Manager', $employee->position);

        $user = User::find($employee->user_id);
        $this->assertNotNull($user);
        $this->assertSame(Role::SELLER, $user->role_id);
        $this->assertTrue(Hash::check('password123', $user->password));
    }
}
