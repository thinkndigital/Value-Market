<?php

namespace Tests\Feature\Phase7;

use App\Http\Controllers\AffiliateController;
use App\Models\AffiliateLink;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AffiliateControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::forceCreate([
            'username' => 'user_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
    }

    public function test_a_logged_in_user_can_create_and_list_their_own_link(): void
    {
        $user = $this->makeUser();
        Auth::login($user);

        $storeResponse = app(AffiliateController::class)->store(new Request(['target_type' => 'platform']));
        $stored = json_decode($storeResponse->getContent(), true);
        $this->assertFalse($stored['error']);

        $listResponse = app(AffiliateController::class)->list();
        $list = json_decode($listResponse->getContent(), true);
        $this->assertCount(1, $list['data']);
    }

    public function test_list_only_returns_the_authenticated_users_own_links(): void
    {
        $owner = $this->makeUser();
        $stranger = $this->makeUser();
        Auth::login($owner);
        app(AffiliateController::class)->store(new Request(['target_type' => 'platform']));

        Auth::login($stranger);
        $listResponse = app(AffiliateController::class)->list();
        $list = json_decode($listResponse->getContent(), true);

        $this->assertCount(0, $list['data']);
    }

    public function test_track_and_redirect_increments_the_click_counter_for_an_unauthenticated_visitor(): void
    {
        $owner = $this->makeUser();
        $link = AffiliateLink::forceCreate([
            'user_id' => $owner->id, 'target_type' => AffiliateLink::TARGET_PLATFORM,
            'code' => 'testcode1', 'status' => AffiliateLink::STATUS_ACTIVE,
        ]);

        $response = app(AffiliateController::class)->trackAndRedirect(new Request(), 'testcode1');

        $this->assertSame(1, $link->fresh()->clicks_count);
        $this->assertTrue($response->isRedirect());
    }
}
