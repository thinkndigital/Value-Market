# Phase 12 — Analytics / BI

`docs/IMPLEMENTATION_ROADMAP.md` (Phase 12) scopes this precisely: *"Read-layer over everything above; no
independent numbers."* No new tables this phase — a read-layer that stored its own numbers would be exactly
the redundant, driftable parallel truth every prior phase's docs have been careful to avoid (Phase 4's
`TenantContext`, Phase 5's `stock_items`, Phase 11's segments/CLV all made the same choice: compute live,
never cache-and-hope-to-keep-in-sync).

## What `AnalyticsService` provides

Six read-only methods, each a live query composed on top of services and tables Phases 1–11 already built —
not reinventing access to any of them:

- **`salesSummary($sellerId, $from, $to)`** — order count, total revenue, average order value from delivered
  `order_items` in a date range, optionally scoped to one seller.
- **`topSellingProducts($sellerId, $limit)`** — ranked by quantity sold, from the same delivered `order_items`
  data.
- **`stockValuation($sellerId)`** — on-hand quantity × weighted-average cost, composed directly on top of
  Phase 5's `InventoryService::weightedAverageCost()` rather than re-deriving cost logic. A variant with
  stock but no recorded purchase receipts contributes `0` — its cost is genuinely unknown, not guessed at
  using the sale price or any other stand-in.
- **`deliveryPerformance($deliveryBoyId, $from, $to)`** — delivery count and total earnings paid, directly
  from Phase 8's `delivery_earnings`.
- **`affiliatePerformance($affiliateUserId)`** — clicks, conversions, and commission broken out by
  `approved` vs `pending` status, directly from Phase 7's `link_clicks`/`referral_conversions`.
- **`trialBalance()`** — every active chart-of-accounts row with its live signed balance, via Phase 9's
  `LedgerService::accountBalance()`. Because every journal entry is guaranteed balanced at write time
  (`LedgerService::postEntry()`'s own invariant), this is always a real trial balance, not a cached
  snapshot that could go stale.

## What this phase does not do (explicitly, scope boundaries)

- **No dashboard UI** — this phase delivers the backend query layer; matches every prior phase's pattern.
- **No caching layer** — every call is a live query. For a platform at real scale this may eventually need
  caching (with an explicit invalidation strategy), but adding that speculatively, before real usage
  patterns and query costs are known, risks introducing exactly the kind of stale-number problem this
  phase's "no independent numbers" scope was written to avoid. A natural Phase 16 (Performance
  Optimization) concern once real load data exists.
- **No cross-tenant admin rollups beyond what each method's `null`-seller-id path already provides** — every
  method accepts an optional seller/driver/affiliate id; passing `null` where supported aggregates
  platform-wide. No separate "admin dashboard" endpoint was built on top of this, since the roadmap's
  scope is the read layer itself, not a specific UI's data-fetching pattern.
- **No new CRM-segment-driven analytics** (e.g. "revenue from customers in segment X") — Phase 11's
  `evaluateSegment()` and this phase's `salesSummary()` are both real, composable primitives; wiring them
  together is a natural, bounded follow-up, not built speculatively now.

## Tests

`tests/Feature/Phase12/AnalyticsServiceTest.php` (9 tests): sales summary (revenue/count/average computed
correctly, date-range filtering proven by excluding an out-of-range item, seller-scoping proven with two
sellers); top-selling-products ranking; stock valuation (correct weighted-average composition, and the
zero-contribution case for unpriced stock); delivery performance counting/summing within a date range;
affiliate performance correctly splitting commission by status (approved vs. pending, not just a combined
total); trial balance reflecting a real posted entry's effect on both sides correctly.

Full suite: **287 passing** (278 before this phase), zero regressions. No migration this phase — the
table-count assertion in `MigrationBaselineTest` is unchanged.
