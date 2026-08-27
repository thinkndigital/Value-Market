<?php

namespace Tests\Feature\Phase8;

use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Role;
use App\Models\User;
use App\Services\DispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeDeliveryBoy(string $zones = '', int $status = 1): User
    {
        return User::forceCreate([
            'username' => 'driver_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'serviceable_zones' => $zones, 'type' => 'phone',
            'role_id' => Role::DELIVERY_BOY, 'status' => $status,
        ]);
    }

    private function makeOrderItemAssignedTo(?int $deliveryBoyId, string $activeStatus = 'processed'): OrderItems
    {
        $order = Order::forceCreate([
            'user_id' => 1, 'mobile' => (string) random_int(6000000000, 6999999999), 'total' => 10,
            'payment_method' => 'cod', 'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD',
            'base_currency_code' => 'USD', 'order_payment_currency_conversion_rate' => 1,
        ]);

        return OrderItems::forceCreate([
            'user_id' => 1, 'order_id' => $order->id, 'seller_id' => 1, 'product_variant_id' => 1,
            'quantity' => 1, 'price' => 10, 'sub_total' => 10,
            'status' => json_encode([[$activeStatus, now()->toDateTimeString()]]),
            'active_status' => $activeStatus, 'order_type' => 'regular_order',
            'delivery_boy_id' => $deliveryBoyId,
        ]);
    }

    public function test_rank_available_delivery_boys_filters_by_zone(): void
    {
        $inZone = $this->makeDeliveryBoy(zones: '5,7');
        $outOfZone = $this->makeDeliveryBoy(zones: '9');

        $ranked = app(DispatchService::class)->rankAvailableDeliveryBoys(zoneId: 7);

        $this->assertTrue($ranked->pluck('id')->contains($inZone->id));
        $this->assertFalse($ranked->pluck('id')->contains($outOfZone->id));
    }

    public function test_rank_available_delivery_boys_excludes_inactive_drivers(): void
    {
        $active = $this->makeDeliveryBoy(zones: '5', status: 1);
        $inactive = $this->makeDeliveryBoy(zones: '5', status: 0);

        $ranked = app(DispatchService::class)->rankAvailableDeliveryBoys(zoneId: 5);

        $this->assertTrue($ranked->pluck('id')->contains($active->id));
        $this->assertFalse($ranked->pluck('id')->contains($inactive->id));
    }

    public function test_rank_available_delivery_boys_orders_by_fewest_active_deliveries(): void
    {
        $busy = $this->makeDeliveryBoy(zones: '5');
        $idle = $this->makeDeliveryBoy(zones: '5');
        $this->makeOrderItemAssignedTo($busy->id, 'processed');
        $this->makeOrderItemAssignedTo($busy->id, 'shipped');
        $this->makeOrderItemAssignedTo($idle->id, 'delivered'); // completed - should not count as active load

        $ranked = app(DispatchService::class)->rankAvailableDeliveryBoys(zoneId: 5);

        $this->assertSame($idle->id, $ranked->first()->id);
    }

    public function test_auto_assign_picks_the_least_loaded_zone_matching_driver_and_updates_the_order_item(): void
    {
        $busy = $this->makeDeliveryBoy(zones: '5');
        $idle = $this->makeDeliveryBoy(zones: '5');
        $this->makeOrderItemAssignedTo($busy->id, 'processed');
        $orderItem = $this->makeOrderItemAssignedTo(null, 'processed');

        $assigned = app(DispatchService::class)->autoAssign($orderItem->id, zoneId: 5);

        $this->assertSame($idle->id, $assigned->id);
        $this->assertSame($idle->id, $orderItem->fresh()->delivery_boy_id);
    }

    public function test_auto_assign_returns_null_when_no_driver_matches_the_zone(): void
    {
        $orderItem = $this->makeOrderItemAssignedTo(null, 'processed');

        $assigned = app(DispatchService::class)->autoAssign($orderItem->id, zoneId: 999);

        $this->assertNull($assigned);
        $this->assertNull($orderItem->fresh()->delivery_boy_id);
    }
}
