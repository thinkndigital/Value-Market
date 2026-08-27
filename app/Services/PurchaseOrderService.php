<?php

namespace App\Services;

use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Phase 5 (docs/PHASE_5_INVENTORY_PROCUREMENT.md): purchase order lifecycle. receiveGoods() is the one path
 * that turns a PO into actual stock - it writes the GRN/items, then calls
 * ProductService::updateStock(..., 'plus', $branchId, 'goods_received_note', $grnId, $unitCost) per line so
 * the legacy stock field AND the new ledger move together in one call, rather than this service writing to
 * the ledger directly and risking the two drifting apart.
 */
class PurchaseOrderService
{
    /**
     * @param array<int, array{product_variant_id:int, quantity:int, unit_cost:float}> $items
     */
    public function create(int $sellerId, int $supplierId, ?int $branchId, array $items, array $meta = []): PurchaseOrder
    {
        if (empty($items)) {
            throw new \InvalidArgumentException('A purchase order needs at least one item.');
        }

        return DB::transaction(function () use ($sellerId, $supplierId, $branchId, $items, $meta) {
            $po = PurchaseOrder::forceCreate([
                'seller_id' => $sellerId,
                'supplier_id' => $supplierId,
                'branch_id' => $branchId,
                'status' => PurchaseOrder::STATUS_ORDERED,
                'order_date' => $meta['order_date'] ?? now()->toDateString(),
                'expected_date' => $meta['expected_date'] ?? null,
                'notes' => $meta['notes'] ?? null,
                'created_by' => $meta['created_by'] ?? null,
            ]);

            foreach ($items as $item) {
                PurchaseOrderItem::forceCreate([
                    'purchase_order_id' => $po->id,
                    'product_variant_id' => (int) $item['product_variant_id'],
                    'quantity' => (int) $item['quantity'],
                    'unit_cost' => (float) $item['unit_cost'],
                    'received_quantity' => 0,
                ]);
            }

            return $po;
        });
    }

    /**
     * @param array<int, array{purchase_order_item_id:int, quantity_received:int}> $receivedItems
     */
    public function receiveGoods(PurchaseOrder $po, ?int $branchId, array $receivedItems, ?int $receivedBy = null, ?string $notes = null): GoodsReceivedNote
    {
        if (empty($receivedItems)) {
            throw new \InvalidArgumentException('A goods received note needs at least one item.');
        }

        return DB::transaction(function () use ($po, $branchId, $receivedItems, $receivedBy, $notes) {
            $grn = GoodsReceivedNote::forceCreate([
                'purchase_order_id' => $po->id,
                'seller_id' => $po->seller_id,
                'branch_id' => $branchId,
                'received_date' => now()->toDateString(),
                'received_by' => $receivedBy,
                'notes' => $notes,
            ]);

            foreach ($receivedItems as $received) {
                $poItem = PurchaseOrderItem::where('id', $received['purchase_order_item_id'])
                    ->where('purchase_order_id', $po->id)
                    ->first();
                if (!$poItem) {
                    throw new \InvalidArgumentException('Purchase order item does not belong to this purchase order.');
                }

                $qty = (int) $received['quantity_received'];
                if ($qty <= 0) {
                    continue;
                }
                if ($poItem->remainingQuantity() < $qty) {
                    throw new \InvalidArgumentException("Cannot receive {$qty} units - only {$poItem->remainingQuantity()} remain on this purchase order item.");
                }

                GoodsReceivedNoteItem::forceCreate([
                    'goods_received_note_id' => $grn->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_variant_id' => $poItem->product_variant_id,
                    'quantity_received' => $qty,
                    'unit_cost' => $poItem->unit_cost,
                ]);

                $poItem->received_quantity = (int) $poItem->received_quantity + $qty;
                $poItem->save();

                app(ProductService::class)->updateStock(
                    $poItem->product_variant_id,
                    $qty,
                    'plus',
                    $branchId,
                    StockMovement::REFERENCE_GOODS_RECEIVED_NOTE,
                    $grn->id,
                    (float) $poItem->unit_cost
                );
            }

            $allItems = PurchaseOrderItem::where('purchase_order_id', $po->id)->get();
            $fullyReceived = $allItems->every(fn ($item) => $item->remainingQuantity() === 0);
            $po->status = $fullyReceived ? PurchaseOrder::STATUS_RECEIVED : PurchaseOrder::STATUS_PARTIALLY_RECEIVED;
            $po->save();

            return $grn;
        });
    }
}
