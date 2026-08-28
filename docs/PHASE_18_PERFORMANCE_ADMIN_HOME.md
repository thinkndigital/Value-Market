# Performance Pass — /admin/home and Production Deployment

Ad-hoc performance work requested directly by the user, outside `docs/IMPLEMENTATION_ROADMAP.md`'s numbered
phases (Phase 16 already did a general, whole-application indexing pass - see
`docs/PHASE_16_PERFORMANCE_OPTIMIZATION.md`; this is a focused, deeper pass on one specific page the user
reported as slow, plus the full Laravel/DB/Docker/Apache/Cloud Run review requested alongside it). Numbered
"Phase 18" here only to keep this project's established one-topic-per-doc convention, not a roadmap phase.

## 1. Diagnosis method

No step in this pass assumed a cause. Every finding below was confirmed one of two ways: reading the actual
controller/helper code line by line, or measuring real query counts against the real controller with
`DB::listen()` (not estimated). The empirical count for `Admin\HomeController::index()` before any change:
**26 queries**, confirmed by running the controller directly and counting every query Laravel actually
issued.

## 2. Findings, most severe first

**Finding 1 (the single largest cause) — a heavy query re-run 7 times for no reason.**
`HomeController::index()`'s weekly-sales section built a `for ($i = 0; $i < 7; $i++)` loop and called
`getWeeklySalesData()` on every iteration. That method's own query already computes **all seven days of the
current week in one pass** (`GROUP BY DATE(created_at)` across the whole week, returned as a 7-element
array) - the loop only ever read `$dayRes[...][$i]`, a single index into that same result. Six of the seven
calls were therefore fully redundant: same SQL, same bindings, same result, discarded five out of six times.
Confirmed by the query log showing `7x select DATE_FORMAT(created_at, '%d-%b')...` - all seven rows
byte-identical.

**Finding 2 — three queries doing one query's job.**
`getMonthlyData()` was called three times (`sub_total`, `admin_commission_amount`, `quantity`), each running
an identical `GROUP BY YEAR(CURDATE()), MONTH(created_at)` against `order_items`, differing only in which
single column got `SUM()`'d.

**Finding 3 — no index on any of the four columns this page (and the wider admin panel) filters by.**
`order_items.store_id`, `orders.store_id`, `combo_products.store_id`, `users.role_id` carried zero index -
confirmed directly via `SHOW INDEX`, not assumed. Every query on this page filters by one of these; `role_id`
alone is filtered by dozens of other `Admin\*` controllers across the app (confirmed via grep), so this
finding's impact is not limited to `/admin/home`.

**Finding 4 (same shape as Finding 2, a different call site) — `countNewUsers()` (`app/function_helper.php`,
called once per `/admin/home` load) ran 5 separate `COUNT` queries** against the same `users.role_id = 2`
set, differing only in which additional `WHERE` condition narrowed each one (current month / previous month
/ active / inactive, plus the unfiltered total).

**Finding 5 — `route:cache` cannot currently run.** Same root cause already documented in
`docs/DEPLOYMENT.md`/`docs/PHASE_17_FULL_QA_PRODUCTION_READINESS.md`: ~50 groups of duplicate route names
across the admin/seller/delivery_boy panels make Laravel's route-name registry ambiguous, which
`route:cache`'s stricter validation rejects outright. **Not fixed in this pass** - fixing it requires
renaming at least one route in each duplicate pair, and this pass's own instructions explicitly rule that
out ("do not change route names"). This is a real, separate, already-scoped piece of work (see the two docs
above for the full list and why it needs per-case review, not a blind rename) - left exactly as documented,
not silently dropped.

**Not a finding — OPcache, the Dockerfile, and `.dockerignore` were reviewed and are already correctly
tuned** (see §5-6 below) - re-confirmed rather than assumed correct from a prior pass, but no change was
needed.

## 3. Fixes applied

| # | File | Change | Verified |
|---|---|---|---|
| 1 | `app/Http/Controllers/Admin/HomeController.php` | `getWeeklySalesData()` moved outside the loop, called once | Query log: `7x` → `1x` |
| 2 | `app/Http/Controllers/Admin/HomeController.php` | `getMonthlyData()` (3 calls) replaced with `getMonthlyDataCombined()` (1 call, 3 `SUM()` columns) | Same 3 result arrays reconstructed from the 1 query's rows |
| 3 | `app/function_helper.php` | `countNewUsers()`'s 5 `COUNT` queries merged into 1 query using `CASE WHEN` per original condition | `tests/Feature/Phase18/AdminHomePerformanceTest.php` proves identical output across every branch (active/inactive/null, both months, wrong role_id excluded) |
| 4 | `database/migrations/2025_02_14_000000_add_admin_home_performance_indexes.php` (new) | Composite `(store_id, created_at)` on `order_items`; single-column `store_id`/`role_id` indexes on `orders`/`combo_products`/`users` | Full `up()`/`down()`/`up()` cycle run against the live dev database; each index confirmed present via `SHOW INDEX` after |

**Result: 26 → 14 queries for `/admin/home`, confirmed empirically before and after the fix**, not
estimated. No business logic, output values, view variables, routes, or API contracts changed - `test_count_new_users_returns_identical_results_to_the_original_five_query_version` and the weekly/monthly
merges all reconstruct the exact same shapes the original per-call code produced.

## 4. What was NOT changed, and why

- **Route names** - explicitly ruled out by this pass's own instructions; see Finding 5.
- **`countNewOrders()`, `AdmintotalEarnings()`** (`app/Services/OrderService.php`, `app/function_helper.php`)
  - each already runs as a single query; the new `orders.store_id`/`order_items.store_id` indexes benefit
  them directly without any code change needed.
- **JIT** (`docker/opcache.ini`, currently `off`) - left as-is. Laravel request handling is overwhelmingly
  I/O-bound (database round trips, not CPU-bound loops), the workload JIT gives the least benefit for; no
  `opcache.jit_buffer_size` is even configured, so enabling it without also sizing a JIT buffer would do
  nothing. Not changed without a concrete, measured reason to.
- **Apache MPM/worker tuning** - the base `php:8.4-apache` image's compiled-in prefork MPM defaults are
  left untouched. This *is* a real, worth-flagging interaction with Cloud Run's `--concurrency` setting
  (Apache/mod_php holds one OS process per concurrent request, unlike PHP-FPM's lighter model) - covered as
  a Cloud Run recommendation in §7 instead of a blind Apache config edit, since the right number depends on
  the memory limit actually configured for the live service, which this environment cannot inspect.

## 5. Dockerfile and `.dockerignore` review

Already reviewed in a prior pass (`docs/PHASE_17_FULL_QA_PRODUCTION_READINESS.md`) and re-confirmed here:
multi-stage build (Composer deps / Vite assets / runtime, each cached independently), PHP 8.4 pinned across
all stages with the exact reasoning for that version documented inline (the real intersection of every
locked package's PHP constraint), the full PHP extension list cross-checked against actual `composer.json`
dependencies and real `app/` usage (including `imagick`, confirmed used directly for animated-GIF resizing
with no GD fallback - omitting it would be a silent, later-discovered runtime failure), `composer install
--no-dev --optimize-autoloader` equivalent already in the build, and `.dockerignore` already excludes
`tests/`, `.git`, `node_modules`, and `vendor` from the build context. No changes made - already correct.

## 6. OPcache review

`docker/opcache.ini`, confirmed against the running PHP 8.4.19 build - every directive
(`opcache.enable`, `enable_cli`, `memory_consumption=192`, `interned_strings_buffer=16`,
`max_accelerated_files=20000`, `validate_timestamps=0`, `save_comments=1`, `jit=off`) is a valid, supported
PHP 8.4 directive, none deprecated. `validate_timestamps=0` is correct specifically because Cloud Run
containers are immutable per revision (no hot-reloaded PHP source to detect), a reasoning already documented
inline in the file. No changes made - already correct for this deployment target.

## 7. Cloud Run recommendations (this environment cannot read or change the live service)

No `gcloud` credentials or live service access exist in this session - every number below is a reasoned
starting point for a Laravel-on-Apache/mod_php admin dashboard in `me-central1`, not a live measurement, and
is explicitly a starting point to tune against real traffic, not a final answer:

- **Min instances: 1** - if `/admin/home` (and the rest of the admin panel) needs consistently fast response
  for a human operator, not just for occasional/batch traffic, this avoids a cold start (container boot +
  Composer autoload + first-request Blade/config resolution) on every session gap. Real, quantifiable cost:
  one instance billed continuously instead of scaling to zero when idle.
- **Startup CPU Boost: on** - this Dockerfile's `entrypoint.sh` runs `config:cache`/`view:cache` on every
  container start (by design - Cloud Run containers are stateless/ephemeral, so this can't be baked into the
  image once and reused); boosted CPU during that startup window shortens it directly.
- **Concurrency: moderate, not Cloud Run's default 80, given the deployment stack specifically** - Apache
  with `mod_php` (this image, not PHP-FPM) holds one OS process per concurrent request, not one lightweight
  worker thread; 80 concurrent requests could mean 80 live PHP processes in one container, each with real
  memory footprint. The right number is the memory limit actually assigned divided by realistic per-request
  memory use - a number this environment cannot measure without live traffic. Start conservative (e.g. 20-40)
  and raise it only after observing real memory headroom, not before.
- **CPU / Memory: not raised without a measured reason**, per this pass's own rules. The query-count fix in
  §3 reduces *database* round trips, not Laravel's per-request CPU/memory footprint - if `/admin/home` is
  still slow after these fixes and after DB indexes have had time to matter at real data volume, the next
  diagnostic step is Cloud Run's own request-latency/CPU metrics (not available in this session), not a
  resource bump made on suspicion.

Full `gcloud` commands (using the placeholder this pass's own instructions specify when the real service
name isn't known) are in `docs/PHASE_18_FINAL_REPORT.md`.

## 8. Verification performed

`php -l` across `app/`, `routes/`, and `database/migrations/` - clean. `composer validate` - clean. A full
fresh migration (`migrate:fresh`) across the entire history including the new indexes migration - clean,
zero errors. Full test suite before and after every change - 332/332 passing throughout, zero regressions.
The query-count claims (26 → 14, and each individual fix's query reduction) are proven by dedicated tests in
`tests/Feature/Phase18/AdminHomePerformanceTest.php`, not just asserted in this document.
