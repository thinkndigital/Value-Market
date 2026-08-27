<?php

namespace Tests\Feature\Phase4;

use App\Models\Employee;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (docs/PHASE_4_VENDOR_SYSTEM.md): TenantContext::sellerIdFor() is the one place employee
 * tenant-scoping is centralized - every caller that goes through it (new Phase 4 code, and Phase 2/3's own
 * fixes) picks up employee support. See its docblock for the explicit scope boundary this does not cover.
 */
class TenantContextEmployeeTest extends TestCase
{
    use RefreshDatabase;

    private function makeSellerUser(): User
    {
        return User::forceCreate([
            'username' => 'owner_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => Role::SELLER,
        ]);
    }

    public function test_an_owning_seller_resolves_to_their_own_seller_id(): void
    {
        $ownerUser = $this->makeSellerUser();
        $seller = Seller::forceCreate(['user_id' => $ownerUser->id, 'disk' => 'public', 'status' => 1]);

        $resolved = app(TenantContext::class)->sellerIdFor($ownerUser->fresh());

        $this->assertSame($seller->id, $resolved);
    }

    public function test_an_active_employee_resolves_to_their_employers_seller_id(): void
    {
        $ownerUser = $this->makeSellerUser();
        $seller = Seller::forceCreate(['user_id' => $ownerUser->id, 'disk' => 'public', 'status' => 1]);

        $employeeUser = $this->makeSellerUser();
        Employee::forceCreate([
            'seller_id' => $seller->id,
            'user_id' => $employeeUser->id,
            'status' => Employee::STATUS_ACTIVE,
            'disk' => 'public',
        ]);

        $resolved = app(TenantContext::class)->sellerIdFor($employeeUser->fresh());

        $this->assertSame($seller->id, $resolved);
    }

    public function test_a_deactivated_employee_does_not_resolve(): void
    {
        $ownerUser = $this->makeSellerUser();
        $seller = Seller::forceCreate(['user_id' => $ownerUser->id, 'disk' => 'public', 'status' => 1]);

        $employeeUser = $this->makeSellerUser();
        Employee::forceCreate([
            'seller_id' => $seller->id,
            'user_id' => $employeeUser->id,
            'status' => Employee::STATUS_INACTIVE,
            'disk' => 'public',
        ]);

        $resolved = app(TenantContext::class)->sellerIdFor($employeeUser->fresh());

        $this->assertNull($resolved);
    }

    public function test_a_user_with_no_seller_and_no_employee_record_resolves_to_null(): void
    {
        $strangerUser = $this->makeSellerUser();

        $resolved = app(TenantContext::class)->sellerIdFor($strangerUser->fresh());

        $this->assertNull($resolved);
    }
}
