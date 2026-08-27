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
}
