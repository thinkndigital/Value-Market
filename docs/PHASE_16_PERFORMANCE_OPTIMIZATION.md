# Phase 16 — Performance Optimization

## 1. Scope and what "real query patterns" means without production traffic

`docs/IMPLEMENTATION_ROADMAP.md`'s Phase 16 description: *"Indexing, caching, queue tuning, reporting-query
optimization once real query patterns are visible."* This application has no production deployment and no
APM/slow-query-log history yet (confirmed earlier in this session's own scoping - see
`docs/PHASE_14_AI_ANALYTICS_LAYER.md` for the parallel case of a phase whose ideal input doesn't exist yet).
Rather than either skip the phase entirely or guess speculatively, "real query patterns" was interpreted the
one way it can be satisfied honestly right now: patterns confirmed real by reading the actual application
code (not by guessing what *might* be slow) and confirmed unindexed by inspecting the live database schema
directly (`SHOW INDEX`, not assumed). Every change below traces to a specific, real, currently-executing
query shape, cross-checked against the schema before being touched - the same "verified, not assumed"
discipline `docs/SECURITY_AUDIT.md` §2 established for Phase 1.

What this phase explicitly could not do without production visibility: choose what to cache (caching the
wrong thing, or caching something that goes stale in a way nobody notices, is worse than not caching), or
tune queue workers/concurrency (there is no queue load to observe). Both are named in the roadmap and both
are deferred here, honestly, rather than filled in with a guess dressed up as a decision - see §3.

## 2. Indexing (`database/migrations/2025_02_13_000000_add_performance_indexes.php`)

Two independent checks were required before any column was indexed: (a) direct schema inspection
(`SHOW INDEX FROM <table>`) confirming no index already covers it, and (b) at least one real call site in
this application's own code (not a hypothetical one) filtering by exactly that column or column
combination.

| Table | Index added | Confirmed unindexed via | Confirmed queried via |
|---|---|---|---|
| `order_items` | `seller_id` | `SHOW INDEX` (only PK + user_id/order_id/product_variant_id existed) | Dozens of `OrderItems::where('seller_id', ...)` call sites across Phases 2-15's own Seller-panel controllers |
| `order_items` | `active_status` (50-char prefix - see below) | same | `AnalyticsService::salesSummary()`/`topSellingProducts()` (Phase 12), plus the broader delivered/cancelled/returned status-filter pattern used throughout the app |
| `order_items` | `(seller_id, active_status)` composite | same | The exact combined shape `AnalyticsService::salesSummary()` and most Seller-panel order-status list queries use |
| `products` | `seller_id` | `SHOW INDEX` (only PK + category_id existed) | Every seller product listing/ownership check, including this session's own `PurchaseOrderController` variant-ownership fix (Phase 15, Finding 1) |
| `orders` | `channel` | `SHOW INDEX` (only PK + user_id existed) | Added by this session's own Phase 3 specifically as a report-filter dimension, but the migration that introduced the column didn't index it |
| `referral_conversions` | `(order_id, status)` composite | Only single-column `order_id`/`affiliate_link_id` indexes existed (Phase 7's own migration) | Every read this session's Phase 7/15 `AffiliateService` code does (`recordConversion`, `approveConversionsForOrder`, `reverseConversionsForOrder`) filters by exactly this pair |
| `pos_payments` | `(pos_shift_id, payment_method)` composite | Only single-column `order_id`/`pos_shift_id` indexes existed (Phase 6's own migration) | `PosShiftService::close()`'s cash-sum query filters by exactly this pair on every shift close |

**Why `order_items.active_status`/`status` need a prefix index, not a full-column one:** both columns are
`varchar(1024)` (inherited eShop Plus schema, not something this session introduced). Under this table's
`utf8mb4_unicode_ci` collation (4 bytes/char), a full-column index would need up to 4096 bytes per entry -
over InnoDB's maximum key-prefix length (3072 bytes, even with `innodb_large_prefix` enabled, which MariaDB
10.11 defaults on). Every value actually stored is a short status word (`delivered`, `cancelled`,
`return_request_approved`, ...), so a 50-character prefix index covers every real value with room to spare
while staying safely under the limit.

**Safety**: adding a secondary index is purely additive - it cannot change what any query returns, only how
fast it's found (at the standard, well-understood cost of slightly slower writes and more disk space).
MariaDB's default InnoDB online-DDL mode (`ALGORITHM=INPLACE`) permits concurrent reads and writes while a
secondary index is being built, so this is safe to run at any table size, including against live data later.
Verified: a full `up()` → `down()` → `up()` cycle runs cleanly with no errors and reaches the identical
index set each time (an initial typo in `down()`'s index name for `pos_payments` was caught by exactly this
verification step, before it shipped - see the migration's own commit history for the fix).

## 3. N+1 query fix

`AffiliateService::approveConversionsForOrder()`/`reverseConversionsForOrder()` (Phase 7/15) loop over every
conversion for an order and read `$conversion->link->user_id` - without eager-loading, each row fired its
own `AffiliateLink` query. In practice one order rarely has more than a couple of conversions, so the real-
world impact is small, but the fix (`ReferralConversion::with('link')->...`) is free, zero-risk, and closes
what would otherwise scale badly for a large multi-vendor cart. Proven with an actual query-count assertion
(`tests/Feature/Phase16/AffiliateServiceQueryCountTest.php`), not just a code-review claim that `->with()` is
present - one `affiliate_links` query for 3 conversions on the same order, not three.

No other genuine N+1 was found in the Phase 4-14 code this session wrote (the code this session has full
context on to fix safely). The pre-existing legacy services (`OrderService`, `ProductService`,
`ComboProductService`, `ParcelService`, `CartService` - each several hundred to several thousand lines) were
grepped for the same loop-plus-lazy-relation-access shape and none were found in the parts actually read;
a full audit of ~14,000+ lines of pre-existing legacy business logic this session did not write is out of
scope for the reason given in §4.

## 4. Explicitly deferred: caching and queue tuning

Not attempted, named here rather than silently dropped. Caching requires knowing which reads are (a) hot
enough to matter and (b) safe to serve slightly stale - both are judgment calls that need real traffic data
or at minimum a human's product-level call about acceptable staleness per endpoint (e.g., is a seller okay
seeing their dashboard revenue number lag by a minute?). Guessing this without that input risks either
caching nothing that mattered (wasted complexity) or caching something whose staleness surprises a real
seller/admin in a way that erodes trust in the numbers - a worse outcome than not caching. Queue tuning has
an even harder version of the same problem: there is no queue load in this dev environment to observe or
tune against. Both are reasonable, concrete Phase 17/production-readiness follow-ups once the application
has real traffic or at least a realistic load-test to observe.

## 5. Legacy full-application performance audit

Also explicitly out of scope. This session has deep, current context on Phases 1-15's own code (it wrote
it) but not on the pre-existing ~14,000+ line legacy API controllers and services this multi-phase project
has deliberately built alongside rather than rewritten (the same boundary every phase's own final report has
maintained: extend and fix what's touched, don't undertake an unscoped rewrite of everything else). A full
legacy performance sweep is real, valuable work - but it's its own dedicated phase, not a few hours
appended to this one while unsupervised.

## 6. Verification

`php -l` on every touched file. Migration verified with a full up/down/up cycle against the live dev
database (not just `RefreshDatabase` in tests, which only proves it works once from empty). Full test suite
run after every change, not batched to the end - 318 → 320 passing, zero regressions. The N+1 fix has a
dedicated query-count regression test, not just a `->with()` presence check.
