# Phase 6 — POS: Shifts, Split Payments, and Two Confirmed Bug Fixes

`docs/IMPLEMENTATION_ROADMAP.md` (Phase 6) scopes this as: *"Extend the existing
`Seller\PosController`/`StockController`... add shifts, till, split payments, cash reconciliation, wired to
the same inventory/ledger as e-commerce."*

Before adding anything new, this phase closed two real, already-documented bugs in
`PosController::place_order()` that `docs/PHASE_1_TRANSACTION_BOUNDARIES.md` found and explicitly deferred
here: *"Fixing either of those is a POS business-logic change (Phase 6), not a transaction-boundary
change."* Both were proven with a real test before being fixed, and both tests now assert the corrected
behavior instead of documenting the bug.

## 1. Bug fix: a multi-item POS cart only ever recorded its first item

`place_order()`'s per-line loop used to build one `OrderItems` row, `DB::commit()`, build the response, and
`return` — all **inside** the loop body. The very first iteration always returned, so a cart with two or
three products only ever got an `OrderItems` row (and, see below, a stock deduction) for the first one — the
rest were silently dropped, even though the order's own `total`/`final_total` already reflected the whole
cart. Fixed by moving the commit/response outside the loop so every line item is processed within the same
transaction before it commits once.

**Proven, not assumed**: `tests/Feature/Phase6/PosMultiItemAndPaymentsTest.php`'s
`test_a_two_item_pos_cart_records_both_items_and_decrements_both_stocks` pre-seeds a genuinely two-item POS
sale and asserts **both** `OrderItems` rows exist and **both** variants' stock moved correctly — not just
that the method doesn't crash.

**A related, separate bug found but NOT fixed here**: `CartService::addToCart()` has its own pre-existing
crash for a *genuinely new* (never-before-carted) multi-item POS sale — it explodes a single scalar
`store_id` and indexes it per cart item (`$store_id[$index]`), which is only ever correct for one item. This
is a different bug in a different service/file, not one of the two `PHASE_1_TRANSACTION_BOUNDARIES.md`
pointed at this phase, so it's documented here as still open rather than silently folded in. Every multi-item
test in this phase pre-seeds cart rows first (the same technique Phase 1's own POS tests already use) to
isolate what this phase actually fixed.

## 2. Bug fix: POS never decremented stock

Neither `place_order()` (regular products) nor `combo_place_order()` (combo products) ever called a stock
service after creating an order — `combo_place_order()` even calls
`ComboProductService::validateComboStock()` *before* creating the order (checking availability) and then
never calls the corresponding deduction. Every e-commerce checkout order (`OrderService::placeOrder()`)
already does this correctly; POS just never did.

Fixed by mirroring that exact same pattern:

- `place_order()`, per regular line item: `ProductService::updateStock($variantId, $qty, '', $branchId,
  'legacy_adjustment', $orderId)` — which, thanks to Phase 5's extension of that method, also writes a real
  `stock_movements` ledger entry for the sale, not just decrements the legacy field.
- `place_order()`, per combo line item, and `combo_place_order()`'s own loop:
  `ComboProductService::updateComboStock($id, $qty, 'subtract')` — the existing legacy combo-stock method
  (a separate model/table from `Product_variants`, not yet integrated with Phase 5's ledger - see §5).

Both proven by test: `PosComboStockTest.php` for the combo path, and the multi-item test above for regular
products.

## 3. Till shifts (`pos_shifts`)

`PosShiftService::open()`/`close()`. A cashier can't open a second shift while one is already open
(`user_id`-scoped, checked before creating a new row). Closing computes `expected_cash = opening_cash +
SUM(cash-method pos_payments during this shift)` — card/wallet/other-method payments are deliberately
excluded from the cash expectation — and records `cash_variance = closing_cash - expected_cash`, where
`closing_cash` is what the cashier physically counted. `Seller\PosShiftController` scopes everything via
`TenantContext`, same IDOR discipline as every controller since Phase 4.

## 4. Split payments (`pos_payments`)

`orders.payment_method` stays exactly as every existing report/query already reads it — the primary/first
method. `pos_payments` is an additive, richer breakdown: a POS sale can be paid with more than one method
(part cash, part card), each as its own row summing to the order total. `PosShiftService::recordSaleForOpenShift()`
is called once, right after order creation, from both `place_order()` and (implicitly available for)
`combo_place_order()`:

- If the request includes an explicit `payments` array (`[{payment_method, amount}, ...]`), each line is
  recorded as its own `pos_payments` row.
- If it doesn't (every pre-Phase-6 caller, and most real POS terminals that only support one tender per
  sale), **one** payment line is recorded automatically from the order's own `payment_method`/`total_payable`
  — so cash-reconciliation math always has something to sum, without requiring existing POS client code to
  change how it calls `place_order()`.

The order is also attached to whichever shift applies: the explicitly requested `pos_shift_id` if it's a
real, currently-open shift; otherwise the acting cashier's own open shift (via `Auth::id()` — the order's
own `user_id` is the *customer*, not the cashier, a distinction the code is explicit about); otherwise no
shift at all. A POS sale is never blocked just because no shift is open — shift tracking is additive on top
of the existing sale flow, not a new requirement gating it.

## 5. What this phase does not do (explicitly, scope boundaries)

- **`CartService::addToCart()`'s multi-item crash** (§1) — a different bug in a different file, left open
  and documented rather than folded into this phase's already-large surface.
- **Combo product stock is not yet integrated with Phase 5's `stock_movements`/`stock_items` ledger** —
  `ComboProductService::updateComboStock()` is a separate, legacy mechanism against the `combo_products`
  table (not `product_variants`), correctly called now (bug fixed), but not yet ledger-aware. Bringing combo
  stock onto the same ledger as regular products is its own scoped follow-up, not silently implied by "POS
  is wired to the ledger" — regular-product POS sales are ledger-aware today; combo sales aren't yet.
- **No new UI** — this phase delivers the backend (bug fixes, `pos_shifts`/`pos_payments`
  migration/models/service/controller, tests), matching every prior phase's pattern.
- **`StockController`** (also named in the roadmap's phase description) was read but not modified - its
  existing manual stock-adjustment screens already route through `ProductService::updateStock()` (the same
  chokepoint Phase 5 extended), so they already gained ledger integration for free with no code change
  needed here.

## 6. Tests

`tests/Feature/Phase6/` (4 new files, 11 new tests):

- `PosMultiItemAndPaymentsTest.php` (3) — the multi-item fix proven end to end; a POS sale with no explicit
  payment breakdown records one generated line; an explicit split-payment request records each line summing
  correctly.
- `PosComboStockTest.php` (1) — a combo POS sale now actually decrements combo stock.
- `PosShiftServiceTest.php` (5) — open/close lifecycle, duplicate-open rejection, cash-expectation math
  (cash-only, excluding other methods), a short-till variance, and a closed shift can't be closed again.
- `PosShiftControllerTest.php` (2) — a seller can open/close their own shift; cannot close another seller's.

Two pre-existing tests updated (not regressions - both were written to fail exactly when these bugs got
fixed, per their own docblocks):

- `tests/Feature/Phase1/PosSaleTest.php`'s `test_a_single_item_pos_sale_creates_an_order_and_decrements_stock`
  — was asserting the *buggy* unchanged-stock value on purpose ("this test starts failing - correctly - the
  moment that bug is fixed"); now asserts the correct decremented value.
- `tests/Feature/Phase1/MigrationBaselineTest.php`'s table-count assertion, 102 → 104 for the 2 new tables
  (expected consequence of this phase's migration).

Full suite: **210 passing** (199 before this phase), zero unexpected regressions — the 2 updated assertions
above were the intended, documented outcome of fixing the bugs they existed to flag.
