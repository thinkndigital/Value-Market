<?php

namespace Tests\Feature\Phase11;

use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Role;
use App\Models\User;
use App\Services\CrmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'username' => 'customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
    }

    private function makeDeliveredOrderItem(int $customerId, int $sellerId, float $subTotal): OrderItems
    {
        $order = Order::forceCreate([
            'user_id' => $customerId, 'mobile' => (string) random_int(6000000000, 6999999999), 'total' => $subTotal,
            'payment_method' => 'cod', 'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD',
            'base_currency_code' => 'USD', 'order_payment_currency_conversion_rate' => 1,
        ]);

        return OrderItems::forceCreate([
            'user_id' => $customerId, 'order_id' => $order->id, 'seller_id' => $sellerId,
            'product_variant_id' => 1, 'quantity' => 1, 'price' => $subTotal, 'sub_total' => $subTotal,
            'status' => json_encode([['delivered', now()->toDateTimeString()]]),
            'active_status' => 'delivered', 'order_type' => 'regular_order',
        ]);
    }

    public function test_add_note_and_list_notes_round_trips(): void
    {
        $customer = $this->makeCustomer();

        app(CrmService::class)->addNote($customer->id, 99, 'Prefers weekend delivery', sellerId: 5);
        $notes = app(CrmService::class)->listNotes($customer->id, sellerId: 5);

        $this->assertCount(1, $notes);
        $this->assertSame('Prefers weekend delivery', $notes->first()->note);
    }

    public function test_notes_are_scoped_per_seller_and_do_not_leak(): void
    {
        $customer = $this->makeCustomer();
        app(CrmService::class)->addNote($customer->id, 99, 'Seller 5 note', sellerId: 5);
        app(CrmService::class)->addNote($customer->id, 99, 'Seller 6 note', sellerId: 6);

        $notesForSeller5 = app(CrmService::class)->listNotes($customer->id, sellerId: 5);

        $this->assertCount(1, $notesForSeller5);
        $this->assertSame('Seller 5 note', $notesForSeller5->first()->note);
    }

    public function test_empty_note_is_rejected(): void
    {
        $customer = $this->makeCustomer();

        $this->expectException(\InvalidArgumentException::class);
        app(CrmService::class)->addNote($customer->id, 99, '   ', sellerId: 5);
    }

    public function test_tagging_a_customer_twice_with_the_same_tag_is_idempotent(): void
    {
        $customer = $this->makeCustomer();
        $crm = app(CrmService::class);
        $tag = $crm->createTag('VIP', sellerId: 5);

        $crm->tagCustomer($customer->id, $tag->id, 99);
        $crm->tagCustomer($customer->id, $tag->id, 99);

        $this->assertSame(1, \App\Models\CustomerTagAssignment::where('customer_user_id', $customer->id)->count());
    }

    public function test_untag_removes_the_assignment(): void
    {
        $customer = $this->makeCustomer();
        $crm = app(CrmService::class);
        $tag = $crm->createTag('VIP', sellerId: 5);
        $crm->tagCustomer($customer->id, $tag->id, 99);

        $crm->untagCustomer($customer->id, $tag->id);

        $this->assertCount(0, $crm->customerTags($customer->id, sellerId: 5));
    }

    public function test_customer_lifetime_value_sums_only_delivered_items_for_that_seller(): void
    {
        $customer = $this->makeCustomer();
        $this->makeDeliveredOrderItem($customer->id, sellerId: 5, subTotal: 100);
        $this->makeDeliveredOrderItem($customer->id, sellerId: 5, subTotal: 50);
        $this->makeDeliveredOrderItem($customer->id, sellerId: 6, subTotal: 999); // different seller

        $clv = app(CrmService::class)->customerLifetimeValue($customer->id, sellerId: 5);

        $this->assertSame(150.0, $clv);
    }

    public function test_evaluate_segment_matches_customers_meeting_min_total_spent(): void
    {
        $bigSpender = $this->makeCustomer();
        $smallSpender = $this->makeCustomer();
        $this->makeDeliveredOrderItem($bigSpender->id, sellerId: 5, subTotal: 500);
        $this->makeDeliveredOrderItem($smallSpender->id, sellerId: 5, subTotal: 10);

        $segment = app(CrmService::class)->createSegment('Big spenders', ['min_total_spent' => 100], sellerId: 5);
        $matches = app(CrmService::class)->evaluateSegment($segment);

        $this->assertContains($bigSpender->id, $matches);
        $this->assertNotContains($smallSpender->id, $matches);
    }

    public function test_evaluate_segment_matches_customers_meeting_min_orders(): void
    {
        $frequent = $this->makeCustomer();
        $occasional = $this->makeCustomer();
        $this->makeDeliveredOrderItem($frequent->id, sellerId: 5, subTotal: 10);
        $this->makeDeliveredOrderItem($frequent->id, sellerId: 5, subTotal: 10);
        $this->makeDeliveredOrderItem($frequent->id, sellerId: 5, subTotal: 10);
        $this->makeDeliveredOrderItem($occasional->id, sellerId: 5, subTotal: 10);

        $segment = app(CrmService::class)->createSegment('Frequent buyers', ['min_orders' => 3], sellerId: 5);
        $matches = app(CrmService::class)->evaluateSegment($segment);

        $this->assertContains($frequent->id, $matches);
        $this->assertNotContains($occasional->id, $matches);
    }

    public function test_evaluate_segment_is_scoped_to_the_segments_own_seller(): void
    {
        $customer = $this->makeCustomer();
        $this->makeDeliveredOrderItem($customer->id, sellerId: 5, subTotal: 500);

        $segmentForOtherSeller = app(CrmService::class)->createSegment('Big spenders', ['min_total_spent' => 100], sellerId: 6);
        $matches = app(CrmService::class)->evaluateSegment($segmentForOtherSeller);

        $this->assertNotContains($customer->id, $matches);
    }
}
