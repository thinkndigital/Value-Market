<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Role;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Direct unit coverage for CartService::addToCart()'s multi-item fix (see the docblock on that method, and
 * tests/Feature/Phase1/PosSaleTest.php for the end-to-end Seller\PosController scenario this same fix
 * unblocks). Two callers rely on genuinely different store_id shapes and both must keep working:
 * CartController's storefront/API add-to-cart supplies one store_id per cart item (a real multi-vendor
 * cart), while Seller\PosController supplies a single store_id shared by the whole sale (every POS item
 * belongs to the cashier's own store). The fix must not blur those together.
 */
class CartServiceMultiItemTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::forceCreate([
            'username' => 'cart_user_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
    }

    public function test_a_multi_item_batch_with_one_store_id_per_item_keeps_each_items_own_store(): void
    {
        $user = $this->makeUser();

        $result = app(CartService::class)->addToCart([
            'user_id' => $user->id,
            'product_variant_id' => '101,102',
            'qty' => '1,1',
            'store_id' => '5,9', // genuinely different stores - the storefront multi-vendor cart shape
            'product_type' => 'regular,regular',
        ], false);

        $this->assertTrue($result);
        $this->assertSame(5, (int) Cart::where('product_variant_id', 101)->value('store_id'));
        $this->assertSame(9, (int) Cart::where('product_variant_id', 102)->value('store_id'));
    }

    public function test_a_multi_item_batch_with_a_single_shared_store_id_applies_it_to_every_item(): void
    {
        $user = $this->makeUser();

        // The Seller\PosController shape: one store_id for the whole sale, no matter how many line items -
        // this used to crash with "Undefined array key 1" past the first item.
        $result = app(CartService::class)->addToCart([
            'user_id' => $user->id,
            'product_variant_id' => '201,202,203',
            'qty' => '1,1,1',
            'store_id' => '7',
            'product_type' => 'regular,regular,regular',
        ], false);

        $this->assertTrue($result);
        $this->assertSame(3, Cart::whereIn('product_variant_id', [201, 202, 203])->count());
        foreach ([201, 202, 203] as $variantId) {
            $this->assertSame(7, (int) Cart::where('product_variant_id', $variantId)->value('store_id'));
        }
    }

    public function test_updating_an_existing_item_still_processes_the_rest_of_the_batch(): void
    {
        $user = $this->makeUser();
        Cart::forceCreate([
            'user_id' => $user->id, 'product_variant_id' => 301, 'qty' => 1,
            'is_saved_for_later' => 0, 'store_id' => 1,
        ]);

        // 301 already exists (update branch); 302 is new (create branch) - both must be processed in one
        // call, not just the first one the loop happens to touch.
        $result = app(CartService::class)->addToCart([
            'user_id' => $user->id,
            'product_variant_id' => '301,302',
            'qty' => '3,1',
            'store_id' => '1,1',
            'product_type' => 'regular,regular',
        ], false);

        $this->assertTrue($result);
        $this->assertSame(3, (int) Cart::where('product_variant_id', 301)->value('qty'), 'The existing row must be updated.');
        $this->assertSame(1, Cart::where('product_variant_id', 302)->count(), 'The new row must also be created, not dropped.');
    }
}
