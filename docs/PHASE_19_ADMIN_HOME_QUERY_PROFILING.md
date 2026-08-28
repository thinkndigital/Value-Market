# /admin/home — Real Execution-Time Profiling (Phase 19)

Follow-up to `docs/PHASE_18_PERFORMANCE_ADMIN_HOME.md`, which cut `/admin/home` from 26 to 14-16 queries by
removing redundant calls inside `HomeController::index()`. This phase asks a different question: not "how
many queries", but **where does the real response time go** — measured, not estimated, and specifically
including the one thing Phase 18's methodology never saw: Blade rendering itself.

All Phase 18 fixes are kept as-is; nothing here reverts them.

## 1. Methodology

A new diagnostic tool, `app artisan perf:admin-home` (`app/Console/Commands/ProfileAdminHome.php`), was
built for this pass and kept in the repo as a reusable instrument (not a one-off script) since the same
question — real time, not query count — will come up again for other pages.

What it does, precisely:
- Registers **one** `DB::listen()` for the whole process, tagging every query with whichever phase is
  currently running (`bootstrap` / `controller` / `render` / `middleware`) — a single shared listener,
  because `DB::listen()` has no "unlisten": registering a fresh one per phase silently double/triple-counts
  every later phase into the earlier ones' totals (a real bug hit and fixed while building this).
- Times `HomeController::index()` directly (`microtime(true)` around the call) — this is what Phase 18's own
  test (`tests/Feature/Phase18/AdminHomePerformanceTest.php`) measured, and only that.
- Then separately times `$view->render()` — the actual Blade compile+render pass, which **fires its own
  queries** (view composers, anything a template calls inline). Phase 18's methodology never called
  `->render()`, so it never saw these at all.
- Times the three route-group middleware `/admin/home` actually carries (`role:...`, `CheckPurchaseCode`,
  `CheckStoreNotEmpty`), invoked directly in-process (not through the full HTTP Kernel — dispatching a
  second `Request::create()` through the Kernel starts a **disconnected** session via `StartSession`,
  breaking the `Auth::login()`/`store_id` state already set up; the middleware chain's own `handle()` methods
  are timed directly instead, with the same session/auth state, which is the part that's actually specific
  to this route).
- With `--explain`, re-runs every distinct captured `SELECT` through `EXPLAIN`, with real bound values
  substituted for `?` placeholders.
- With `--seed --rows=N --other-rows=M`, seeds `N` order/order_items rows for one store plus `M` rows for
  every *other* existing store — necessary because a **single-store** seed makes `store_id` match 100% of
  the table, which makes MySQL correctly (not buggily) skip the `store_id` index in favor of a full scan.
  That's a property of the benchmark data, not evidence the index doesn't help in a real multi-tenant
  deployment. All EXPLAIN conclusions below are from a run seeded to **145,589 order_items rows across 3
  stores**, with the profiled store holding ~17% of the table (25k of 145k) — a deliberately unfavorable,
  realistic minority share, not a best case.

Run: `php artisan perf:admin-home --seed --rows=25000 --other-rows=60000 --explain` (one-time seed), then
`php artisan perf:admin-home [--explain]` to re-measure against the same data.

## 2. Baseline (before this phase's fixes)

Averaged over 3 runs against the 145,589-row / 3-store dataset, current dev DB:

| Metric | Value |
|---|---|
| Total queries (bootstrap + middleware + controller + render) | 83 |
| `HomeController::index()` alone | 18 queries, ~433 ms wall |
| Blade render alone | **54 queries**, ~669 ms wall |
| Total DB time (sum of all query times) | ~824 ms |
| Total reconstructed wall time | ~1,109 ms |
| Non-DB (PHP + template + middleware logic) time | ~284 ms |

**The controller was never the real story.** Rendering the page fired *more* queries (54) than the
controller itself (18), and cost more than the controller's own 433 ms — invisible to Phase 18's
`app(HomeController::class)->index()`-only measurement, because it never rendered the view.

### 2.1 Where the render-phase queries came from

`resources/views/admin/pages/forms/home.blade.php` calls `app(OrderService::class)->ordersCount($status,
'', '', $store_id)` **24 times inline**, directly in the template — a real query every time:

| Status argument | Times called | Where |
|---|---:|---|
| `received` | 5 | value display, `$current...Order` variable, `aria-valuenow` (x2, one of them a pre-existing copy-paste bug reusing `received` on a different status block — not touched, see §4) |
| `processed` | 3 | same three spots |
| `shipped` | 3 | same |
| `delivered` | 3 | same |
| `cancelled` | 2 | value display + `$currentRecivedOrder` (reused variable name) |
| `returned` | 2 | same |
| `''` (all statuses / "max value" for the progress-bar width) | 6 | once per status block, **identical query every time** |

That's 6 named statuses + 1 "all" total = **7 distinct (status, store_id) results actually needed**,
computed 24 times.

### 2.2 The other dominant cost: `top_sellers`

`HomeController::index()`'s `top_sellers` block eager-loaded **every** `order_items` row (`seller_id,
sub_total, seller_commission_amount, active_status` — no `store_id` filter, no date bound) for every seller
of the store, then summed `sub_total`/`seller_commission_amount` **in PHP** via `Collection::sum()`. At
scale this is both a large row transfer (25,006 rows matched a 12-seller `IN` clause in the seeded data) and
slow PHP iteration for something one `GROUP BY` already computes. Single query time: **57.63 ms** — the
single slowest individual query measured in the controller phase — plus real (unmeasured-by-query-time) PHP
summation cost on top of it.

## 3. Is the problem Database, Laravel/PHP, External API, or Rendering?

**Overwhelmingly Database** — confirmed by measurement, not assumed:

- Total DB time / total wall time ≈ **824 / 1,109 ≈ 74%** of the baseline's total response time.
- No external HTTP/API calls exist anywhere in `HomeController.php` or `home.blade.php` (grepped for
  `Http::`, `curl_`, `file_get_contents`, Guzzle — none found). External API is not a factor for this page.
- "Rendering" as a *template-engine* cost (Blade compiling/echoing) is small (~25 ms non-DB time even in the
  before state) — what looked like a rendering problem was actually 54 **database queries** triggered
  *during* rendering, not the templating engine itself being slow.
- The remaining ~26% (PHP-only time) was real and traced to one specific cause (top_sellers' PHP-side
  `Collection::sum()` over thousands of rows — §2.2), not general PHP/Laravel framework overhead.

## 4. Fixes applied

### 4.1 `ordersCount()` de-duplication (24 calls → 7)

**File:** `app/Http/Controllers/Admin/HomeController.php` — computes all 7 distinct results once, into
`$orders_status_counts`, passed to the view.
**File:** `resources/views/admin/pages/forms/home.blade.php` — all 24 call sites replaced with array lookups
(`$orders_status_counts['received']`, etc.), each replacement substituting the *exact* status literal that
call site already used — including the pre-existing bug where two `aria-valuenow` attributes (in the
`cancelled` and `returned` blocks) reused the `received` count instead of their own status. That bug is
**not part of this performance pass** and was deliberately left exactly as it was: fixing it would change
what the page displays, which the task's own rules exclude ("لا تغير Business Logic", "لا تغير UI").

### 4.2 `top_sellers` — SQL aggregate instead of PHP summation

**File:** `app/Http/Controllers/Admin/HomeController.php` — replaced the eager-load of every raw
`order_items` row with one `OrderItems::whereIn('seller_id', $sellerIds)->selectRaw("seller_id, SUM(CASE
WHEN active_status = 'delivered' THEN sub_total ELSE 0 END) as total_sales, SUM(seller_commission_amount) as
total_commission")->groupBy('seller_id')` query, keyed by `seller_id`, then mapped against the (small,
already-cheap) list of the store's sellers. Same two sums, same "no store_id filter, seller's order_items
from any store all get summed together" semantics as the original code (preserved exactly, not "fixed" —
changing it would change what the page displays), same sort-by-`total_sales`-desc-take-6. Proven identical
in `tests/Feature/Phase19/AdminHomeQueryProfilingTest.php`.

### 4.3 Two EXPLAIN-verified indexes on `order_items`

Both verified by creating the candidate index directly via SQL, re-running `EXPLAIN`, and confirming the
query plan actually changed — **before** writing the migration, per the task's own rule. A candidate that
did **not** change the plan (a `(seller_id, active_status(50), sub_total, seller_commission_amount)`
covering index for the `top_sellers` aggregate) was tested and **rejected** — MySQL kept choosing the
existing `seller_id` index regardless, so it was not added. Not every plausible index helps; this is the
proof either way.

**`database/migrations/2025_02_15_000000_add_order_items_financial_covering_index.php`** — new composite
`(store_id, created_at, sub_total, admin_commission_amount, quantity)`, replacing Phase 18's
`(store_id, created_at)` index (a strict left-prefix subset of the new one, so keeping both would only add
write-time cost for zero read benefit — dropped in the same migration).

| Query | Before | After |
|---|---|---|
| `AdmintotalEarnings()` | `type: ALL`, 143,589 rows scanned | `type: ref`, `Using index` (index-only, no bookmark lookups) |
| `getMonthlyDataCombined()` | `type: ALL`, "Using temporary; Using filesort" | `type: ref`, `Using index; Using temporary; Using filesort` (aggregation still needs a sort, but no longer touches the base table) |
| `getWeeklySalesData()` | `type: ALL` | `type: ref`, `Using index` |

**`database/migrations/2025_02_16_000000_add_order_items_status_count_index.php`** — new
`(store_id, active_status(50), order_id)` (a prefix length matching the existing
`order_items_active_status_prefix_index` convention — `active_status` is a legacy `varchar(1024)`, too wide
for a full-column composite index within InnoDB's 3072-byte key limit).

| Query (`ordersCount()`) | Before | After |
|---|---|---|
| Status-specific (e.g. `active_status = 'delivered'`) | `type: ALL`, 143,796 rows | `type: ref`, ~12,682 rows |
| "All statuses" total | `type: ALL`, 143,796 rows | `type: ref`, ~52,774 rows (still large — `active_status != 'awaiting'` matches most rows — but real narrowing, not a full scan of every store's data) |

## 5. PHP loops / Blade loops / external calls — explicitly checked

- **PHP loops after DB retrieval** (task item 10): the only one that mattered was §2.2 (`top_sellers`),
  fixed. `HomeController::index()`'s other loops (the 7-day week-name loop, the 12-month array merge, the
  30-day date-fill loop) iterate small, fixed-size arrays (≤31 elements) — negligible, not touched.
- **Blade rendering loops** (task item 11): `home.blade.php` itself has **no** `@foreach`/`@for` over a
  data collection — the "loop" cost was the 24 inline service calls (§2.1, fixed), not template iteration.
- **External HTTP/API calls during page load** (task item 12): none exist in this controller or view
  (grepped, confirmed empty).

## 6. What was NOT touched, and why

- **`SetDefaultStore` middleware** runs a `Store::where('is_default_store', 1)->where('status',
  1)->first()` query on *every* web request (not just `/admin/home`) regardless of whether the session
  already has a `store_id`. Real, but site-wide (not this page's problem) and tied to session/store-resolution
  logic a prior security pass (docs/SECURITY_AUDIT.md §6.4) already reasoned about carefully — out of scope
  for a page-specific performance pass.
- **`select * from permissions` (x10) during render**: cheap (~2 ms total for all 10, confirmed by
  measurement) — not worth the risk of touching Spatie permission-check code for a sub-3ms gain.
- **Business logic, UI, routes, auth, permissions, API contracts**: unchanged. Every fix here is either a
  cache-the-repeated-call refactor (§4.1) or a compute-the-same-sum-in-SQL-instead-of-PHP refactor (§4.2),
  both proven output-identical by tests, or an additive index (§4.3).
- **Dockerfile / Apache / OPcache / Cloud Run**: not touched — nothing measured here pointed at them (§3
  confirms the bottleneck was the database layer, not the request-serving layer), matching the task's own
  rule not to touch these without clear evidence.

## 7. What this measures, and what it doesn't

Every number in this document is the **Laravel/PHP/MySQL layer only**, measured in-process on this dev
container. It does **not** include: Apache/PHP-FPM process overhead, TLS handshake, network round-trip, or
Cloud Run cold starts — none of those can be measured without a real request against the deployed Cloud Run
service, which this environment has no access to. Given the database layer accounted for ~74% of the
measured time and none of today's fixes touch the request-serving layer, the *relative* improvement
(§8) should carry over to production, but the *absolute* numbers will differ — that needs a real Cloud Run
measurement to confirm, not a guess from this environment.

## 8. Before / After

Same methodology, same 145,589-row / 3-store dataset, both measured with `php artisan perf:admin-home`
(averaged over multiple runs each; see §9 for the exact command).

| Metric | Before | After |
|---|---:|---:|
| Total queries (bootstrap + middleware + controller + render) | 83 | 66 |
| Database time (sum of query times, all phases) | ~824 ms | ~165 ms |
| Slowest single query | `top_sellers` eager-load, 57.63 ms (controller phase) — closely followed by the redundant `ordersCount('')` "all statuses" full scan at ~53 ms/call × 6 identical calls = 319 ms combined (render phase) | `getMonthlyDataCombined()`, ~15 ms (now index-only; the former #1 and #2 are both fixed) |
| Total reconstructed wall time | ~1,109 ms | ~204 ms |
| PHP-only time (wall − DB) | ~284 ms | ~39 ms |

**~81% reduction in total measured response time (≈5.4x faster)**, same displayed values (proven by
`tests/Feature/Phase19/AdminHomeQueryProfilingTest.php`), same business logic, same UI, same routes.

## 9. Reproducing this

```
php artisan perf:admin-home --seed --rows=25000 --other-rows=60000   # one-time: seed 145k rows, 3 stores
php artisan perf:admin-home --explain                                 # re-run any time, same data
```

## 10. Verification

- `php artisan test` (full suite): 352 passed, including 4 new Phase 19 tests proving `orders_status_counts`
  matches direct `ordersCount()` calls, rendering fires exactly 7 (not 24) `ordersCount()` queries,
  `top_sellers` totals match the original PHP-side sum semantics exactly, and `top_sellers` runs one `GROUP
  BY` query instead of pulling every matching row into PHP.
- `tests/Feature/Phase18/AdminHomePerformanceTest.php`'s controller-only query-count guard updated (16 → 23)
  with an explanation: 7 queries that used to run *during rendering* (invisible to that test) now run in the
  controller instead — a real net reduction (24 render queries → 7, now counted here) that looks like a
  controller-only regression from that test's narrow lens alone.
- Both new migrations verified with a full `up()` → `down()` → `up()` cycle and a clean
  `migrate:fresh` on the test database.
- `php -l` on every touched PHP file.
