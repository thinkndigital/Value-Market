<?php

namespace Tests\Feature\Phase1;

use App\Http\Controllers\Admin\AddressController;
use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Phase 1 (docs/SECURITY_AUDIT.md, Task 8): regression test for a confirmed IDOR - before this phase,
 * AddressController::store()'s update path and destroy() operated purely by address id, with no check
 * that the address belonged to the requesting user. Any authenticated customer could edit or delete any
 * other customer's saved address. This proves the fix actually blocks that, not just that it was written.
 */
class AddressOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithAddress(): array
    {
        $user = User::forceCreate([
            'username' => 'addr_user_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
        ]);

        $address = Address::forceCreate([
            'user_id' => $user->id,
            'name' => 'Home',
            'is_default' => 0,
        ]);

        return [$user, $address];
    }

    public function test_a_user_cannot_delete_another_users_address(): void
    {
        [, $victimAddress] = $this->makeUserWithAddress();
        [$attacker] = $this->makeUserWithAddress();

        app(AddressController::class)->destroy((string) $victimAddress->id, $attacker->id);

        $this->assertNotNull(
            Address::find($victimAddress->id),
            'The address must still exist - an attacker must not be able to delete another user\'s address by guessing its id.'
        );
    }

    public function test_a_user_can_delete_their_own_address(): void
    {
        [$owner, $address] = $this->makeUserWithAddress();

        app(AddressController::class)->destroy((string) $address->id, $owner->id);

        $this->assertNull(Address::find($address->id));
    }

    /**
     * AddressController::store() reads its input via the global request() helper, not the $request
     * parameter directly - so a test has to bind its Request into the container (matching what Laravel
     * itself does for a real HTTP request) rather than merely pass it as an argument, or request()
     * resolves to an unrelated empty request and the method silently takes the wrong code path
     * entirely. Discovered by this test initially passing for the wrong reason (the update never ran at
     * all, for either owner or attacker) - binding it into the container was the fix.
     */
    private function bindRequest(Request $request): void
    {
        $this->app->instance('request', $request);
    }

    public function test_a_user_cannot_update_another_users_address(): void
    {
        [, $victimAddress] = $this->makeUserWithAddress();
        [$attacker] = $this->makeUserWithAddress();

        $request = new Request([
            'id' => $victimAddress->id,
            'user_id' => $attacker->id,
            'name' => 'Hijacked Address',
        ]);
        $this->bindRequest($request);

        app(AddressController::class)->store($request);

        $this->assertSame(
            'Home',
            Address::find($victimAddress->id)->name,
            'Another user\'s address must not be modifiable by an attacker who only knows its id.'
        );
    }

    public function test_a_user_can_update_their_own_address(): void
    {
        [$owner, $address] = $this->makeUserWithAddress();

        $request = new Request([
            'id' => $address->id,
            'user_id' => $owner->id,
            'name' => 'Updated Home',
        ]);
        $this->bindRequest($request);

        app(AddressController::class)->store($request);

        $this->assertSame('Updated Home', Address::find($address->id)->name);
    }
}
