<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\Seller;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ProductService::fetchProduct() backs the storefront's main product-listing/search endpoint
 * (App\v1\ApiController::get_products) - the highest-traffic customer-facing query in the app. It used to
 * eager-load a Product's full `orderItems` (every order line item ever placed for it, via
 * hasManyThrough(OrderItems::class, Product_variants::class)) even though nothing in this method ever reads
 * that relation - it's converted to a plain array and immediately unset() along with the other eager-loaded
 * relations before the response is built (so this was never a data leak, just a wasted query and a wasted
 * eager-loaded result set on every single product-listing request).
 */
class ProductListingQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_product_does_not_query_order_items(): void
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
        ]);
        $seller = Seller::forceCreate([
            'user_id' => $user->id,
            'disk' => 'public',
            'status' => 1,
        ]);
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
        OrderItems::forceCreate([
            'product_variant_id' => $variant->id,
            'seller_id' => $seller->id,
            'quantity' => 3,
            'price' => 100,
            'sub_total' => 300,
        ]);

        // The orderItems relation's hasManyThrough eager-load always fires as its own standalone query
        // starting with this exact prefix (confirmed by inspecting DB::listen() output against a real
        // seeded product: "select `order_items`.*, `product_variants`.`product_id` as `laravel_through_key`
        // from `order_items` inner join `product_variants` on ... where `product_variants`.`product_id`
        // in (...)"). A plain str_contains() for "order_items" would also match the total_sale/
        // calculated_price correlated subqueries fetchProduct's main query legitimately embeds inline (not
        // a separate query at all) - checking the prefix is what tells the two apart.
        $orderItemsEagerLoadQueried = false;
        DB::listen(function ($query) use (&$orderItemsEagerLoadQueried) {
            if (str_starts_with(trim($query->sql), 'select `order_items`.*')) {
                $orderItemsEagerLoadQueried = true;
            }
        });

        $result = app(ProductService::class)->fetchProduct(store_id: $product->store_id, id: $product->id);

        $this->assertNotEmpty($result['product']);
        $this->assertFalse(
            $orderItemsEagerLoadQueried,
            'fetchProduct() should not eager-load orderItems - it is never read before being unset() from the response.'
        );
    }
}
