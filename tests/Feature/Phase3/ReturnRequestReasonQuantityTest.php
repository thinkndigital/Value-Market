<?php

namespace Tests\Feature\Phase3;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 (docs/PHASE_3_COMMERCE_CORE.md): a customer can now say why they're returning an item and how
 * many units (rather than always the whole ordered quantity) when requesting a return. Covers both the
 * innermost persistence (OrderService::setUserReturnRequest()) and the quantity-cap validation added to
 * OrderService::validateOrderStatus().
 */
class ReturnRequestReasonQuantityTest extends TestCase
{
    use RefreshDatabase;

    private function makeSellerAndCustomer(): array
    {
        $sellerUser = User::forceCreate([
            'username' => 'seller_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate([
            'user_id' => $sellerUser->id,
            'disk' => 'public',
            'status' => 1,
        ]);
        $customer = User::forceCreate([
            'username' => 'customer_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => Role::CUSTOMER,
        ]);

        return [$seller, $customer];
    }

    private function makeDeliveredOrderItem(Seller $seller, User $customer, int $quantity = 5): OrderItems
    {
        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']),
            'slug' => 'cat-' . uniqid(),
            'image' => '',
            'banner' => '',
            'status' => 1,
        ]);
        $product = Product::forceCreate([
            'category_id' => $category->id,
            'seller_id' => $seller->id,
            'name' => json_encode(['en' => 'Product']),
            'slug' => 'product-' . uniqid(),
            'image' => '',
            'deliverable_cities' => '',
            'status' => 1,
            'is_returnable' => 1,
        ]);
        $variant = Product_variants::forceCreate([
            'product_id' => $product->id,
            'price' => 100,
            'status' => 1,
        ]);
        $order = Order::forceCreate([
            'user_id' => $customer->id,
            'mobile' => (string) random_int(6000000000, 6999999999),
            'total' => 100 * $quantity,
            'payment_method' => 'cod',
            'order_payment_currency_id' => 1,
            'order_payment_currency_code' => 'USD',
            'base_currency_code' => 'USD',
            'order_payment_currency_conversion_rate' => 1,
        ]);

        return OrderItems::forceCreate([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'price' => 100,
            'sub_total' => 100 * $quantity,
            'status' => json_encode([['delivered', now()->toDateTimeString()]]),
            'active_status' => 'delivered',
            'order_type' => 'regular_order',
        ]);
    }

    public function test_set_user_return_request_stores_the_given_reason_and_quantity(): void
    {
        [$seller, $customer] = $this->makeSellerAndCustomer();
        $orderItem = $this->makeDeliveredOrderItem($seller, $customer, 5);

        $rowData = (object) [
            'user_id' => $customer->id,
            'product_id' => $orderItem->product_variant_id, // unused by the assertion, just needs a value
            'product_variant_id' => $orderItem->product_variant_id,
            'order_id' => $orderItem->order_id,
            'order_item_id' => $orderItem->id,
            'quantity' => $orderItem->quantity,
        ];

        app(OrderService::class)->setUserReturnRequest($rowData, 'order_items', 'Wrong size', 2);

        $returnRequest = ReturnRequest::where('order_item_id', $orderItem->id)->first();
        $this->assertNotNull($returnRequest);
        $this->assertSame('Wrong size', $returnRequest->reason);
        $this->assertSame(2, $returnRequest->quantity);
    }

    public function test_set_user_return_request_defaults_quantity_to_the_full_ordered_amount(): void
    {
        [$seller, $customer] = $this->makeSellerAndCustomer();
        $orderItem = $this->makeDeliveredOrderItem($seller, $customer, 5);

        $rowData = (object) [
            'user_id' => $customer->id,
            'product_id' => $orderItem->product_variant_id,
            'product_variant_id' => $orderItem->product_variant_id,
            'order_id' => $orderItem->order_id,
            'order_item_id' => $orderItem->id,
            'quantity' => $orderItem->quantity,
        ];

        // No reason/quantity passed - matches an older app client that doesn't send them.
        app(OrderService::class)->setUserReturnRequest($rowData, 'order_items');

        $returnRequest = ReturnRequest::where('order_item_id', $orderItem->id)->first();
        $this->assertNotNull($returnRequest);
        $this->assertNull($returnRequest->reason);
        $this->assertSame(5, $returnRequest->quantity);
    }

    public function test_validate_order_status_rejects_a_return_quantity_over_what_was_ordered(): void
    {
        [$seller, $customer] = $this->makeSellerAndCustomer();
        $orderItem = $this->makeDeliveredOrderItem($seller, $customer, 3);

        $res = app(OrderService::class)->validateOrderStatus(
            (string) $orderItem->id,
            'returned',
            'order_items',
            null,
            true,
            '',
            'Too many requested',
            10 // more than the 3 ordered
        );

        $this->assertTrue($res['error']);
        $this->assertStringContainsString('Return quantity must be between 1 and the ordered quantity', $res['message']);
        $this->assertSame(0, ReturnRequest::where('order_item_id', $orderItem->id)->count());
    }

    public function test_validate_order_status_accepts_a_valid_partial_return_quantity(): void
    {
        [$seller, $customer] = $this->makeSellerAndCustomer();
        $orderItem = $this->makeDeliveredOrderItem($seller, $customer, 3);

        $res = app(OrderService::class)->validateOrderStatus(
            (string) $orderItem->id,
            'returned',
            'order_items',
            null,
            true,
            '',
            'Changed my mind',
            2
        );

        $this->assertFalse($res['error']);
        $returnRequest = ReturnRequest::where('order_item_id', $orderItem->id)->first();
        $this->assertNotNull($returnRequest);
        $this->assertSame('Changed my mind', $returnRequest->reason);
        $this->assertSame(2, $returnRequest->quantity);
    }
}
