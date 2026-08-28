<?php

namespace Tests\Feature\Phase15;

use App\Http\Controllers\Delivery_boy\UserController;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Same vulnerability as Seller\UserController (see SellerUserControllerAccountOwnershipTest), found moments
 * later in the same sweep: delivery_boy/account/update/{id} took $id straight from the URL with no
 * ownership check, letting any authenticated delivery boy reset any other account's password and overwrite
 * its email/mobile/address (role_id also force-set, with no filter on $id's role either). edit() had the
 * same gap for read access via route-model binding.
 */
class DeliveryBoyUserControllerAccountOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function makeDeliveryBoy(): User
    {
        return User::forceCreate([
            'username' => 'delivery_' . uniqid(), 'password' => Hash::make('original-password'), 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::DELIVERY_BOY,
            'email' => 'original_' . uniqid() . '@example.com',
        ]);
    }

    public function test_update_denies_a_delivery_boy_updating_another_users_account(): void
    {
        $victim = $this->makeDeliveryBoy();
        $attacker = $this->makeDeliveryBoy();
        Auth::login($attacker);

        $request = new Request([
            'name' => 'Hijacked Name', 'email' => 'attacker-controlled@example.com', 'mobile' => '6000000001',
            'old_password' => 'irrelevant', 'new_password' => 'hacked-password',
        ]);
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $response = app(UserController::class)->update($request, $victim->id);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $victim->refresh();
        $this->assertNotSame('Hijacked Name', $victim->username);
        $this->assertTrue(Hash::check('original-password', $victim->password), 'The victim password must not have been reset.');
    }

    public function test_edit_denies_a_delivery_boy_viewing_another_users_account(): void
    {
        $victim = $this->makeDeliveryBoy();
        $attacker = $this->makeDeliveryBoy();
        Auth::login($attacker);

        $view = app(UserController::class)->edit($victim);

        $this->assertSame('admin.pages.views.no_data_found', $view->name());
    }

    public function test_edit_allows_a_delivery_boy_to_view_their_own_account(): void
    {
        $owner = $this->makeDeliveryBoy();
        Auth::login($owner);

        $view = app(UserController::class)->edit($owner);

        $this->assertSame('delivery_boy.pages.forms.account', $view->name());
    }

    public function test_update_lets_a_delivery_boy_past_the_ownership_check_for_their_own_account(): void
    {
        $owner = $this->makeDeliveryBoy();
        Auth::login($owner);

        $response = app(UserController::class)->update(new Request([
            'name' => 'My Own Name', 'email' => $owner->email, 'mobile' => '6000000002',
        ]), $owner->id);

        $owner->refresh();
        $this->assertSame('My Own Name', $owner->username);
    }
}
