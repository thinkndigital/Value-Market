<?php

namespace Tests\Feature\Phase11;

use App\Http\Controllers\Seller\CrmController;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CrmControllerTest extends TestCase
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

    private function makeCustomerWhoOrderedFrom(Seller $seller): User
    {
        $customer = User::forceCreate([
            'username' => 'customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
        $order = Order::forceCreate([
            'user_id' => $customer->id, 'mobile' => (string) random_int(6000000000, 6999999999), 'total' => 100,
            'payment_method' => 'cod', 'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD',
            'base_currency_code' => 'USD', 'order_payment_currency_conversion_rate' => 1,
        ]);
        OrderItems::forceCreate([
            'user_id' => $customer->id, 'order_id' => $order->id, 'seller_id' => $seller->id,
            'product_variant_id' => 1, 'quantity' => 1, 'price' => 100, 'sub_total' => 100,
            'status' => json_encode([['delivered', now()->toDateTimeString()]]),
            'active_status' => 'delivered', 'order_type' => 'regular_order',
        ]);

        return $customer;
    }

    public function test_a_seller_can_note_a_customer_who_ordered_from_them(): void
    {
        $seller = $this->makeSeller();
        $customer = $this->makeCustomerWhoOrderedFrom($seller);
        Auth::login(User::find($seller->user_id));

        $response = app(CrmController::class)->addNote(new Request([
            'customer_user_id' => $customer->id, 'note' => 'Great customer',
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
    }

    public function test_a_seller_cannot_note_a_customer_who_never_ordered_from_them(): void
    {
        $seller = $this->makeSeller();
        $stranger = User::forceCreate([
            'username' => 'stranger_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
        Auth::login(User::find($seller->user_id));

        $response = app(CrmController::class)->addNote(new Request([
            'customer_user_id' => $stranger->id, 'note' => 'Snooping',
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame(0, \App\Models\CustomerNote::count());
    }

    public function test_a_seller_cannot_list_notes_seller_a_wrote_about_a_shared_customer(): void
    {
        $sellerA = $this->makeSeller();
        $sellerB = $this->makeSeller();
        $customer = $this->makeCustomerWhoOrderedFrom($sellerA);
        // Give the customer an order from seller B too, so the ownership check alone wouldn't catch this -
        // the seller-scoping on the notes themselves has to hold.
        $orderB = Order::forceCreate([
            'user_id' => $customer->id, 'mobile' => (string) random_int(6000000000, 6999999999), 'total' => 50,
            'payment_method' => 'cod', 'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD',
            'base_currency_code' => 'USD', 'order_payment_currency_conversion_rate' => 1,
        ]);
        OrderItems::forceCreate([
            'user_id' => $customer->id, 'order_id' => $orderB->id, 'seller_id' => $sellerB->id,
            'product_variant_id' => 1, 'quantity' => 1, 'price' => 50, 'sub_total' => 50,
            'status' => json_encode([['delivered', now()->toDateTimeString()]]),
            'active_status' => 'delivered', 'order_type' => 'regular_order',
        ]);

        Auth::login(User::find($sellerA->user_id));
        app(CrmController::class)->addNote(new Request(['customer_user_id' => $customer->id, 'note' => 'Seller A private note']));

        Auth::login(User::find($sellerB->user_id));
        $response = app(CrmController::class)->listNotes(new Request(), $customer->id);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $this->assertCount(0, $data['data'], "seller B must not see seller A's private note about a customer they both share");
    }

    public function test_lifetime_value_endpoint_is_scoped_to_the_requesting_sellers_own_sales(): void
    {
        $seller = $this->makeSeller();
        $customer = $this->makeCustomerWhoOrderedFrom($seller);
        Auth::login(User::find($seller->user_id));

        $response = app(CrmController::class)->customerLifetimeValue(new Request(), $customer->id);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $this->assertSame(100.0, (float) $data['data']['lifetime_value']);
    }
}
