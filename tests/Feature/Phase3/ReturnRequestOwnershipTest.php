<?php

namespace Tests\Feature\Phase3;

use App\Http\Controllers\Seller\ReturnRequestController;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Phase 3 (docs/PHASE_3_COMMERCE_CORE.md): Seller\ReturnRequestController::update() used to do
 * ReturnRequest::find($returnRequestId) with no check the request belonged to the logged-in seller - its
 * own list() was correctly scoped, update() wasn't. Same IDOR shape Phase 2 fixed elsewhere in this app:
 * any seller could transition another seller's return request by guessing its id.
 */
class ReturnRequestOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function makeSeller(): Seller
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => Role::SELLER,
        ]);

        return Seller::forceCreate([
            'user_id' => $user->id,
            'disk' => 'public',
            'status' => 1,
        ]);
    }

    private function makeReturnRequestFor(Seller $seller): ReturnRequest
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
        ]);
        $variant = Product_variants::forceCreate([
            'product_id' => $product->id,
            'price' => 100,
            'status' => 1,
        ]);
        $order = Order::forceCreate([
            'user_id' => $seller->user_id,
            'mobile' => (string) random_int(6000000000, 6999999999),
            'total' => 200,
            'payment_method' => 'cod',
            'order_payment_currency_id' => 1,
            'order_payment_currency_code' => 'USD',
            'base_currency_code' => 'USD',
            'order_payment_currency_conversion_rate' => 1,
        ]);
        $orderItem = OrderItems::forceCreate([
            'user_id' => $seller->user_id,
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'price' => 100,
            'sub_total' => 200,
            'status' => json_encode([['received', now()->toDateTimeString()]]),
            'active_status' => 'delivered',
            'order_type' => 'regular_order',
        ]);

        return ReturnRequest::forceCreate([
            'user_id' => $seller->user_id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'status' => ReturnRequest::STATUS_PENDING,
        ]);
    }

    public function test_a_seller_cannot_transition_another_sellers_return_request(): void
    {
        $owner = $this->makeSeller();
        $stranger = $this->makeSeller();
        $returnRequest = $this->makeReturnRequestFor($owner);

        Auth::login(User::find($stranger->user_id));

        $request = new Request([
            'return_request_id' => $returnRequest->id,
            'status' => ReturnRequest::STATUS_REJECTED,
            'order_item_id' => $returnRequest->order_item_id,
        ]);

        $response = app(ReturnRequestController::class)->update($request);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $returnRequest->refresh();
        $this->assertSame(ReturnRequest::STATUS_PENDING, $returnRequest->status);
    }

    public function test_the_owning_seller_can_transition_their_own_return_request(): void
    {
        // SettingService::getSettings() caches its result in a method-static array that persists for the
        // whole PHPUnit process, not per-test - whichever test in the suite calls it first "wins" for every
        // test after it, regardless of what any later test writes to the `settings` table. Mocking the
        // service sidesteps that entirely instead of racing it: OrderService::fetchOrders()'s per-item
        // return-eligibility check (reached via ReturnRequestService::applyTransition() ->
        // OrderService::update_order_item()) needs `max_days_to_return_item` present, which is unrelated to
        // Phase 3 itself - just data every real deployment already has.
        $this->mock(SettingService::class, function ($mock) {
            $mock->shouldReceive('getSettings')
                ->with('system_settings', true)
                ->andReturn(json_encode(['app_name' => 'Test Store', 'max_days_to_return_item' => 7]));
        });

        // The notification pipeline this test doesn't otherwise touch tries to fetch a real Firebase
        // access token unconditionally, even with zero registered devices to notify - unrelated to what
        // this test verifies (the ownership guard + transition), so it's faked out here.
        $this->mock(FirebaseNotificationService::class, function ($mock) {
            $mock->shouldReceive('sendNotification')->andReturn(null);
        });

        $owner = $this->makeSeller();
        $returnRequest = $this->makeReturnRequestFor($owner);

        Auth::login(User::find($owner->user_id));

        $request = new Request([
            'return_request_id' => $returnRequest->id,
            'status' => ReturnRequest::STATUS_REJECTED,
            'order_item_id' => $returnRequest->order_item_id,
        ]);

        $response = app(ReturnRequestController::class)->update($request);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $returnRequest->refresh();
        $this->assertSame(ReturnRequest::STATUS_REJECTED, $returnRequest->status);
    }
}
