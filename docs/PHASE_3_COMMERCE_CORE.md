# Phase 3 — Commerce Core: Returns/RMA & Order-Origin Discriminator

`docs/IMPLEMENTATION_ROADMAP.md` (Phase 3 — Commerce Core) scopes this phase as: *"Extend existing
products/orders/cart with what's missing (structured returns/RMA, order-origin discriminator for
POS/affiliate/marketplace)."* Both features already existed in skeletal form; this phase closes the
confirmed gaps in each, plus one IDOR found in the same code and fixed with the user's explicit sign-off to
include it in this phase rather than deferring it.

## 1. Returns/RMA

### What existed before this phase

`return_requests` (table + `ReturnRequest` model) already supported create/list/approve/reject/complete,
with real side effects on completion (wallet refund via `OrderService::process_refund()`, stock restock via
`ProductService::updateStock()`). Three confirmed gaps:

- No customer-supplied return **reason** field.
- No **quantity** field — a return was always implicitly "the whole line item," no partial-by-unit returns.
- The approve/reject/complete transition-guard logic was duplicated near-verbatim between
  `Admin\ReturnRequestController::update()` and `Seller\ReturnRequestController::update()`, using magic
  status integers (0/1/2/3/8) with no named constants — and the two copies had actually drifted: Seller's
  copy enforced two guards ("can't revert to pending" from approved or rejected) that Admin's copy was
  missing entirely.

### `ReturnRequest` status constants

`app/Models/ReturnRequest.php` gained named constants matching the pattern established in Phase 2
(`Role::SUPER_ADMIN` etc.):

```php
const STATUS_PENDING   = 0;
const STATUS_APPROVED  = 1;
const STATUS_REJECTED  = 2;
const STATUS_RETURNED  = 3;
const STATUS_PICKED_UP = 8;
```

`STATUS_PICKED_UP` is defined for completeness (matching what the raw `8` in the existing schema/data
represents) but no code path in this phase transitions into or out of it — it was already unused before this
phase and stays that way.

### `reason` and `quantity` columns

Migration `database/migrations/2025_02_03_000000_add_return_request_reason_and_quantity.php` adds `reason`
(nullable `varchar(512)`) and `quantity` (nullable `integer`) to `return_requests`. Both nullable, not
required: an older app client that doesn't send them still works unchanged, and the write path
(`OrderService::setUserReturnRequest()`) defaults `quantity` to the order item's full ordered quantity when
omitted.

### `ReturnRequestService` — consolidating the duplicated guard/transition logic

New `app/Services/ReturnRequestService.php` extracts the transition-guard chain and the status-3/1/2 side
effects into one tested path, using the new model constants instead of magic numbers:

- `guardTransition(ReturnRequest $returnRequest, int $newStatus): ?string` — returns an error message string
  for a blocked transition, `null` if allowed. All **six** guard pairs now apply to both callers (the two
  Admin was previously missing — approved→pending and rejected→pending — are enforced there too now, closing
  that drift rather than picking one copy as the "reference" and silently changing behavior for one panel).
- `applyTransition(ReturnRequest $returnRequest, int $newStatus, ?string $remarks, $deliverBy = null): void`
  — persists the status/remarks change, then runs the existing side effects unchanged (`process_refund()`,
  `updateStock()`, `update_order_item()`, `delivery_boy_id` assignment) keyed off the new status.

Both `Admin\ReturnRequestController::update()` and `Seller\ReturnRequestController::update()` now call into
this after their own auth/ownership checks, replacing their formerly-inlined and formerly-diverging guard
blocks. Each controller keeps its own notification-building code (FCM + custom-message templating)
untouched — recipients and message templates differ slightly between the two panels, and that logic was not
part of what needed consolidating.

### Customer-facing reason/quantity capture

Tracing the actual call path (not assumed from the roadmap wording) found the real customer-facing
return-creation entry point is `App\v1\ApiController::update_order_item_status()` — a distinct, per-order-item
method from `update_order_status()`. `Admin\OrderController::update_order_status()`'s own internal
`validateOrderStatus()` call does not pass `fromuser=true`, so it never creates a `ReturnRequest`; only the
`update_order_item_status()` → `OrderService::update_order_item(..., fromapp: true)` path does. (The initial
research pass that fed the approved plan had this one level off — corrected during implementation by reading
the actual call sites, not a scope change.)

`reason` and `quantity` are now optional request params on `update_order_item_status()`, threaded through:

```
ApiController::update_order_item_status()
  → OrderService::update_order_item($id, $status, $return_request, $fromapp, $reason, $quantity)
    → OrderService::validateOrderStatus($ids, $status, $table, $user_id, $fromuser, $parcel_type, $reason, $quantity)
      → OrderService::setUserReturnRequest($row, $table, $reason, $quantity)
        → ReturnRequest::create([..., 'reason' => $reason, 'quantity' => $quantity ?? $row->quantity])
```

`validateOrderStatus()` now also selects `oi.quantity` (previously not selected) and, when a return quantity
is supplied, validates it's a positive integer not exceeding the order item's ordered quantity before any
`ReturnRequest` row is written — a request for more than was ordered is rejected with a clear message and no
partial state is created.

### Deliberately not changed this phase

`process_refund()` and `updateStock()` still act on the order item's **full** quantity/amount even when a
return request's `quantity` is less than the full ordered amount — i.e. the *record* now captures a real
partial-quantity return (visible to admin/seller, validated, stored), but the *money and stock math* stay
whole-item, same as before this phase. Wiring proportional refund/restock math touches
`OrderService::validateOrderStatus()` (a 300+ line method shared by cancel *and* return, across
`order_items`/`orders`/`parcels` table contexts) and financial calculation code this codebase's own Phase 1
docs (`PHASE_1_FINANCIAL_PRECISION.md`) already treat with above-average care. This is documented as an open
follow-up rather than folded in silently — see the Final Report.

### Seller-ownership IDOR fix (found during this phase, fixed with the user's explicit approval)

`Seller\ReturnRequestController::update()` did `ReturnRequest::find($returnRequestId)` with **no check** the
target request belonged to the logged-in seller — its own `list()` method was correctly scoped
(`whereHas('orderItem', fn($q) => $q->where('seller_id', $seller_id))`), `update()` wasn't. Any seller could
transition another seller's return request (approve/reject/mark-returned, triggering the real refund/restock
side effects above) by guessing its id — the same IDOR shape Phase 2 fixed elsewhere in this app.

Fixed by scoping the lookup the same way `list()` already does. A bonus fix found in the same spot: when the
id genuinely didn't exist (or, before the fix, wasn't owned by the caller), the method fell through to the
same `$response['error'] = false` success response at the bottom of the function as a real update — a
"not found" now returns an explicit error response instead of a misleading false-success.

## 2. Order-origin discriminator

### What existed before this phase

`orders.is_pos_order` (binary tinyint) was the only real channel signal — set to `1` only by
`Seller\PosController`'s order-placement flow, `0` everywhere else, and read only as a report filter
(`WHERE is_pos_order = 0`), never as a business-logic branch. A dormant, fully unused `orders.type` column
already existed in the schema (pre-existing eShop Plus schema debt, left untouched — reusing it risks a
future reader assuming it was always meaningful) and a similarly-named but semantically unrelated
`order_items.order_type` column (`regular_order` vs `combo_order` — a product-shape flag, not a channel flag)
both risked being confused with what "order-origin discriminator" actually means. No affiliate
order-placement path exists anywhere in this codebase — confirmed absent, correctly out of scope (Phase 7).

### `channel` column and model constants

Migration `database/migrations/2025_02_04_000000_add_order_channel_discriminator.php` adds a new `channel`
`varchar(32)` column to `orders` (default `'marketplace'`), plus a one-time backfill of existing rows
(`is_pos_order = 1` → `channel = 'pos'`; else → `channel = 'marketplace'`). `app/Models/Order.php` gained:

```php
const CHANNEL_MARKETPLACE = 'marketplace';
const CHANNEL_POS = 'pos';
const CHANNEL_AFFILIATE = 'affiliate';
```

`CHANNEL_AFFILIATE` is defined now so Phase 7 doesn't need another migration later, but no code path in this
phase (or before it) sets it — no affiliate ordering exists yet. `is_pos_order` itself is untouched: the
existing report/query filters against it keep working exactly as before; `channel` is an additive, richer
parallel concept, not a replacement.

### Two independent order-creation code paths — both needed the fix

The approved plan assumed setting `channel` inside `OrderService::placeOrder()` alone would cover both the
marketplace and POS paths, on the premise that POS also flows through `placeOrder()` with
`is_pos_order=1`. Reading `Seller\PosController` directly during implementation showed this premise was
wrong: its call to `OrderService::placeOrder()` is commented out, and the controller builds its own
`$order_data` and calls `Order::forceCreate()` directly, at two separate insertion points (regular products
and combo products). This was caught specifically because a real end-to-end POS test was written (reusing
`tests/Feature/Phase1/PosSaleTest.php`'s fixture pattern) rather than only reasoning about the code
statically — the first version of that test failed because `channel` came back null despite the
`placeOrder()` edit being in place.

Fixed by setting `'channel' => Order::CHANNEL_POS` directly in both of `PosController`'s own
`$order_data`-building blocks (regular products and combo products), alongside the existing `is_pos_order`
value each already sets. `OrderService::placeOrder()` sets
`'channel' => !empty($data['is_pos_order']) ? Order::CHANNEL_POS : Order::CHANNEL_MARKETPLACE` for the
storefront/marketplace checkout path, which is the only path that actually calls it today.

## 3. Tests

`tests/Feature/Phase3/` (4 new files, 17 new tests):

- `ReturnRequestServiceTest.php` (8 tests) — every guard-transition pair, both blocked and allowed.
- `ReturnRequestOwnershipTest.php` (2 tests) — a seller cannot transition another seller's return request;
  the owning seller still can.
- `ReturnRequestReasonQuantityTest.php` (4 tests) — reason/quantity stored when provided; quantity defaults
  to the full ordered amount when omitted; a requested quantity above what was ordered is rejected before any
  row is written; a valid partial quantity is accepted and stored.
- `OrderChannelTest.php` (3 tests) — a real POS sale (via `PosController::place_order()`) is marked
  `channel = 'pos'`; a real marketplace checkout (via `OrderService::placeOrder()`) is marked
  `channel = 'marketplace'`; the migration's backfill logic sets `channel` correctly from existing
  `is_pos_order` values.

Full suite: **168 passing** (151 before this phase's tests were added), zero regressions.
