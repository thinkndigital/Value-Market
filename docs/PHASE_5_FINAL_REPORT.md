# Phase 5 Final Report — Inventory + Procurement

**Status: complete for the scope delivered — see §6 below for what's explicitly deferred.**
`docs/PHASE_5_INVENTORY_PROCUREMENT.md` carries full design/implementation detail; this report is the index
and the numbers.

## Exact numbers

| Metric | Count |
|---|---|
| Commits this phase | 1 |
| New tables | 7 (`suppliers`, `purchase_orders`, `purchase_order_items`, `goods_received_notes`, `goods_received_note_items`, `stock_movements`, `stock_items`) |
| New models | 7 |
| New services | 2 (`InventoryService`, `PurchaseOrderService`) |
| Existing service extended (single integration point) | 1 (`ProductService::updateStock()` — 5 new optional params, all 15 existing call sites unchanged) |
| New controllers | 2 (`Seller\SupplierController`, `Seller\PurchaseOrderController`) |
| New routes | 7 |
| New Phase 5 test files | 4 |
| New Phase 5 tests | 18 |
| Existing test updated (not a regression — expected table-count shift) | 1 (`MigrationBaselineTest`, 95 → 102) |
| Total test suite (Phase 1–5) | 199 passing, 0 failing |
| Total test suite at Phase 5 start | 181 |

## What changed

- **Real inventory ledger**: `stock_movements` (immutable, every quantity change with direction + reason)
  and `stock_items` (running per-branch quantity, kept in sync at write time). Both write through one
  method, `InventoryService::recordMovement()`.
- **Single safe integration point found and used**: all 15 existing stock-changing call sites across the
  codebase already funnel through `ProductService::updateStock()` — extended with 5 optional trailing params
  rather than touched individually. Verified by test that the 15 sites' existing call shape still produces
  identical `product_variants`/`products.stock` results, with the new ledger write as an additive side
  effect, not a behavior change.
- **Procurement workflow**: `suppliers`, `purchase_orders`/items, `goods_received_notes`/items, with real
  partial/full receiving logic (`PurchaseOrderService::receiveGoods()`) that rejects over-receipt, tracks
  `received_quantity` per line, and derives PO status from actual remaining quantities rather than separate
  mutable state.
- **Branch transfers**: `InventoryService::transferStock()` — net-zero on total stock, moves only the
  per-branch split, verified to reject a branch belonging to a different seller.
- **Cost visibility**: `InventoryService::weightedAverageCost()` — a real, tested weighted-average
  calculation across recorded receipts, explicitly documented as not a perpetual/FIFO engine (see below).

## Documented, not built this phase (with reason)

| Finding | Why not built now | Doc |
|---|---|---|
| True perpetual/FIFO cost-layer valuation | `weightedAverageCost()` is a genuine first pass built on the new `stock_movements` ledger, which is now the right foundation for a full cost-layer engine as its own focused pass — not the same thing, and not silently conflated with it | `PHASE_5_INVENTORY_PROCUREMENT.md` §4, §6 |
| Seller-panel UI for suppliers/POs/GRNs | This phase delivers the backend (migrations/models/services/controllers/tests), matching Phase 3/4's pattern | `PHASE_5_INVENTORY_PROCUREMENT.md` §6 |
| Low-stock alerting / reorder points | Not in the roadmap's one-line scope; natural follow-up now that `stock_items` is real | `PHASE_5_INVENTORY_PROCUREMENT.md` §6 |
| A separate `warehouses` table | Deliberately not built — Phase 4's `branches` already answers "where is this stock"; a second near-identical table would be the exact redundant-parallel-concept `DATABASE_GAP_ANALYSIS.md` §6 warns against | `PHASE_5_INVENTORY_PROCUREMENT.md` §1 |

## Verification performed

- Migration run clean against the real MariaDB instance this repo's test suite uses.
- `php -l` clean on every touched/new PHP file, including the modified `ProductService::updateStock()`.
- `php artisan route:list` confirms all 7 new routes registered, no name collisions.
- Full suite run after the change: **199/199 passing**, zero regressions — critically, this includes every
  Phase 1–3 test that exercises `ProductService::updateStock()` indirectly (order placement, cancellation
  restocks, return restocks), none of which needed any change, proving the extension is truly additive.
- The riskiest logic (partial/full PO receiving, over-receipt rejection, stock dual-write staying in sync
  with the ledger, transfer net-zero-on-total) has direct test coverage, not just IDOR coverage — this phase
  touches money-adjacent inventory math, so correctness tests came before the controller layer, not after.
- IDOR coverage on both new controllers: a seller cannot create a PO against another seller's supplier,
  cannot receive goods against another seller's PO, and a transfer between branches owned by different
  sellers is rejected.

## What Phase 5 did not do (explicitly, scope boundaries)

Did not build a separate `warehouses` table (§1). Did not build a perpetual/FIFO valuation engine — only a
weighted-average calculation, explicitly labeled as such. Did not build any new UI screens. Did not touch
any of the 15 existing `ProductService::updateStock()` call sites — the extension is fully additive by
design.
