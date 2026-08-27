<?php

namespace Tests\Feature\Phase5;

use App\Models\Category;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\Role;
use App\Models\Seller;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5 (docs/PHASE_5_INVENTORY_PROCUREMENT.md §2): ProductService::updateStock() is the one method all
 * 15 existing stock-changing call sites funnel through - this proves the new dual-write into
 * stock_movements/stock_items happens automatically for a call made exactly the way those 15 sites already
 * call it (no new params), i.e. backward compatibility plus the new side effect, not a behavior change.
 */
class ProductServiceUpdateStockLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function makeVariantLevelProduct(int $stock = 5): array
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $user->id, 'disk' => 'public', 'status' => 1]);
        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']), 'slug' => 'cat-' . uniqid(), 'image' => '', 'banner' => '',
        ]);
        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id,
            'name' => json_encode(['en' => 'Ledger Product']), 'slug' => 'ledger-product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '2', 'status' => 1,
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 10, 'status' => 1, 'stock' => $stock]);

        return [$seller, $variant];
    }

    public function test_a_legacy_style_call_with_no_new_params_still_updates_stock_exactly_as_before(): void
    {
        [, $variant] = $this->makeVariantLevelProduct(stock: 5);

        app(ProductService::class)->updateStock($variant->id, 3, 'plus');

        $this->assertSame(8, $variant->fresh()->stock);
    }

    public function test_a_legacy_style_call_also_writes_a_generic_ledger_entry(): void
    {
        [$seller, $variant] = $this->makeVariantLevelProduct(stock: 5);

        app(ProductService::class)->updateStock($variant->id, 3, 'plus');

        $movement = StockMovement::where('product_variant_id', $variant->id)->first();
        $this->assertNotNull($movement);
        $this->assertSame($seller->id, $movement->seller_id);
        $this->assertNull($movement->branch_id);
        $this->assertSame(StockMovement::TYPE_IN, $movement->movement_type);
        $this->assertSame(3, $movement->quantity);
        $this->assertSame(StockMovement::REFERENCE_LEGACY_ADJUSTMENT, $movement->reference_type);

        $stockItem = StockItem::where('product_variant_id', $variant->id)->whereNull('branch_id')->first();
        $this->assertSame(3, $stockItem->quantity);
    }

    public function test_a_deduction_call_writes_an_out_movement(): void
    {
        [, $variant] = $this->makeVariantLevelProduct(stock: 5);

        app(ProductService::class)->updateStock($variant->id, 2, ''); // no 'plus' - deduction, matches order-placement call sites

        $this->assertSame(3, $variant->fresh()->stock);
        $movement = StockMovement::where('product_variant_id', $variant->id)->first();
        $this->assertSame(StockMovement::TYPE_OUT, $movement->movement_type);
        $this->assertSame(2, $movement->quantity);
    }

    public function test_a_new_style_call_with_explicit_branch_and_reference_is_recorded_accordingly(): void
    {
        [$seller, $variant] = $this->makeVariantLevelProduct(stock: 0);
        $branch = \App\Models\Branch::forceCreate(['seller_id' => $seller->id, 'name' => 'Warehouse', 'status' => \App\Models\Branch::STATUS_ACTIVE]);

        app(ProductService::class)->updateStock($variant->id, 10, 'plus', $branch->id, StockMovement::REFERENCE_GOODS_RECEIVED_NOTE, 42, 5.50);

        $movement = StockMovement::where('product_variant_id', $variant->id)->first();
        $this->assertSame($branch->id, $movement->branch_id);
        $this->assertSame(StockMovement::REFERENCE_GOODS_RECEIVED_NOTE, $movement->reference_type);
        $this->assertSame(42, $movement->reference_id);
        $this->assertSame(5.5, (float) $movement->unit_cost);
    }
}
