<?php

namespace Tests\Feature;

use App\Http\Controllers\Delivery_boy\v1\ApiController;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * docs/CHANGELOG_FEATURE_AUDIT.md (v1.0.11, "Delivery Boy active/inactive availability toggle"): confirmed
 * genuinely missing - `users.active` exists but is an admin-controlled account-enabled flag (used throughout
 * this app's own admin/delivery-boy-list filters), not a delivery boy's own online/offline self-toggle.
 * Added a distinct `is_available` column (migration 2025_02_17_000000) and a self-service
 * toggle_availability() endpoint, deliberately NOT wired into DispatchService::rankAvailableDeliveryBoys()
 * in this pass - that method's existing `where('status', 1)` filter reads yet another of this legacy
 * schema's three overlapping boolean-ish user columns (active/status/active_status), and changing dispatch
 * eligibility logic without a much deeper audit of what each already means risks a real behavior regression
 * in live order assignment. Documented as a follow-up, not silently left undone.
 */
class DeliveryBoyAvailabilityToggleTest extends TestCase
{
    use RefreshDatabase;

    private function makeDeliveryBoy(bool $isAvailable = true): User
    {
        return User::forceCreate([
            'username' => 'delivery_boy_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::DELIVERY_BOY,
            'active' => 1, 'is_available' => $isAvailable, 'city' => '', 'serviceable_zones' => '',
        ]);
    }

    public function test_toggling_with_no_explicit_value_flips_the_current_state(): void
    {
        $deliveryBoy = $this->makeDeliveryBoy(isAvailable: true);
        Auth::login($deliveryBoy);

        $response = app(ApiController::class)->toggle_availability(new Request());
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['error']);
        $this->assertFalse($payload['data']['is_available']);
        $this->assertSame(0, (int) $deliveryBoy->fresh()->is_available);
    }

    public function test_toggling_again_flips_back(): void
    {
        $deliveryBoy = $this->makeDeliveryBoy(isAvailable: false);
        Auth::login($deliveryBoy);

        $response = app(ApiController::class)->toggle_availability(new Request());
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['data']['is_available']);
        $this->assertSame(1, (int) $deliveryBoy->fresh()->is_available);
    }

    public function test_an_explicit_value_sets_state_directly_instead_of_flipping(): void
    {
        $deliveryBoy = $this->makeDeliveryBoy(isAvailable: true);
        Auth::login($deliveryBoy);

        $response = app(ApiController::class)->toggle_availability(new Request(['is_available' => 0]));
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['data']['is_available']);
        $this->assertSame(0, (int) $deliveryBoy->fresh()->is_available);
    }

    public function test_a_non_delivery_boy_cannot_toggle_availability(): void
    {
        $customer = User::forceCreate([
            'username' => 'customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'is_available' => 1,
        ]);
        Auth::login($customer);

        $response = app(ApiController::class)->toggle_availability(new Request());
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['error']);
        $this->assertSame(1, (int) $customer->fresh()->is_available, 'A non-delivery-boy must not be able to change this flag.');
    }

    public function test_get_delivery_boy_details_reports_the_current_availability(): void
    {
        $deliveryBoy = $this->makeDeliveryBoy(isAvailable: false);
        Auth::login($deliveryBoy);

        $response = app(ApiController::class)->get_delivery_boy_details(new Request());
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(0, (int) $payload['data']['is_available']);
    }
}
