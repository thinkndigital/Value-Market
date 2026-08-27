<?php

namespace Tests\Feature\Phase8;

use App\Models\DeliveryEarning;
use App\Models\Order;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\DeliveryEarningService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryEarningServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeDriver(): User
    {
        return User::forceCreate([
            'username' => 'driver_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::DELIVERY_BOY, 'balance' => 0,
        ]);
    }

    private function makeOrder(float $deliveryCharge = 0): Order
    {
        return Order::forceCreate([
            'user_id' => 1, 'mobile' => (string) random_int(6000000000, 6999999999), 'total' => 100,
            'payment_method' => 'cod', 'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD',
            'base_currency_code' => 'USD', 'order_payment_currency_conversion_rate' => 1,
            'delivery_charge' => $deliveryCharge,
        ]);
    }

    private function mockSettings(array $overrides): void
    {
        $this->mock(SettingService::class, function ($mock) use ($overrides) {
            $mock->shouldReceive('getSettings')
                ->with('system_settings', true)
                ->andReturn(json_encode(array_merge(['app_name' => 'Test Store'], $overrides)));
        });
    }

    public function test_returns_null_and_does_not_credit_when_earnings_are_disabled(): void
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder();
        $this->mockSettings(['delivery_earning_status' => 0]);

        $result = app(DeliveryEarningService::class)->creditForDeliveredItem(501, $order->id, $driver->id);

        $this->assertNull($result);
        $this->assertSame(0.0, (float) $driver->fresh()->balance);
    }

    public function test_returns_null_when_no_delivery_boy_is_assigned(): void
    {
        $order = $this->makeOrder();
        $this->mockSettings(['delivery_earning_status' => 1, 'delivery_earning_type' => 'flat', 'delivery_earning_value' => 5]);

        $result = app(DeliveryEarningService::class)->creditForDeliveredItem(502, $order->id, null);

        $this->assertNull($result);
    }

    public function test_a_flat_rate_credits_the_configured_amount(): void
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder();
        $this->mockSettings(['delivery_earning_status' => 1, 'delivery_earning_type' => 'flat', 'delivery_earning_value' => 7.5]);

        $earning = app(DeliveryEarningService::class)->creditForDeliveredItem(503, $order->id, $driver->id);

        $this->assertNotNull($earning);
        $this->assertSame(7.5, (float) $earning->amount);
        $this->assertSame(7.5, (float) $driver->fresh()->balance);
    }

    public function test_a_percentage_rate_is_computed_against_the_orders_delivery_charge(): void
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder(deliveryCharge: 40);
        $this->mockSettings(['delivery_earning_status' => 1, 'delivery_earning_type' => 'percentage', 'delivery_earning_value' => 25]);

        $earning = app(DeliveryEarningService::class)->creditForDeliveredItem(504, $order->id, $driver->id);

        $this->assertSame(10.0, (float) $earning->amount); // 25% of 40
        $this->assertSame(10.0, (float) $driver->fresh()->balance);
    }

    public function test_the_same_order_item_is_never_credited_twice(): void
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder();
        $this->mockSettings(['delivery_earning_status' => 1, 'delivery_earning_type' => 'flat', 'delivery_earning_value' => 5]);

        app(DeliveryEarningService::class)->creditForDeliveredItem(505, $order->id, $driver->id);
        $second = app(DeliveryEarningService::class)->creditForDeliveredItem(505, $order->id, $driver->id);

        $this->assertNull($second);
        $this->assertSame(1, DeliveryEarning::where('order_item_id', 505)->count());
        $this->assertSame(5.0, (float) $driver->fresh()->balance);
    }
}
