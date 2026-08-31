<?php

namespace Tests\Feature\Phase5;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Seller;
use App\Models\StockMovement;
use App\Models\ProcurementVendor;
use App\Models\User;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeFixture(int $stock = 0): array
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $user->id, 'disk' => 'public', 'status' => 1]);
        $supplier = ProcurementVendor::forceCreate(['seller_id' => $seller->id, 'name' => 'Acme Supplier', 'status' => ProcurementVendor::STATUS_ACTIVE]);
        $branch = Branch::forceCreate(['seller_id' => $seller->id, 'name' => 'Main Branch', 'status' => Branch::STATUS_ACTIVE]);

        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']), 'slug' => 'cat-' . uniqid(), 'image' => '', 'banner' => '',
        ]);
        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id,
            'name' => json_encode(['en' => 'PO Product']), 'slug' => 'po-product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '2', 'status' => 1,
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 20, 'status' => 1, 'stock' => $stock]);

        return [$seller, $supplier, $branch, $variant];
    }

    public function test_create_makes_a_purchase_order_with_items_in_ordered_status(): void
    {
        [$seller, $supplier, $branch, $variant] = $this->makeFixture();

        $po = app(PurchaseOrderService::class)->create($seller->id, $supplier->id, $branch->id, [
            ['product_variant_id' => $variant->id, 'quantity' => 50, 'unit_cost' => 4.25],
        ]);

        $this->assertSame(PurchaseOrder::STATUS_ORDERED, $po->status);
        $this->assertSame(1, $po->items()->count());
        $this->assertSame(50, $po->items()->first()->quantity);
    }

    public function test_receive_goods_fully_updates_stock_and_marks_the_po_received(): void
    {
        [$seller, $supplier, $branch, $variant] = $this->makeFixture(stock: 0);
        $po = app(PurchaseOrderService::class)->create($seller->id, $supplier->id, $branch->id, [
            ['product_variant_id' => $variant->id, 'quantity' => 30, 'unit_cost' => 4.25],
        ]);
        $poItem = $po->items()->first();

        $grn = app(PurchaseOrderService::class)->receiveGoods($po, $branch->id, [
            ['purchase_order_item_id' => $poItem->id, 'quantity_received' => 30],
        ]);

        $this->assertSame(30, $variant->fresh()->stock);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->fresh()->status);
        $this->assertSame(30, $poItem->fresh()->received_quantity);

        $movement = StockMovement::where('reference_type', StockMovement::REFERENCE_GOODS_RECEIVED_NOTE)
            ->where('reference_id', $grn->id)->first();
        $this->assertNotNull($movement);
        $this->assertSame(4.25, (float) $movement->unit_cost);
        $this->assertSame($branch->id, $movement->branch_id);
    }

    public function test_receive_goods_partially_marks_the_po_partially_received(): void
    {
        [$seller, $supplier, $branch, $variant] = $this->makeFixture(stock: 0);
        $po = app(PurchaseOrderService::class)->create($seller->id, $supplier->id, $branch->id, [
            ['product_variant_id' => $variant->id, 'quantity' => 30, 'unit_cost' => 4.25],
        ]);
        $poItem = $po->items()->first();

        app(PurchaseOrderService::class)->receiveGoods($po, $branch->id, [
            ['purchase_order_item_id' => $poItem->id, 'quantity_received' => 10],
        ]);

        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $po->fresh()->status);
        $this->assertSame(20, $poItem->fresh()->remainingQuantity());
    }

    public function test_receive_goods_rejects_receiving_more_than_what_remains_on_the_order(): void
    {
        [$seller, $supplier, $branch, $variant] = $this->makeFixture(stock: 0);
        $po = app(PurchaseOrderService::class)->create($seller->id, $supplier->id, $branch->id, [
            ['product_variant_id' => $variant->id, 'quantity' => 10, 'unit_cost' => 4.25],
        ]);
        $poItem = $po->items()->first();

        $this->expectException(\InvalidArgumentException::class);
        app(PurchaseOrderService::class)->receiveGoods($po, $branch->id, [
            ['purchase_order_item_id' => $poItem->id, 'quantity_received' => 15],
        ]);
    }

    public function test_two_partial_receipts_add_up_and_finish_the_order(): void
    {
        [$seller, $supplier, $branch, $variant] = $this->makeFixture(stock: 0);
        $po = app(PurchaseOrderService::class)->create($seller->id, $supplier->id, $branch->id, [
            ['product_variant_id' => $variant->id, 'quantity' => 10, 'unit_cost' => 4.25],
        ]);
        $poItem = $po->items()->first();

        app(PurchaseOrderService::class)->receiveGoods($po, $branch->id, [
            ['purchase_order_item_id' => $poItem->id, 'quantity_received' => 4],
        ]);
        app(PurchaseOrderService::class)->receiveGoods($po, $branch->id, [
            ['purchase_order_item_id' => $poItem->id, 'quantity_received' => 6],
        ]);

        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->fresh()->status);
    }
}
