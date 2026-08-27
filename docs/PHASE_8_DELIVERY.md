# Phase 8 — Delivery: Auto-Dispatch and Structured Driver Earnings

`docs/IMPLEMENTATION_ROADMAP.md` (Phase 8) scopes this as: *"Extend existing delivery-boy/parcel system
with zones, dispatch, structured driver earnings."*

## 1. What already existed (verified, not rebuilt)

- **Zones**: `zones` (city/zipcode-scoped) and `users.serviceable_zones` (a delivery boy's comma-separated
  assigned zone ids) already exist. `app/function_helper.php`'s `getDeliveryBoys()` already filters
  candidates by zone via `FIND_IN_SET(?, serviceable_zones)`.
- **Cash reconciliation**: `fund_transfers` + `Admin\FundTransferController` +
  `Delivery_boy\CashCollectionController` already handle a driver settling COD cash they *collected* back to
  the platform — the opposite direction of money from what this phase adds. Confirmed working, left
  untouched.
- **Manual assignment**: `Admin\OrderController` and `Seller\OrderController` already let a human pick a
  specific `delivery_boy_id` and assign it to an order item or parcel via
  `OrderService::updateOrder(['delivery_boy_id' => ...], ...)`.

**What was actually missing**, confirmed by grep before writing anything: no auto-dispatch (assignment was
100% manual, nothing ranked or picked a driver automatically) and no structured per-delivery earning (no
code anywhere paid a driver a fee for completing a delivery — `fund_transfers` only tracks cash *coming in*
from the driver, never money going out to them).

## 2. Auto-dispatch

`DispatchService::rankAvailableDeliveryBoys(?zoneId)` — active delivery boys (`role_id = DELIVERY_BOY`,
`status = 1`), zone-filtered with the exact same `FIND_IN_SET` technique `getDeliveryBoys()` already uses
(not a new geography model), ranked by **current load** — fewest active (not yet
delivered/cancelled/returned) assigned order items first, so dispatch spreads work rather than always
picking the same driver.

`DispatchService::autoAssign($orderItemId, ?zoneId)` picks the top-ranked candidate and assigns them via the
**exact same** `OrderService::updateOrder()` call the manual assignment path already uses —
`Admin\OrderController::auto_assign_delivery_boy()` is a new, additive endpoint alongside the existing
manual-assignment flow, not a replacement for it. Returns `null` (no assignment made, caller decides what to
do) when no active, zone-matching driver exists — never forces a bad assignment.

## 3. Structured driver earnings

`delivery_earnings`: one immutable row per delivered order item paid out — `delivery_boy_id`, `order_id`,
`order_item_id` (unique — the idempotency guard), `amount`, `rate_type`/`rate_value` (a snapshot of the rate
in effect at the time, not just the computed total).

`DeliveryEarningService::creditForDeliveredItem()` is called from the **exact same trigger point** as the
existing refer-a-friend bonus and Phase 7's affiliate-commission approval —
`Admin\OrderController`'s delivered-status branch — reading a rate from `system_settings`
(`delivery_earning_status`/`delivery_earning_type`/`delivery_earning_value`, the same JSON-config pattern
`processReferralBonus()` already uses), computing flat or percentage-of-`orders.delivery_charge`, and
crediting the driver's wallet via the existing `WalletService`. **Off by default** — a fresh install pays no
delivery earnings until an admin explicitly configures `delivery_earning_status = 1`, so this is a pure
opt-in addition, never a silent new cost.

## 4. What this phase does not do (explicitly, scope boundaries)

- **No UI for configuring the earning rate or reviewing dispatch/earnings history** — this phase delivers
  the backend; matches every prior phase's pattern.
- **No real-time driver location / nearest-driver dispatch** — ranking is by zone match + current load, not
  live GPS distance; the schema/data for driver location tracking doesn't exist in this codebase and adding
  it is a larger, separate feature.
- **No automatic re-dispatch on driver rejection/timeout** — `autoAssign()` makes one assignment; retry/
  reassignment logic (e.g. "driver didn't accept within N minutes, try the next one") is a natural follow-up
  once real usage patterns are visible, not built speculatively now.
- **Per-parcel dispatch** — `delivery_boy_id` also exists on `parcels` (multi-seller order splits); this
  phase's dispatch/earnings both work at the `order_items` level, matching where the majority of
  delivery-boy assignment already happens in the existing manual-assignment code. Parcel-level dispatch is a
  bounded, well-understood follow-up, not folded in here to keep this phase's surface reviewable.

## 5. Tests

`tests/Feature/Phase8/` (2 new files, 10 new tests):

- `DispatchServiceTest.php` (5) — zone filtering; inactive drivers excluded; load-balanced ranking (a
  driver with a `delivered` item doesn't count as "busy," a real end-to-end auto-assign picks the correct
  least-loaded zone-matching driver and updates the order item); `autoAssign()` returns `null` (not a bad
  assignment) when no driver matches.
- `DeliveryEarningServiceTest.php` (5) — disabled-by-default no-op; no-op with no assigned driver; flat-rate
  crediting; percentage-of-delivery-charge computation; proven idempotency (the same order item is never
  credited twice, even calling the method twice directly).

`tests/Feature/Phase1/MigrationBaselineTest.php`'s table-count assertion updated 108 → 109 for the 1 new
table (expected consequence of this phase's migration).

Full suite: **233 passing** (223 before this phase), zero regressions.
