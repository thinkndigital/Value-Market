<?php

namespace Tests\Feature\Phase4;

use App\Http\Controllers\Seller\EmployeeController;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeSeller(): Seller
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => Role::SELLER,
        ]);

        return Seller::forceCreate(['user_id' => $user->id, 'disk' => 'public', 'status' => 1]);
    }

    public function test_a_seller_can_create_and_list_their_own_employee(): void
    {
        $seller = $this->makeSeller();
        Auth::login(User::find($seller->user_id));

        $request = new Request([
            'name' => 'Staff One',
            'mobile' => (string) random_int(6000000000, 6999999999),
            'password' => 'password123',
        ]);
        $response = app(EmployeeController::class)->store($request);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);

        $listResponse = app(EmployeeController::class)->list();
        $list = json_decode($listResponse->getContent(), true);
        $this->assertCount(1, $list['data']);
    }

    public function test_a_seller_cannot_assign_an_employee_to_another_sellers_branch(): void
    {
        $owner = $this->makeSeller();
        $stranger = $this->makeSeller();
        $strangerBranch = Branch::forceCreate(['seller_id' => $stranger->id, 'name' => 'Stranger Branch', 'status' => Branch::STATUS_ACTIVE]);

        Auth::login(User::find($owner->user_id));

        $request = new Request([
            'name' => 'Staff One',
            'mobile' => (string) random_int(6000000000, 6999999999),
            'password' => 'password123',
            'branch_id' => $strangerBranch->id,
        ]);
        $response = app(EmployeeController::class)->store($request);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame(0, Employee::where('seller_id', $owner->id)->count());
    }

    public function test_a_seller_cannot_update_another_sellers_employee(): void
    {
        $owner = $this->makeSeller();
        $stranger = $this->makeSeller();
        $ownerEmployeeUser = User::forceCreate([
            'username' => 'staff_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $employee = Employee::forceCreate([
            'seller_id' => $owner->id, 'user_id' => $ownerEmployeeUser->id,
            'position' => 'Cashier', 'status' => Employee::STATUS_ACTIVE, 'disk' => 'public',
        ]);

        Auth::login(User::find($stranger->user_id));

        $response = app(EmployeeController::class)->update(new Request(['position' => 'Hijacked']), $employee->id);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame('Cashier', $employee->fresh()->position);
    }

    public function test_destroy_deactivates_rather_than_deletes_the_employee_row(): void
    {
        $owner = $this->makeSeller();
        $employeeUser = User::forceCreate([
            'username' => 'staff_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $employee = Employee::forceCreate([
            'seller_id' => $owner->id, 'user_id' => $employeeUser->id,
            'status' => Employee::STATUS_ACTIVE, 'disk' => 'public',
        ]);

        Auth::login(User::find($owner->user_id));

        $response = app(EmployeeController::class)->destroy($employee->id);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $this->assertNotNull($employee->fresh());
        $this->assertSame(Employee::STATUS_INACTIVE, $employee->fresh()->status);
        $this->assertNotNull(User::find($employeeUser->id));
    }

    /**
     * Security audit finding (docs/SECURITY_AUDIT.md §6, Finding 9): TenantContext::currentSellerId()
     * resolves the same seller_id for an employee as for the owner, so employee-roster management must be
     * gated on TenantContext::isSellerOwner(), not just "resolves to this tenant" - otherwise any employee
     * has full owner authority to create more employees or deactivate coworkers.
     */
    public function test_an_employee_cannot_create_another_employee(): void
    {
        $owner = $this->makeSeller();
        $employeeUser = User::forceCreate([
            'username' => 'staff_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        Employee::forceCreate([
            'seller_id' => $owner->id, 'user_id' => $employeeUser->id,
            'status' => Employee::STATUS_ACTIVE, 'disk' => 'public',
        ]);

        Auth::login($employeeUser);

        $response = app(EmployeeController::class)->store(new Request([
            'name' => 'Coworker', 'mobile' => (string) random_int(6000000000, 6999999999), 'password' => 'password123',
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame(1, Employee::where('seller_id', $owner->id)->count());
    }

    public function test_an_employee_cannot_deactivate_a_coworker(): void
    {
        $owner = $this->makeSeller();
        $actingEmployeeUser = User::forceCreate([
            'username' => 'staff_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        Employee::forceCreate([
            'seller_id' => $owner->id, 'user_id' => $actingEmployeeUser->id,
            'status' => Employee::STATUS_ACTIVE, 'disk' => 'public',
        ]);
        $coworkerUser = User::forceCreate([
            'username' => 'staff_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $coworker = Employee::forceCreate([
            'seller_id' => $owner->id, 'user_id' => $coworkerUser->id,
            'status' => Employee::STATUS_ACTIVE, 'disk' => 'public',
        ]);

        Auth::login($actingEmployeeUser);

        $response = app(EmployeeController::class)->destroy($coworker->id);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame(Employee::STATUS_ACTIVE, $coworker->fresh()->status);
    }

    public function test_the_owner_can_still_manage_employees(): void
    {
        $owner = $this->makeSeller();
        Auth::login(User::find($owner->user_id));

        $response = app(EmployeeController::class)->store(new Request([
            'name' => 'Staff One', 'mobile' => (string) random_int(6000000000, 6999999999), 'password' => 'password123',
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $this->assertSame(1, Employee::where('seller_id', $owner->id)->count());
    }
}
