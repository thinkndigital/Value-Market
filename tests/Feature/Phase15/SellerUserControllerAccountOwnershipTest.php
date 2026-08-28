<?php

namespace Tests\Feature\Phase15;

use App\Http\Controllers\Seller\UserController;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Found while continuing docs/SECURITY_AUDIT.md §6.4's SetDefaultStore sweep. Seller\UserController::edit()/
 * update() (routes seller/account/{id} and seller/account/update/{id}) had NO ownership check at all - $id
 * came straight from the URL/form action and was used to User::find($id) with no filter on the currently
 * authenticated seller. update() then overwrote that target user's username/email/mobile/address/password
 * (a full account takeover via password reset) and force-set its role_id to Role::SELLER, with no check on
 * $id's role either - the most severe finding of this whole sweep, well beyond the store_id-scoped IDORs it
 * started from. Both methods are "my own account settings" pages; fixed by requiring $id === Auth::id().
 */
class SellerUserControllerAccountOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function makeSeller(): User
    {
        return User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => Hash::make('original-password'), 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
            'email' => 'original_' . uniqid() . '@example.com',
        ]);
    }

    public function test_update_denies_a_seller_updating_another_users_account(): void
    {
        $victim = $this->makeSeller();
        $attacker = $this->makeSeller();
        Auth::login($attacker);

        $response = app(UserController::class)->update(new Request([
            'name' => 'Hijacked Name', 'email' => 'attacker-controlled@example.com',
            'address' => 'x', 'store_name' => 'x', 'account_number' => 'x',
            'account_name' => 'x', 'bank_name' => 'x', 'bank_code' => 'x',
            'old_password' => 'irrelevant', 'new_password' => '1', 'password' => 'hacked-password',
            'confirm_password' => 'hacked-password',
        ]), $victim->id);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $victim->refresh();
        $this->assertNotSame('Hijacked Name', $victim->username);
        $this->assertTrue(Hash::check('original-password', $victim->password), 'The victim password must not have been reset.');
    }

    public function test_edit_denies_a_seller_viewing_another_users_account(): void
    {
        $victim = $this->makeSeller();
        $attacker = $this->makeSeller();
        Auth::login($attacker);

        $view = app(UserController::class)->edit($victim->id);

        $this->assertSame('admin.pages.views.no_data_found', $view->name());
    }

    public function test_edit_allows_a_seller_to_view_their_own_account(): void
    {
        $owner = $this->makeSeller();
        $store = Store::forceCreate(['name' => json_encode(['en' => 'Store']), 'slug' => 'store-' . uniqid(), 'status' => 1]);
        Auth::login($owner);
        session(['store_id' => $store->id]);

        $view = app(UserController::class)->edit($owner->id);

        $this->assertSame('seller.pages.forms.account', $view->name());
    }

    public function test_update_lets_a_seller_past_the_ownership_check_for_their_own_account(): void
    {
        $owner = $this->makeSeller();
        Auth::login($owner);

        // Deliberately minimal/invalid request - not enough to reach a successful update, but proves the
        // request got past the ownership gate: the failure here is a validation error, not "Data Not Found".
        $response = app(UserController::class)->update(new Request([]), $owner->id);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertNotSame('Data Not Found', $data['message'] ?? null);
    }
}
