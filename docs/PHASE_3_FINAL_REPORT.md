# Phase 3 Final Report — Commerce Core (Returns/RMA & Order-Origin Discriminator)

**Status: complete.** Every number below was counted directly from git history, test output, and the actual
source in this repo — not estimated. `docs/PHASE_3_COMMERCE_CORE.md` carries the full findings and
implementation detail; this report is the index and the numbers.

## Exact numbers

| Metric | Count |
|---|---|
| Commits this phase | 1 |
| Findings fixed (RMA reason/quantity capture, transition-guard consolidation + drift fix, order-channel discriminator across both order-creation paths) | 4 |
| IDOR fixed (seller-ownership gap on return-request transitions, found during this phase) | 1 |
| Bonus fix found in the same code (misleading always-success response on a not-found/not-owned return request) | 1 |
| New Phase 3 test files | 4 |
| New Phase 3 tests | 17 |
| Total test suite (Phase 1 + 2 + 3) | 168 passing, 0 failing |
| Total test suite at Phase 3 start | 151 |
| Files changed (app/migrations/tests/docs) | 16 |

## What changed

**Returns/RMA:**

- `return_requests.reason` and `return_requests.quantity` (both nullable) — a customer can now say why
  they're returning an item and how many units, instead of always implicitly the whole line item.
- `ReturnRequest` status constants (`STATUS_PENDING`/`STATUS_APPROVED`/`STATUS_REJECTED`/`STATUS_RETURNED`/
  `STATUS_PICKED_UP`) replacing magic status integers at the two call sites that branch on them.
- New `ReturnRequestService` consolidating the transition-guard chain and status-change side effects that
  were previously duplicated near-verbatim between `Admin\ReturnRequestController::update()` and
  `Seller\ReturnRequestController::update()` — and had drifted: Admin's copy was missing two guards
  ("can't revert to pending" from approved/rejected) that Seller's copy enforced. Both panels now enforce all
  six guards.
- Reason/quantity threaded through the real customer-facing entry point
  (`App\v1\ApiController::update_order_item_status()` → `OrderService::update_order_item()` →
  `validateOrderStatus()` → `setUserReturnRequest()`), with server-side validation that a requested return
  quantity can't exceed what was actually ordered.

**Order-origin discriminator:**

- `orders.channel` (default `'marketplace'`, backfilled from existing `is_pos_order` values) alongside the
  existing `is_pos_order` flag, which stays exactly as-is.
- `Order` model constants `CHANNEL_MARKETPLACE` / `CHANNEL_POS` / `CHANNEL_AFFILIATE` (the last defined now,
  unused until Phase 7's affiliate ordering exists).
- Set correctly on **both** of the two independent order-creation code paths discovered while implementing
  this: `OrderService::placeOrder()` (storefront/marketplace) and `Seller\PosController::place_order()`
  (POS) — the latter was found, during implementation, to bypass `OrderService::placeOrder()` entirely and
  build/insert its own order data directly, so the fix originally planned for one call site needed to land
  in two.

**Security fix found and fixed within this phase (with the user's explicit sign-off to include it here rather
than defer it):**

- `Seller\ReturnRequestController::update()` had no check that the target `ReturnRequest` belonged to the
  logged-in seller — any seller could approve/reject/complete another seller's return request (triggering
  real wallet-refund and stock-restock side effects) by guessing its id. Its own `list()` method was already
  correctly scoped; `update()` wasn't. Fixed by scoping the lookup the same way, plus a related fix: a
  not-found/not-owned id previously fell through to the same success response as a real update — it now
  returns an explicit error instead.

## Documented, not fixed this phase (with reason)

| Finding | Why not fixed now | Doc |
|---|---|---|
| `process_refund()` / `updateStock()` still act on an order item's full quantity/amount even when a return request's new `quantity` field records a smaller partial amount | The *record* now correctly captures a partial-quantity return (validated, stored, visible to admin/seller); wiring proportional refund/restock math touches a 300+ line method (`validateOrderStatus()`) shared by cancel *and* return across three table contexts, and this codebase's own Phase 1 docs already flag financial calculation code here for above-average care. Scoped as its own focused pass rather than folded in silently. | `PHASE_3_COMMERCE_CORE.md` §1 |

## Verification performed

- Full test suite run after each meaningful change, not just at the end: **168/168 passing**, zero
  regressions introduced at any point (151 passing immediately before this phase's own tests were added).
- `php -l` clean on every touched/new PHP file (7 modified controllers/models/services, 3 new app files, 2
  new migrations, 4 new test files).
- Both new migrations run cleanly against the real MariaDB instance used by this repo's test suite (not
  SQLite) via `php artisan migrate`.
- The order-channel discriminator was proven against a real end-to-end POS order (via
  `PosController::place_order()`, reusing `PosSaleTest`'s proven fixture pattern) and a real end-to-end
  marketplace order (via `OrderService::placeOrder()`), not just unit-level assertions on the two write
  sites — this is what caught the `PosController` bypass gap the original plan missed.
- The seller-ownership fix was proven in both directions: a stranger seller is denied and the return request
  is left unchanged; the owning seller can still transition their own request exactly as before.

## What Phase 3 did not do (explicitly, scope boundaries)

Did not build a structured return-shipping/pickup workflow (`STATUS_PICKED_UP` exists as a constant but no
new logic transitions into or out of it — matching its state before this phase). Did not touch payment-gateway
refund reversal — returns still credit the store wallet only, exactly as before this phase. Did not wire
proportional refund/restock math for partial-quantity returns (documented above). Did not add an
affiliate order-placement path — `Order::CHANNEL_AFFILIATE` is defined for Phase 7 but nothing in this
codebase sets it yet, matching the roadmap's own phase split.
