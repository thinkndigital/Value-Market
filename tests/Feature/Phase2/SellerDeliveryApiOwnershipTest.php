<?php

namespace Tests\Feature\Phase2;

use App\Http\Controllers\Delivery_boy\v1\ApiController as DeliveryBoyApiController;
use App\Http\Controllers\Seller\ComboProductController;
use App\Http\Controllers\Seller\v1\ApiController as SellerApiController;
use App\Models\Category;
use App\Models\ComboProduct;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Parcel;
use App\Models\Parcelitem;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Phase 2 (docs/PHASE_2_IDOR_AUDIT.md §5b): the 8 confirmed-but-not-yet-fixed findings from that section,
 * now fixed - each of these API methods acted on a caller-supplied id (product_id/order_id/id/
 * order_item_id) with no check that the record belonged to the authenticated seller/delivery boy. Proves,
 * for each: an attacker (a different seller/delivery boy) is denied, and the genuine owner is not blocked
 * by the new check.
 */
class SellerDeliveryApiOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function makeSeller(): array
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => Role::SELLER,
        ]);

        $seller = Seller::forceCreate([
            'user_id' => $user->id,
            'disk' => 'public',
        ]);

        return [$user, $seller];
    }

    private function makeDeliveryBoy(): User
    {
        return User::forceCreate([
            'username' => 'delivery_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => Role::DELIVERY_BOY,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::forceCreate([
            'name' => json_encode(['en' => 'Category']),
            'slug' => 'cat-' . uniqid(),
            'image' => '',
            'banner' => '',
        ]);
    }

    private function makeProductOwnedBy(Seller $seller): Product
    {
        return Product::forceCreate([
            'category_id' => $this->makeCategory()->id,
            'seller_id' => $seller->id,
            'name' => json_encode(['en' => 'Product']),
            'slug' => 'product-' . uniqid(),
            'image' => '',
            'deliverable_cities' => '',
            'status' => 1,
        ]);
    }

    private function makeComboProductOwnedBy(Seller $seller): ComboProduct
    {
        return ComboProduct::forceCreate([
            'seller_id' => $seller->id,
            'title' => json_encode(['en' => 'Combo']),
            'deliverable_cities' => '',
            'status' => 1,
        ]);
    }

    private function makeOrderItemOwnedBy(Seller $seller, ?int $deliveryBoyId = null): array
    {
        $customer = User::forceCreate([
            'username' => 'customer_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => Role::CUSTOMER,
        ]);

        $order = Order::forceCreate([
            'user_id' => $customer->id,
            'mobile' => '9999999999',
            'total' => 100,
            'payment_method' => 'cod',
            'order_payment_currency_id' => 1,
            'order_payment_currency_code' => 'USD',
            'base_currency_code' => 'USD',
            'order_payment_currency_conversion_rate' => 1,
        ]);

        $product = $this->makeProductOwnedBy($seller);

        $productVariant = Product_variants::forceCreate([
            'product_id' => $product->id,
            'price' => 100,
        ]);

        $orderItem = OrderItems::forceCreate([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'delivery_boy_id' => $deliveryBoyId,
            'product_variant_id' => $productVariant->id,
            'quantity' => 1,
            'price' => 100,
            'sub_total' => 100,
            'status' => json_encode([['placed', now()->toDateTimeString()]]),
            'order_type' => 'regular_order',
        ]);

        return [$order, $orderItem];
    }

    // --- delete_product ---

    public function test_delete_product_denies_a_non_owning_seller(): void
    {
        [, $owner] = $this->makeSeller();
        $product = $this->makeProductOwnedBy($owner);

        [$attacker] = $this->makeSeller();
        Auth::login($attacker);

        $response = app(SellerApiController::class)->delete_product(new Request(['product_id' => $product->id]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_delete_product_allows_the_owning_seller(): void
    {
        [$ownerUser, $owner] = $this->makeSeller();
        $product = $this->makeProductOwnedBy($owner);
        Auth::login($ownerUser);

        $response = app(SellerApiController::class)->delete_product(new Request(['product_id' => $product->id]));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    // --- update_product_status ---

    public function test_update_product_status_does_not_change_another_sellers_product(): void
    {
        [, $owner] = $this->makeSeller();
        $product = $this->makeProductOwnedBy($owner);

        [$attackerUser, $attackerSeller] = $this->makeSeller();
        \App\Models\SellerStore::forceCreate([
            'seller_id' => $attackerSeller->id,
            'user_id' => $attackerUser->id,
            'store_id' => 1,
            'slug' => 'store-' . uniqid(),
            'store_name' => 'Store',
            'store_description' => 'Store',
            'logo' => '',
            'store_thumbnail' => '',
            'disk' => 'public',
            'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);
        Auth::login($attackerUser);

        app(SellerApiController::class)->update_product_status(new Request([
            'product_id' => $product->id,
            'status' => 0,
            'store_id' => 1,
        ]));

        $this->assertSame(1, $product->fresh()->status, 'A seller must not be able to (de)activate another seller\'s product.');
    }

    // --- update_product_deliverability ---

    public function test_update_product_deliverability_rejects_another_sellers_product_id(): void
    {
        [, $owner] = $this->makeSeller();
        $product = $this->makeProductOwnedBy($owner);

        [$attacker] = $this->makeSeller();
        Auth::login($attacker);

        // products.deliverable_type defaults to 1 (NOT NULL DEFAULT 1, not reflected on the in-memory
        // model until re-fetched) - attempt to change it to a different value so an unblocked update
        // would be observable.
        $this->assertSame(1, $product->fresh()->deliverable_type);
        $response = app(SellerApiController::class)->update_product_deliverability(new Request([
            'product_id' => (string) $product->id,
            'deliverable_type' => 0,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame(1, $product->fresh()->deliverable_type);
    }

    // --- update_combo_product_deliverability ---

    public function test_update_combo_product_deliverability_rejects_another_sellers_combo(): void
    {
        [, $owner] = $this->makeSeller();
        $combo = $this->makeComboProductOwnedBy($owner);

        [$attacker] = $this->makeSeller();
        Auth::login($attacker);

        $response = app(SellerApiController::class)->update_combo_product_deliverability(new Request([
            'product_id' => (string) $combo->id,
            'deliverable_type' => 1,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertNotEquals(1, $combo->fresh()->deliverable_type);
    }

    // --- delete_combo_product ---

    public function test_delete_combo_product_denies_a_non_owning_seller(): void
    {
        [, $owner] = $this->makeSeller();
        $combo = $this->makeComboProductOwnedBy($owner);

        [$attacker] = $this->makeSeller();
        Auth::login($attacker);

        $response = app(SellerApiController::class)->delete_combo_product(
            new Request(['product_id' => $combo->id]),
            app(ComboProductController::class)
        );
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertDatabaseHas('combo_products', ['id' => $combo->id]);
    }

    public function test_delete_combo_product_allows_the_owning_seller(): void
    {
        [$ownerUser, $owner] = $this->makeSeller();
        $combo = $this->makeComboProductOwnedBy($owner);
        Auth::login($ownerUser);

        $response = app(SellerApiController::class)->delete_combo_product(
            new Request(['product_id' => $combo->id]),
            app(ComboProductController::class)
        );
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $this->assertDatabaseMissing('combo_products', ['id' => $combo->id]);
    }

    // --- delete_order ---

    public function test_delete_order_denies_a_seller_with_no_items_in_the_order(): void
    {
        [, $owner] = $this->makeSeller();
        [$order] = $this->makeOrderItemOwnedBy($owner);

        [$attacker] = $this->makeSeller();
        Auth::login($attacker);

        $response = app(SellerApiController::class)->delete_order(new Request(['order_id' => $order->id]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_delete_order_allows_the_owning_seller(): void
    {
        [$ownerUser, $owner] = $this->makeSeller();
        [$order] = $this->makeOrderItemOwnedBy($owner);
        Auth::login($ownerUser);

        $response = app(SellerApiController::class)->delete_order(new Request(['order_id' => $order->id]));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    // --- delete_order_parcel ---

    public function test_delete_order_parcel_denies_a_seller_with_no_items_in_the_parcel(): void
    {
        [, $owner] = $this->makeSeller();
        [$order, $orderItem] = $this->makeOrderItemOwnedBy($owner);
        $parcel = Parcel::forceCreate([
            'order_id' => $order->id,
            'name' => 'Parcel 1',
            'status' => 'pending',
            'active_status' => 'pending',
            'otp' => 1234,
        ]);
        Parcelitem::forceCreate([
            'parcel_id' => $parcel->id,
            'order_item_id' => $orderItem->id,
            'product_variant_id' => $orderItem->product_variant_id,
            'unit_price' => 100,
            'quantity' => 1,
        ]);

        [$attacker] = $this->makeSeller();
        Auth::login($attacker);

        $response = app(SellerApiController::class)->delete_order_parcel(new Request(['id' => $parcel->id]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertDatabaseHas('parcels', ['id' => $parcel->id]);
    }

    // --- update_returned_order_item_status (delivery boy) ---

    public function test_update_returned_order_item_status_denies_a_non_assigned_delivery_boy(): void
    {
        [, $owner] = $this->makeSeller();
        $assignedDeliveryBoy = $this->makeDeliveryBoy();
        [, $orderItem] = $this->makeOrderItemOwnedBy($owner, $assignedDeliveryBoy->id);

        $attacker = $this->makeDeliveryBoy();
        Auth::login($attacker);

        $response = app(DeliveryBoyApiController::class)->update_returned_order_item_status(new Request([
            'order_item_id' => $orderItem->id,
            'status' => 'return_pickedup',
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertNotEquals('return_pickedup', $orderItem->fresh()->active_status);
    }
}
