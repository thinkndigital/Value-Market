<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Phase 5 (docs/PHASE_5_INVENTORY_PROCUREMENT.md): the single place that writes to the new stock_movements
 * ledger and its stock_items running-total materialization. Two callers feed it: ProductService::updateStock()
 * (dual-write for the 15 existing legacy call sites - see that method's docblock) and this phase's own
 * PurchaseOrderService::receiveGoods()/transferStock()/adjustStock() below. Every write goes through
 * recordMovement() so there is exactly one place the stock_items running total can drift from the ledger.
 */
class InventoryService
{
    /** Current on-hand quantity for one variant at one branch (0 if never received into that branch). */
    public function branchStockQuantity(int $sellerId, int $branchId, int $productVariantId): int
    {
        return (int) (StockItem::where('seller_id', $sellerId)
            ->where('branch_id', $branchId)
            ->where('product_variant_id', $productVariantId)
            ->value('quantity') ?? 0);
    }

    /**
     * Phase 9/10 (32-phase SaaS brief - docs/PHASE_9_10_POS_CONCURRENCY_AND_BRANCHES.md): before this, a
     * POS sale's stock check (validateStock(), the app/function_helper.php global) only ever compared
     * against the seller's total global stock (products.stock/product_variants.stock) - never against what
     * this specific branch actually has on hand in stock_items, even though stock_items has tracked a
     * real per-branch running total since Phase 5. A seller with Branch A holding 6 units and Branch B
     * holding 4 could sell 8 units from Branch A's POS terminal and it would succeed (global stock = 10),
     * silently overselling a location that never had that much inventory.
     *
     * $branchId === null (no branch resolved for this sale - the seller isn't using branches, or POS
     * didn't verify one) skips this check entirely, same as before this fix - preserves today's behavior
     * for every seller not using the branch feature. Combo products aren't branch-tracked (updateComboStock()
     * takes no branch parameter either - matches that existing scope), so only regular items are checked.
     *
     * @return array{error: bool, message: string}
     */
    public function validateBranchStock(int $sellerId, ?int $branchId, array $productVariantIds, array $quantities, array $productTypes): array
    {
        if ($branchId === null) {
            return ['error' => false, 'message' => ''];
        }

        foreach ($productVariantIds as $index => $variantId) {
            if (($productTypes[$index] ?? 'regular') !== 'regular') {
                continue;
            }

            $onHand = $this->branchStockQuantity($sellerId, $branchId, (int) $variantId);
            if ($onHand < (int) $quantities[$index]) {
                return [
                    'error' => true,
                    'message' => "This branch only has {$onHand} unit(s) of this item in stock.",
                ];
            }
        }

        return ['error' => false, 'message' => ''];
    }

    /**
     * Writes one immutable ledger row and updates (or creates) the matching stock_items running total.
     * Does NOT touch product_variants/products.stock - callers that need the legacy field kept in sync
     * (ProductService::updateStock()) do that themselves alongside calling this.
     */
    public function recordMovement(
        int $sellerId,
        ?int $branchId,
        int $productVariantId,
        string $direction,
        int $quantity,
        string $referenceType,
        ?int $referenceId = null,
        ?float $unitCost = null,
        ?string $notes = null
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Stock movement quantity must be positive.');
        }
        if (!in_array($direction, [StockMovement::TYPE_IN, StockMovement::TYPE_OUT], true)) {
            throw new \InvalidArgumentException('Stock movement direction must be "in" or "out".');
        }

        return DB::transaction(function () use ($sellerId, $branchId, $productVariantId, $direction, $quantity, $referenceType, $referenceId, $unitCost, $notes) {
            $movement = StockMovement::forceCreate([
                'seller_id' => $sellerId,
                'branch_id' => $branchId,
                'product_variant_id' => $productVariantId,
                'movement_type' => $direction,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'created_at' => now(),
            ]);

            $stockItem = StockItem::where('seller_id', $sellerId)
                ->where('product_variant_id', $productVariantId)
                ->when($branchId === null, fn ($q) => $q->whereNull('branch_id'), fn ($q) => $q->where('branch_id', $branchId))
                ->first();

            if (!$stockItem) {
                $stockItem = StockItem::forceCreate([
                    'seller_id' => $sellerId,
                    'branch_id' => $branchId,
                    'product_variant_id' => $productVariantId,
                    'quantity' => 0,
                ]);
            }

            $delta = $direction === StockMovement::TYPE_IN ? $quantity : -$quantity;
            $stockItem->quantity = max(0, (int) $stockItem->quantity + $delta);
            $stockItem->save();

            return $movement;
        });
    }

    /**
     * Moves stock between two branches of the SAME seller - net zero on total owned quantity, so this never
     * touches product_variants/products.stock (only stock_items' per-branch split changes).
     */
    public function transferStock(int $sellerId, int $productVariantId, int $fromBranchId, int $toBranchId, int $quantity, ?string $notes = null): void
    {
        if ($fromBranchId === $toBranchId) {
            throw new \InvalidArgumentException('Source and destination branch must differ.');
        }

        $ownsBoth = Branch::where('seller_id', $sellerId)->whereIn('id', [$fromBranchId, $toBranchId])->count() === 2;
        if (!$ownsBoth) {
            throw new \InvalidArgumentException('Both branches must belong to the transferring seller.');
        }

        DB::transaction(function () use ($sellerId, $productVariantId, $fromBranchId, $toBranchId, $quantity, $notes) {
            $referenceId = (int) StockMovement::max('id') + 1; // ties the pair together for audit purposes
            $this->recordMovement($sellerId, $fromBranchId, $productVariantId, StockMovement::TYPE_OUT, $quantity, StockMovement::REFERENCE_TRANSFER, $referenceId, null, $notes);
            $this->recordMovement($sellerId, $toBranchId, $productVariantId, StockMovement::TYPE_IN, $quantity, StockMovement::REFERENCE_TRANSFER, $referenceId, null, $notes);
        });
    }

    /**
     * A manual correction (e.g. a stocktake finding a discrepancy) that DOES change total owned quantity -
     * routes through ProductService::updateStock() so the ledger and the legacy field move together.
     */
    public function adjustStock(int $productVariantId, ?int $branchId, int $quantity, string $direction, ?string $notes = null): void
    {
        app(ProductService::class)->updateStock(
            $productVariantId,
            $quantity,
            $direction === StockMovement::TYPE_IN ? 'plus' : '',
            $branchId,
            StockMovement::REFERENCE_MANUAL_ADJUSTMENT,
            null,
            null,
            $notes
        );
    }

    /**
     * Simple weighted-average unit cost across every goods_received_note receipt recorded for this variant
     * (all branches, all time) - NOT a perpetual moving-average or FIFO cost-layer engine. Documented
     * explicitly as v1 in docs/PHASE_5_INVENTORY_PROCUREMENT.md §4; a true perpetual/FIFO valuation is
     * flagged there as a scoped follow-up, not silently implied by this method's name.
     */
    public function weightedAverageCost(int $productVariantId): ?float
    {
        $row = StockMovement::where('product_variant_id', $productVariantId)
            ->where('reference_type', StockMovement::REFERENCE_GOODS_RECEIVED_NOTE)
            ->whereNotNull('unit_cost')
            ->selectRaw('SUM(quantity * unit_cost) as total_cost, SUM(quantity) as total_qty')
            ->first();

        if (!$row || (int) $row->total_qty === 0) {
            return null;
        }

        return round((float) $row->total_cost / (int) $row->total_qty, 4);
    }
}
