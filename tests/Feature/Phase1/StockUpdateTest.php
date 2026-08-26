<?php

namespace Tests\Feature\Phase1;

use App\Models\Category;
use App\Models\Product;
use App\Models\Product_variants;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 (Task 11 - "at minimum test... stock modification"): exercises the real
 * ProductService::updateStock() against the InnoDB-converted products/product_variants tables.
 */
class StockUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeSimpleProductWithVariant(int $stock): array
    {
        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']),
            'slug' => 'cat-' . uniqid(),
            'image' => '',
            'banner' => '',
        ]);

        $product = Product::forceCreate([
            'category_id' => $category->id,
            'name' => json_encode(['en' => 'Product']),
            'slug' => 'product-' . uniqid(),
            'image' => '',
            'deliverable_cities' => '',
            'stock_type' => '0', // product-level stock
            'stock' => $stock,
            'availability' => 1,
        ]);

        $variant = Product_variants::forceCreate([
            'product_id' => $product->id,
            'price' => 10,
        ]);

        return [$product, $variant];
    }

    public function test_selling_a_unit_decrements_product_stock(): void
    {
        [$product, $variant] = $this->makeSimpleProductWithVariant(10);

        app(ProductService::class)->updateStock($variant->id, 3, '');

        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_stock_reaching_zero_marks_product_unavailable(): void
    {
        [$product, $variant] = $this->makeSimpleProductWithVariant(3);

        app(ProductService::class)->updateStock($variant->id, 3, '');

        $fresh = $product->fresh();
        $this->assertSame(0, $fresh->stock);
        $this->assertEquals(0, $fresh->availability);
    }

    public function test_restocking_increments_product_stock_and_marks_available(): void
    {
        [$product, $variant] = $this->makeSimpleProductWithVariant(0);

        app(ProductService::class)->updateStock($variant->id, 5, 'plus');

        $fresh = $product->fresh();
        $this->assertSame(5, $fresh->stock);
        $this->assertEquals(1, $fresh->availability);
    }
}
