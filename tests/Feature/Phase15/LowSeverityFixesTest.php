<?php

namespace Tests\Feature\Phase15;

use App\Http\Controllers\Seller\BranchController;
use App\Http\Controllers\Seller\CrmController;
use App\Http\Controllers\Seller\SupplierController;
use App\Models\Branch;
use App\Models\OrderItems;
use App\Models\Role;
use App\Models\Seller;
use App\Models\ProcurementVendor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Security audit findings (docs/SECURITY_AUDIT.md §6, Findings 11, 13, 14, 15): the LOW-severity batch
 * fixed alongside the HIGH/MEDIUM findings - two unguarded-null TypeError risks and missing field
 * validators. (A separately-flagged Branch/Supplier mass-assignment surface was investigated and found to
 * already be Phase 2's documented, deliberately-deferred `Model::unguard()` finding - see
 * docs/PHASE_2_MASS_ASSIGNMENT_AUDIT.md; not re-addressed superficially at the two-model level here, since
 * doing so would give zero actual protection while implying safety - see SECURITY_AUDIT.md §6 for why.)
 */
class LowSeverityFixesTest extends TestCase
{
    use RefreshDatabase;

    private function makeSeller(): Seller
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);

        return Seller::forceCreate(['user_id' => $user->id, 'disk' => 'public', 'status' => 1]);
    }

    // Finding 11: CrmController::listNotes()/customerLifetimeValue() null-guard.

    public function test_list_notes_returns_a_clean_error_instead_of_a_type_error_when_the_user_has_no_seller(): void
    {
        $randomUser = User::forceCreate([
            'username' => 'nobody_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
        Auth::login($randomUser);

        $response = app(CrmController::class)->listNotes(new Request(), 1);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
    }

    public function test_customer_lifetime_value_returns_a_clean_error_instead_of_a_type_error_when_the_user_has_no_seller(): void
    {
        $randomUser = User::forceCreate([
            'username' => 'nobody_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
        Auth::login($randomUser);

        $response = app(CrmController::class)->customerLifetimeValue(new Request(), 1);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
    }

    // Finding 13: SupplierController::update() validation.

    public function test_supplier_update_rejects_a_malformed_email(): void
    {
        $seller = $this->makeSeller();
        $supplier = ProcurementVendor::forceCreate(['seller_id' => $seller->id, 'name' => 'Acme', 'status' => ProcurementVendor::STATUS_ACTIVE]);
        Auth::login(User::find($seller->user_id));

        $response = app(SupplierController::class)->update(new Request(['email' => 'not-an-email']), $supplier->id);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
    }

    public function test_supplier_update_rejects_a_status_outside_the_enum(): void
    {
        $seller = $this->makeSeller();
        $supplier = ProcurementVendor::forceCreate(['seller_id' => $seller->id, 'name' => 'Acme', 'status' => ProcurementVendor::STATUS_ACTIVE]);
        Auth::login(User::find($seller->user_id));

        $response = app(SupplierController::class)->update(new Request(['status' => 99]), $supplier->id);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame(ProcurementVendor::STATUS_ACTIVE, $supplier->fresh()->status);
    }

    public function test_supplier_update_still_accepts_a_valid_change(): void
    {
        $seller = $this->makeSeller();
        $supplier = ProcurementVendor::forceCreate(['seller_id' => $seller->id, 'name' => 'Acme', 'status' => ProcurementVendor::STATUS_ACTIVE]);
        Auth::login(User::find($seller->user_id));

        $response = app(SupplierController::class)->update(new Request(['name' => 'Acme Renamed']), $supplier->id);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $this->assertSame('Acme Renamed', $supplier->fresh()->name);
    }

    // Finding 14: BranchController lat/long validation.

    public function test_branch_store_rejects_an_out_of_range_latitude(): void
    {
        $seller = $this->makeSeller();
        Auth::login(User::find($seller->user_id));

        $response = app(BranchController::class)->store(new Request(['name' => 'Main', 'latitude' => 999]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame(0, Branch::where('seller_id', $seller->id)->count());
    }

    public function test_branch_store_accepts_a_valid_latitude_and_longitude(): void
    {
        $seller = $this->makeSeller();
        Auth::login(User::find($seller->user_id));

        $response = app(BranchController::class)->store(new Request(['name' => 'Main', 'latitude' => 24.7, 'longitude' => 46.7]));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
    }

    // Finding 15: CrmController tag color validation.

    public function test_tag_customer_rejects_a_non_hex_color(): void
    {
        $seller = $this->makeSeller();
        $customer = User::forceCreate([
            'username' => 'buyer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
        OrderItems::forceCreate(['order_id' => 1, 'user_id' => $customer->id, 'seller_id' => $seller->id, 'product_variant_id' => 1, 'quantity' => 1]);
        Auth::login(User::find($seller->user_id));

        $response = app(CrmController::class)->tagCustomer(new Request([
            'customer_user_id' => $customer->id, 'tag_name' => 'VIP', 'color' => '<script>alert(1)</script>',
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
    }
}
