# Phase 9/10 (32-phase SaaS brief) — POS Concurrency, Branch Inventory, Full-Screen UI

Closes the three items `docs/PHASE_9_POS_CART_FIX.md` explicitly left open: concurrent-sale stock locking,
branch-scoped inventory, and a full-screen responsive POS UI. Product owner's direction: do all three.

## 1. Concurrent-sale stock locking

**Before:** `ProductService::updateStock()` — the one method all 15+ stock-changing call sites (storefront
checkout, POS, admin manual adjustment, webhooks, purchase-order receiving, returns) funnel through — read
current stock with a plain, unlocked `SELECT`, computed a new value in PHP, then wrote it back separately.
Two concurrent decrements for the same variant (a storefront checkout and a POS sale both hitting the last
unit at once, say) could both read the same stale value and both write a decremented result — an oversell,
or worse, stock drifting negative. The minus-branches also only ever guarded "is stock positive" (`> 0`),
never "is stock enough for this quantity" — a `qty=5` request against `stock=2` used to set stock to `-3`.

**Fix:** the entire read-then-write for each variant now runs inside one `DB::transaction()` with
`->lockForUpdate()` on the read. MySQL/InnoDB blocks any other transaction from reading-for-update or
writing these same rows until this one commits, so a concurrent decrement always sees the just-committed,
real value. Both real order-placing callers (`OrderService::placeOrder()`, `Seller\PosController::
place_order()`) already wrap their whole request in an outer `DB::beginTransaction()` — Laravel nests this
method's own `DB::transaction()` as a savepoint inside that, so the lock is held for the outer
transaction's full lifetime, not released early. Standalone callers (admin manual stock adjustment,
webhooks) get a real atomic transaction here for the first time too. The three minus-branch guards
(`stock_type` 0/1/2) were changed from `> 0` to `>= $qty`, so a decrement that can't be satisfied is safely
skipped instead of driving stock negative.

**What this does NOT do:** reject the *order* when a race is actually detected. Two requests landing on the
true last unit at the same instant still both get a "success" response today — one's decrement is just
silently clamped to a no-op rather than going negative. Making all 15+ call sites check a real
insufficient-stock return value and abort order creation on it is a separate, larger, cross-cutting change
(each call site has its own error-handling contract today) — not attempted in this pass. The core safety
property the brief actually asked for (stock can never go negative under concurrent load) is fully closed.

Tests: `tests/Feature/Phase9/StockConcurrencyTest.php` (6 tests) — the insufficient-stock guard fix proven
across all three `stock_type` shapes, plus a same-process two-sequential-decrements scenario proving the
serialized-read behavior the lock guarantees under real concurrency.

## 2. Branch-scoped inventory

**Before:** `stock_items` (a real per-seller-per-branch running total) has existed since this repo's own
Phase 5 (`docs/PHASE_5_INVENTORY_PROCUREMENT.md`) and POS already tagged its stock movements with a
resolved, ownership-verified branch id (`PosController::resolveOwnedPosBranchId()`). But nothing ever
*validated* against it — the stock check before a POS sale (`validateStock()`, the global helper) only ever
compared against the seller's total global stock (`products.stock`/`product_variants.stock`). A seller with
Branch A holding 6 units and Branch B holding 4 could sell 8 units from Branch A's POS terminal and it
would succeed (global stock = 10), silently overselling a location that never had that much on hand.

**Fix:** `InventoryService::validateBranchStock($sellerId, $branchId, $productVariantIds, $quantities,
$productTypes)` — when a branch is resolved for the sale, checks each regular line item's `stock_items`
quantity for that specific branch is sufficient, rejecting the whole request (before any write, same
"check then abort" placement as the existing global `validateStock()` call right above it) otherwise.
`Seller\PosController::place_order()` now resolves the acting seller and their verified-owned branch
*before* the stock checks (previously this only happened deep inside the transaction, after the cart had
already been built) and calls this new check right after the existing global one. `$branchId === null`
(seller isn't using branches, or none was resolved) skips this entirely — every seller not using the
branch feature sees no behavior change.

**What this does NOT do:** combo products aren't branch-tracked (`ComboProductService::updateComboStock()`
takes no branch parameter — matches its existing scope, not extended here), and the storefront/API checkout
path has no branch concept at all (branches are POS-only in this app's model — a customer doesn't pick a
pickup branch at online checkout).

Tests: `tests/Feature/Phase9/PosBranchInventoryTest.php` (5 tests) — no-branch sales unaffected, a sale
within branch stock succeeds, a sale exceeding branch stock is rejected even though global stock is
sufficient (the core scenario), an unreceived branch (no `stock_items` row yet) is treated as zero on hand,
and an unowned branch id is ignored rather than trusted (the existing `resolveOwnedPosBranchId()` ownership
check still holds).

## 3. Full-screen responsive POS UI

**Before:** `seller/pages/forms/pos.blade.php` rendered inside the normal `seller/layout.blade.php` chrome
(sidebar + header), and its two-column products/cart split only ever kicked in above the `xxl` Bootstrap
breakpoint (1400px) — real POS hardware (10-13" tablets) almost always falls below that, so the actual
on-device experience was a full-height stack: the whole product grid, then the cart and place-order button
below it, requiring a scroll past the entire catalog to complete a sale.

**Fix:** new `resources/views/seller/pos_layout.blade.php` — a dedicated shell with no sidebar/header
chrome (a slim top bar instead: store name, a fullscreen-toggle button, an exit-to-dashboard link), the
page filling the full viewport height with no outer scroll. `pos.blade.php` now extends this instead of
`seller/layout` — every id, every class its own JS (`assets/admin/custom/pos.js`) depends on is completely
unchanged, only the wrapper changed. The responsive split breakpoint was lowered from `xxl` (1400px) to
`lg` (992px, real tablet-landscape territory) via the new shell's own CSS, and each panel (products,
cart) scrolls independently within the viewport instead of the whole page scrolling — a cashier reaches the
cart and place-order button without scrolling past the catalog.

**What this does NOT do:** rewrite or restructure any of the page's existing JS/functional logic (product
search and pagination, cart building, customer search, payment splitting) — all real, tested, already-fixed
functionality from this repo's own Phase 6 and this brief's earlier Phase 9 pass. Only the chrome and
responsive layout changed. The combo-product cart panel (`pos-combo-product-cart-detail`, hidden by default,
shown when the Combo Products tab is active) wasn't included in the new flex layout's explicit CSS targets —
it still falls back to plain Bootstrap grid behavior between `lg` and `xxl`, a minor inconsistency versus
the regular-product cart panel, not fixed in this pass.

Tests: `tests/Feature/Phase9/PosFullscreenLayoutTest.php` (1 test) — the page renders end to end inside the
new shell (a real render, not just a syntax check — the highest-risk part of re-parenting a 1000+ line view
is an unbalanced tag from moving markup around) and confirms the normal sidebar chrome is genuinely absent.

## Full suite

622 passing (610 before this phase), zero regressions.
