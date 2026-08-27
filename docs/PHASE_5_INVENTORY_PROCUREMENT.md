# Phase 5 — Inventory + Procurement

`docs/IMPLEMENTATION_ROADMAP.md` (Phase 5) scopes this as net-new: *"warehouses, stock movements,
transfers, valuation, suppliers, POs, GRNs."* `docs/DATABASE_GAP_ANALYSIS.md` §5 confirmed all of it is
genuinely absent — the real schema has one `int` stock column per product/variant and nothing else.

## 1. Why there's no separate `warehouses` table

The roadmap's gap table lists "warehouses" and "branches" on the same line (*"Warehouses / Branches /
Multi-location stock"*). Phase 4 already built `branches` — a seller's physical locations. Building a
second, near-identical `warehouses` table now would just be two location tables answering the same
question ("where is this stock"), which is exactly the kind of redundant parallel concept
`DATABASE_GAP_ANALYSIS.md` §6 already warns against ("what's already a good foundation — do not rebuild").
This phase's `stock_items`/`stock_movements` reference `branches.id` directly. A seller who wants a
dedicated storage-only location just creates a `branch` for it — nothing about `branches` assumes it's
customer-facing.

## 2. The single safe integration point: `ProductService::updateStock()`

Before touching anything, this phase's first real finding: **every stock-changing call site in the entire
app — all 15 of them, across `OrderService`, `ReturnRequestService`, `Seller/Admin/App` API controllers, and
a payment webhook — already funnels through one method**, `ProductService::updateStock()`. This is the
opposite situation from Phase 4's ~90-scattered-call-sites problem with seller-id resolution — here there's
already exactly one chokepoint.

That meant the safe move was to extend `updateStock()` itself with 5 new **optional, trailing** parameters
(`$branchId`, `$referenceType`, `$referenceId`, `$unitCost`, `$notes` — all default to values that reproduce
today's behavior) rather than duplicate its three-stock-type branching logic anywhere else. Every one of the
15 existing call sites keeps calling it exactly as before and, with no code changes on their part, now also
gets a real `stock_movements` ledger entry and `stock_items` running-total update — recorded generically
(`branch_id = null`, `reference_type = 'legacy_adjustment'`) since those call sites don't have
branch/business-reason context to pass. New Phase 5 code (`PurchaseOrderService::receiveGoods()`,
`InventoryService::adjustStock()`) passes the extra arguments for accurate classification.

This was verified, not assumed: `tests/Feature/Phase5/ProductServiceUpdateStockLedgerTest.php` proves a
call made exactly the way the 15 existing sites already call it (no new arguments) still updates
`product_variants`/`products.stock` identically to before, *and* now also writes the ledger entry — backward
compatibility plus the new side effect, confirmed by test rather than by reading the diff.

## 3. `stock_movements` + `stock_items`

`stock_movements` is an immutable ledger — every quantity change, ever, with `movement_type` (`in`/`out` —
direction only) and `reference_type` (`goods_received_note` / `transfer` / `manual_adjustment` /
`legacy_adjustment` — the actual reason). Never updated or deleted; a correction is a new offsetting row,
the same principle `PHASE_1_FINANCIAL_PRECISION.md` already applies to money in this codebase.

`stock_items` is a running per-`(seller, branch, variant)` quantity, kept in sync at write time by
`InventoryService::recordMovement()` — the one method that writes to both tables, so there's exactly one
place either could drift from the other. `branch_id = null` is the "unlocated" bucket every pre-Phase-4
seller's stock falls into by default.

## 4. Suppliers, Purchase Orders, Goods Received Notes

Straightforward net-new CRUD plus one real workflow: `PurchaseOrderService::create()` (draft→ordered, with
line items) and `PurchaseOrderService::receiveGoods()` (partial or full receipt against a PO). Receiving
goods is the one place that turns a PO into real stock — it validates the received quantity doesn't exceed
what remains on the PO item, records a `goods_received_note`/items, then calls
`ProductService::updateStock(..., 'plus', $branchId, 'goods_received_note', $grnId, $unitCost)` per line —
one call updates the legacy field and the ledger together, so they can't drift apart the way they would if
this service wrote to the ledger directly. A PO's `status` (`ordered` → `partially_received` → `received`)
is derived from whether every line item's `remaining_quantity` has reached zero, recomputed after each
receipt, not tracked as separate mutable state.

`InventoryService::transferStock()` moves stock between two branches of the *same* seller — verified by
checking both branches' `seller_id` first. Net effect on total owned quantity is zero, so unlike
`receiveGoods()`, this deliberately does **not** call `ProductService::updateStock()` — only the per-branch
`stock_items` split changes; the legacy total is already correct and shouldn't move.

`InventoryService::weightedAverageCost()` — a **simple weighted average across every recorded
`goods_received_note` receipt** for a variant (all branches, all time). This is explicitly not a perpetual
moving-average or FIFO cost-layer engine — it recomputes from the full ledger on each call rather than
maintaining a running per-unit cost that updates (and partially resets) as stock is consumed. Named and
documented precisely so it isn't mistaken for more precision than it has; a true perpetual/FIFO valuation
engine is listed below as follow-up, not silently implied by the method's presence.

## 5. Tests

`tests/Feature/Phase5/` (4 new files, 18 new tests):

- `InventoryServiceTest.php` (6) — `recordMovement()` creates/updates ledger + running total correctly in
  both directions and never goes negative; `transferStock()` moves quantity with zero net change and rejects
  a branch belonging to another seller; `weightedAverageCost()` returns `null` with no receipts and computes
  correctly across two receipts at different costs.
- `ProductServiceUpdateStockLedgerTest.php` (4) — the backward-compatibility proof described in §2, plus a
  new-style call with explicit branch/reference recorded accordingly.
- `PurchaseOrderServiceTest.php` (5) — PO creation; full receipt updates stock and marks `received`; partial
  receipt marks `partially_received`; over-receipt is rejected before any stock changes; two partial
  receipts sum correctly to finish the order.
- `PurchaseOrderControllerTest.php` (3) — IDOR coverage (a seller cannot create a PO against another
  seller's supplier, or receive goods against another seller's PO) plus a full owning-seller create→receive
  flow.

`tests/Feature/Phase1/MigrationBaselineTest.php`'s table-count assertion updated 95 → 102 for the 7 new
tables (expected consequence of this phase's migration, not a regression).

Full suite: **199 passing** (181 before this phase), zero regressions.

## 6. Documented, not built this phase (with reason)

- **True perpetual/FIFO cost-layer valuation.** `weightedAverageCost()` (§4) is a real, useful first pass,
  not a placeholder — but it's not the cost-layer engine the roadmap's phrase "inventory valuation
  (FIFO/weighted-avg)" ultimately implies. That's its own focused pass on top of the `stock_movements` ledger
  this phase built, which is now the right foundation for it.
- **Seller-panel UI for suppliers/POs/GRNs.** This phase delivers the backend (migrations, models, services,
  controllers, tests) — matching Phase 3/4's pattern of extending flows via API rather than building new
  screens in the same pass.
- **Low-stock alerting / reorder points.** Not asked for by the roadmap's one-line scope; a natural
  follow-up once `stock_items` is the real source of truth for on-hand quantity per location.
