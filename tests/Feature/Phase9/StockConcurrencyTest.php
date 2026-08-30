<?php

namespace Tests\Feature\Phase9;

use App\Models\Category;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 32-phase SaaS brief, Phase 9/10 (docs/PHASE_9_10_POS_CONCURRENCY_AND_BRANCHES.md):
 * ProductService::updateStock() used to read stock with a plain, unlocked SELECT, then write a
 * separately-computed value - a real race for two concurrent sales of the same variant (a storefront
 * checkout and a POS sale hitting the last unit at once). It also only ever guarded a decrement with
 * "is stock positive" (`> 0`), not "is stock enough for this quantity" - a qty=5 request against stock=2
 * used to set stock to -3.
 *
 * True multi-connection concurrency isn't simulated here (this app's existing analogous coverage -
 * tests/Feature/Phase1/WalletServiceTest.php for WalletService's own DB::transaction()+lockForUpdate()
 * fix - takes the same approach: prove the corrected read-modify-write behavior, not spin up parallel
 * connections). What's proven directly: the insufficient-stock guard fix (the concrete, previously-broken,
 * now-fixed behavior) across all three stock_type shapes, and that the locked read still returns the
 * correct up-to-date values.
 */
class StockConcurrencyTest extends TestCase
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

    private function makeCategory(): Category
    {
        return Category::forceCreate([
            'name' => json_encode(['en' => 'Category']), 'slug' => 'cat-' . uniqid(), 'image' => '', 'banner' => '',
        ]);
    }

    public function test_product_level_stock_type_0_decrement_is_skipped_not_negative_when_qty_exceeds_stock(): void
    {
        $seller = $this->makeSeller();
        $product = Product::forceCreate([
            'category_id' => $this->makeCategory()->id, 'seller_id' => $seller->id,
            'name' => json_encode(['en' => 'Product A']), 'slug' => 'product-a-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '0', 'stock' => 2, 'availability' => 1, 'status' => 1,
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 10, 'status' => 1]);

        app(ProductService::class)->updateStock($variant->id, 5, '');

        // Previously: 2 - 5 = -3, silently written. Now: the guard skips a decrement it can't satisfy.
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_product_level_stock_type_0_decrement_of_exactly_available_stock_succeeds(): void
    {
        $seller = $this->makeSeller();
        $product = Product::forceCreate([
            'category_id' => $this->makeCategory()->id, 'seller_id' => $seller->id,
            'name' => json_encode(['en' => 'Product B']), 'slug' => 'product-b-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '0', 'stock' => 3, 'availability' => 1, 'status' => 1,
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 10, 'status' => 1]);

        app(ProductService::class)->updateStock($variant->id, 3, '');

        $fresh = $product->fresh();
        $this->assertSame(0, $fresh->stock);
        $this->assertSame('0', (string) $fresh->availability);
    }

    public function test_synced_stock_type_1_decrement_is_skipped_not_negative_when_qty_exceeds_stock(): void
    {
        $seller = $this->makeSeller();
        $product = Product::forceCreate([
            'category_id' => $this->makeCategory()->id, 'seller_id' => $seller->id,
            'name' => json_encode(['en' => 'Product C']), 'slug' => 'product-c-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '1', 'stock' => 4, 'availability' => 1, 'status' => 1,
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 10, 'status' => 1, 'stock' => 4]);

        app(ProductService::class)->updateStock($variant->id, 10, '');

        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(4, $variant->fresh()->stock);
    }

    public function test_variant_level_stock_type_2_decrement_is_skipped_not_negative_when_qty_exceeds_stock(): void
    {
        $seller = $this->makeSeller();
        $product = Product::forceCreate([
            'category_id' => $this->makeCategory()->id, 'seller_id' => $seller->id,
            'name' => json_encode(['en' => 'Product D']), 'slug' => 'product-d-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '2', 'status' => 1,
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 10, 'status' => 1, 'stock' => 1]);

        app(ProductService::class)->updateStock($variant->id, 2, '');

        $this->assertSame(1, $variant->fresh()->stock, 'A qty greater than stock must not drive it negative.');
    }

    public function test_variant_level_stock_type_2_decrement_of_exactly_available_stock_succeeds_and_flips_availability(): void
    {
        $seller = $this->makeSeller();
        $product = Product::forceCreate([
            'category_id' => $this->makeCategory()->id, 'seller_id' => $seller->id,
            'name' => json_encode(['en' => 'Product E']), 'slug' => 'product-e-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '2', 'status' => 1,
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 10, 'status' => 1, 'stock' => 1, 'availability' => 1]);

        app(ProductService::class)->updateStock($variant->id, 1, '');

        $fresh = $variant->fresh();
        $this->assertSame(0, $fresh->stock);
        $this->assertSame('0', (string) $fresh->availability);
    }

    public function test_two_sequential_decrements_that_together_exceed_stock_only_deplete_what_actually_exists(): void
    {
        // Simulates what the lockForUpdate() fix guarantees under real concurrency: each call sees the
        // true, just-committed value - not a value read before an earlier concurrent decrement landed.
        $seller = $this->makeSeller();
        $product = Product::forceCreate([
            'category_id' => $this->makeCategory()->id, 'seller_id' => $seller->id,
            'name' => json_encode(['en' => 'Product F']), 'slug' => 'product-f-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '2', 'status' => 1,
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 10, 'status' => 1, 'stock' => 3]);

        app(ProductService::class)->updateStock($variant->id, 2, ''); // "sale A": 3 -> 1
        app(ProductService::class)->updateStock($variant->id, 2, ''); // "sale B" (the last-unit race case): should be skipped, not go to -1

        $this->assertSame(1, $variant->fresh()->stock);
    }
}
