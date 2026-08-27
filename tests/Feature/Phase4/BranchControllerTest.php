<?php

namespace Tests\Feature\Phase4;

use App\Http\Controllers\Seller\BranchController;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Phase 4 (docs/PHASE_4_VENDOR_SYSTEM.md): a seller can manage their own branches; ownership is enforced the
 * same way Phase 2's IDOR fixes and Phase 3's seller-ownership fix are - scoped by the authenticated user's
 * resolved seller_id (via TenantContext here), never a request-supplied id taken at face value.
 */
class BranchControllerTest extends TestCase
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

    public function test_a_seller_can_create_and_list_their_own_branch(): void
    {
        $seller = $this->makeSeller();
        Auth::login(User::find($seller->user_id));

        $request = new Request(['name' => 'Main Branch', 'address' => '123 Street']);
        $response = app(BranchController::class)->store($request);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $this->assertSame('Main Branch', $data['data']['name']);

        $listResponse = app(BranchController::class)->list();
        $list = json_decode($listResponse->getContent(), true);
        $this->assertCount(1, $list['data']);
    }

    public function test_a_seller_cannot_update_another_sellers_branch(): void
    {
        $owner = $this->makeSeller();
        $stranger = $this->makeSeller();
        $branch = Branch::forceCreate(['seller_id' => $owner->id, 'name' => 'Owner Branch', 'status' => Branch::STATUS_ACTIVE]);

        Auth::login(User::find($stranger->user_id));

        $response = app(BranchController::class)->update(new Request(['name' => 'Hijacked']), $branch->id);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame('Owner Branch', $branch->fresh()->name);
    }

    public function test_a_seller_cannot_delete_another_sellers_branch(): void
    {
        $owner = $this->makeSeller();
        $stranger = $this->makeSeller();
        $branch = Branch::forceCreate(['seller_id' => $owner->id, 'name' => 'Owner Branch', 'status' => Branch::STATUS_ACTIVE]);

        Auth::login(User::find($stranger->user_id));

        $response = app(BranchController::class)->destroy($branch->id);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertNotNull($branch->fresh());
    }

    public function test_the_owning_seller_can_update_their_own_branch(): void
    {
        $owner = $this->makeSeller();
        $branch = Branch::forceCreate(['seller_id' => $owner->id, 'name' => 'Old Name', 'status' => Branch::STATUS_ACTIVE]);

        Auth::login(User::find($owner->user_id));

        $response = app(BranchController::class)->update(new Request(['name' => 'New Name']), $branch->id);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $this->assertSame('New Name', $branch->fresh()->name);
    }
}
