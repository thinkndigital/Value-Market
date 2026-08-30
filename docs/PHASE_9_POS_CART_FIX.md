# Phase 9 (32-phase SaaS brief) — POS Cart Bug Repair

`TECHNICAL_DEBT.md` listed 4 confirmed POS bugs. Before touching anything, re-verified all 4 against the
current codebase rather than assuming the list was still accurate — it wasn't: 2 of the 4 were already fixed
in this repo's own Phase 6 (`docs/PHASE_6_POS.md`, predating this pass and never removed from the debt
register). The remaining 2 are both in `CartService::addToCart()`, and both are fixed here.

## What was actually re-verified

1. **Order-item loop only recording the first line item** — already fixed (`PHASE_6_POS.md` §1). Confirmed
   still fixed: `PosMultiItemAndPaymentsTest::test_a_two_item_pos_cart_records_both_items_and_decrements_both_stocks`
   passes against current code.
2. **POS never decrementing stock** — already fixed (`PHASE_6_POS.md` §2). Confirmed still fixed: same test,
   plus `PosSaleTest`/`PosComboStockTest`.
3. **`addToCart()` returns `false` for a brand-new item** — still broken, confirmed by reading and by test.
4. **`addToCart()` crashes on a multi-item cart of new products** — still broken, confirmed by reading and by
   test.

## Root cause (deeper than the debt register described)

Reading `CartService::addToCart()` closely turned up that bugs 3 and 4 share one root cause, and it's more
fundamental than "crashes on new items": the method `return`s from **inside** its `foreach` loop the moment
it processes *any* item —

- Updating an already-in-cart item: `return true;` immediately, regardless of `$fromApp` (both branches of
  that `if` did the same thing).
- Creating a brand-new item: `return true;` only when `$fromApp` is true; `Seller\PosController::place_order()`
  always calls this with `$fromApp` left at its default `false`.

So a multi-item batch never got processed past its first item under multiple conditions — not just "new
items after the first," but *any* item after the first, including an existing-item update. This was
previously undocumented; `TECHNICAL_DEBT.md`'s framing ("crashes on multiple new products") was one visible
symptom of the broader "only the first item is ever touched" bug, not the whole bug.

The `$store_id[$index]` crash itself: `PosController::place_order()` passes one scalar `store_id` for the
entire sale (a POS sale is always for one store), which `addToCart()` explodes on commas and indexes per
item (`$store_id[$index]`) - a shape that's only ever correct for a caller supplying one store_id *per item*
(`CartController`'s storefront/API add-to-cart genuinely does this, for a real multi-vendor cart spanning
several sellers - confirmed by reading that call site, not assumed). Once the loop reached a second item
without having already returned, `$store_id[1]` didn't exist.

## Fix

`app/Services/CartService.php::addToCart()`:
- Removed every `return true;`/`return false;` from inside the loop. The method now processes every item in
  the batch, then returns `true` once at the end (the stock-validation array-with-`error` return, before the
  loop, is unchanged). `$fromApp` is kept as a parameter (existing callers still pass it positionally) but no
  longer changes behavior.
- `'store_id' => $store_id[$index] ?? ($store_id[0] ?? ''),` — falls back to the first value when a specific
  index doesn't exist, covering the single-shared-value case (POS) without changing the genuine per-item
  case (every index already exists there, so the fallback never triggers).

## Tests

- `tests/Feature/Phase1/PosSaleTest.php` — the two tests that used to assert the bugs (`assertTrue($payload
  ['error'], 'Documents a known bug...')`, `expectException(ErrorException::class)`) now assert the fixed
  behavior instead: a brand-new item's first sale succeeds (order + order item + stock decrement all real),
  and a two-new-item cart succeeds with both items recorded and both stocks decremented.
- `tests/Feature/CartServiceMultiItemTest.php` (new, 3 tests) — direct unit coverage on `CartService`
  itself, proving both shapes coexist: a genuine per-item `store_id` list (storefront/API multi-vendor cart)
  keeps each item's own store, a single shared `store_id` (POS) applies to every item, and a mixed
  existing+new batch processes both instead of stopping at the first.

Full suite: **577 passing** (574 before this phase), zero regressions.

## What's still open in POS (not touched this pass)

This closes the specific 4 bugs `TECHNICAL_DEBT.md` named. The 32-phase brief's fuller POS ask (full-screen
responsive UI, branch-scoped inventory, atomic multi-step transaction wrapping beyond what already exists,
concurrent-sale stock locking) is separate, larger scope — not attempted here.
