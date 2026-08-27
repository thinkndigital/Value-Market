<?php

namespace Tests\Feature\Phase5;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\Role;
use App\Models\Seller;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5 (docs/PHASE_5_INVENTORY_PROCUREMENT.md): InventoryService::recordMovement() is the single write
 * path for the stock_movements ledger + stock_items running total - every other Phase 5 method (and
 * ProductService::updateStock()'s dual-write) goes through it.
 */
class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeSellerWithVariant(): array
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
            'name' => json_encode(['en' => 'Inv Product']), 'slug' => 'inv-product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '2', 'status' => 1,
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 10, 'status' => 1, 'stock' => 0]);

        return [$seller, $variant];
    }

    public function test_record_movement_creates_a_ledger_row_and_a_new_stock_item(): void
    {
        [$seller, $variant] = $this->makeSellerWithVariant();

        app(InventoryService::class)->recordMovement($seller->id, null, $variant->id, StockMovement::TYPE_IN, 10, StockMovement::REFERENCE_MANUAL_ADJUSTMENT);

        $this->assertSame(1, StockMovement::where('product_variant_id', $variant->id)->count());
        $stockItem = StockItem::where('product_variant_id', $variant->id)->whereNull('branch_id')->first();
        $this->assertNotNull($stockItem);
        $this->assertSame(10, $stockItem->quantity);
    }

    public function test_record_movement_out_decrements_the_stock_item_and_never_goes_negative(): void
    {
        [$seller, $variant] = $this->makeSellerWithVariant();
        $inventory = app(InventoryService::class);

        $inventory->recordMovement($seller->id, null, $variant->id, StockMovement::TYPE_IN, 5, StockMovement::REFERENCE_MANUAL_ADJUSTMENT);
        $inventory->recordMovement($seller->id, null, $variant->id, StockMovement::TYPE_OUT, 20, StockMovement::REFERENCE_MANUAL_ADJUSTMENT);

        $stockItem = StockItem::where('product_variant_id', $variant->id)->whereNull('branch_id')->first();
        $this->assertSame(0, $stockItem->quantity);
    }

    public function test_transfer_stock_moves_quantity_between_branches_with_no_net_change(): void
    {
        [$seller, $variant] = $this->makeSellerWithVariant();
        $branchA = Branch::forceCreate(['seller_id' => $seller->id, 'name' => 'Branch A', 'status' => Branch::STATUS_ACTIVE]);
        $branchB = Branch::forceCreate(['seller_id' => $seller->id, 'name' => 'Branch B', 'status' => Branch::STATUS_ACTIVE]);

        $inventory = app(InventoryService::class);
        $inventory->recordMovement($seller->id, $branchA->id, $variant->id, StockMovement::TYPE_IN, 15, StockMovement::REFERENCE_MANUAL_ADJUSTMENT);

        $inventory->transferStock($seller->id, $variant->id, $branchA->id, $branchB->id, 6);

        $qtyA = StockItem::where('product_variant_id', $variant->id)->where('branch_id', $branchA->id)->value('quantity');
        $qtyB = StockItem::where('product_variant_id', $variant->id)->where('branch_id', $branchB->id)->value('quantity');
        $this->assertSame(9, $qtyA);
        $this->assertSame(6, $qtyB);
        $this->assertSame(9 + 6, $qtyA + $qtyB);
    }

    public function test_transfer_stock_rejects_a_branch_that_belongs_to_another_seller(): void
    {
        [$seller, $variant] = $this->makeSellerWithVariant();
        [$otherSeller] = $this->makeSellerWithVariant();
        $ownBranch = Branch::forceCreate(['seller_id' => $seller->id, 'name' => 'Own Branch', 'status' => Branch::STATUS_ACTIVE]);
        $strangerBranch = Branch::forceCreate(['seller_id' => $otherSeller->id, 'name' => 'Stranger Branch', 'status' => Branch::STATUS_ACTIVE]);

        $this->expectException(\InvalidArgumentException::class);
        app(InventoryService::class)->transferStock($seller->id, $variant->id, $ownBranch->id, $strangerBranch->id, 5);
    }

    public function test_weighted_average_cost_is_null_with_no_receipts(): void
    {
        [, $variant] = $this->makeSellerWithVariant();

        $this->assertNull(app(InventoryService::class)->weightedAverageCost($variant->id));
    }

    public function test_weighted_average_cost_across_two_receipts(): void
    {
        [$seller, $variant] = $this->makeSellerWithVariant();
        $inventory = app(InventoryService::class);

        // 10 units @ 5.00, then 10 units @ 7.00 -> weighted average 6.00
        $inventory->recordMovement($seller->id, null, $variant->id, StockMovement::TYPE_IN, 10, StockMovement::REFERENCE_GOODS_RECEIVED_NOTE, null, 5.00);
        $inventory->recordMovement($seller->id, null, $variant->id, StockMovement::TYPE_IN, 10, StockMovement::REFERENCE_GOODS_RECEIVED_NOTE, null, 7.00);

        $this->assertSame(6.0, app(InventoryService::class)->weightedAverageCost($variant->id));
    }
}
