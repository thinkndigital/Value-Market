# Phase 20.1 — admin dashboard's inconsistent "no store selected" scope

## Context

Follow-up to `docs/PHASE_20_DASHBOARD_RENDERING_BUG.md`, which flagged (with a wrong assumption) that the
Orders Overview card showed illogical numbers "on an empty test database." Investigating properly:

The dev database is not empty - it holds 145,589 `order_items` rows across 3 stores, seeded for Phase 19's
own `/admin/home` query-profiling work (`docs/PHASE_19_ADMIN_HOME_QUERY_PROFILING.md`; store 1 alone carries
~17% of the table, matching that doc's own "the measured store takes ~17%, not 100%" methodology note). Store
1 has real matching sellers/products (12 sellers, 600 products); stores 2 and 3 only have bulk `order_items`
rows with no matching sellers/products, deliberately, to give EXPLAIN a realistic multi-tenant table shape.

Confirmed by direct query: **none of the 3 stores has `is_default_store = 1`**.

## Root cause

`is_default_store` is never set automatically anywhere in this codebase - grepped every write site
(`app/Http/Controllers/Admin/StoreController.php`), and it's only ever flipped by an admin explicitly
checking a box when creating/editing a store, or via the dedicated `admin/store/set_default_store/{id}`
toggle route. Nothing marks the *first* store a fresh install creates as the default.

`App\Http\Middleware\SetDefaultStore` (registered in the global `web` middleware group -
`app/Http/Kernel.php`, so this runs for every request, storefront included, not just the admin panel) tries
to seed `session('store_id')` from `Store::where('is_default_store', 1)->where('status', 1)->first()`. With
no store ever flagged, that query always returns null, and `session('store_id')` stays permanently empty
(`''`) for every visitor and every admin, forever, on any install where nobody happened to flip that toggle -
which is the common case, since it isn't part of any onboarding/setup flow.

Downstream code then disagreed on what an empty store scope means, producing the dashboard's contradictory
numbers:
- `Admin\HomeController::index()`'s `total_seller`/`total_products`/`total_earnings` queries do
  `->where('store_id', $store_id)` - with `$store_id = ''`, this matches nothing (an empty string against an
  integer column never equals a real store id), so these cards correctly-by-accident show `0`.
- `OrderService::ordersCount()` explicitly guards its store filter with `if (!empty($store_id))` - with
  `$store_id = ''`, `empty('')` is `true`, so the guard is false and the filter is skipped entirely, silently
  summing **every** store's order counts together instead of scoping to none or to one.

Net effect: "0 sellers, 0 products" sitting next to "29,646 delivered orders" on the exact same dashboard
load, for the exact same (unresolved) store scope.

## Fix

`app/Http/Middleware/SetDefaultStore.php`: when no store is flagged `is_default_store = 1`, fall back to the
earliest active store (`Store::where('status', 1)->orderBy('id')->first()`) instead of leaving the session
store id empty. An explicitly-flagged default store is still preferred whenever one exists; the fallback only
engages when nothing has ever been configured. This keeps every request store-scoped by something real,
matching what the rest of the codebase already assumes "the current store" resolves to, and fixes both the
admin dashboard's contradictory numbers and (since this middleware is global, not admin-only) the same
unresolved-scope gap on the public storefront.

Deliberately not touched: the `is_default_store` database flag itself. Auto-assigning it via a migration
would be a real, user-visible product decision (which store becomes the platform's "main" one) on someone's
live data, not something to decide silently on their behalf - the middleware-level runtime fallback achieves
the same practical outcome (every session gets a real store) without touching what an admin may have
deliberately left unconfigured. Also not touched: `OrderService::ordersCount()`'s own empty-store-id
"skip the filter, go global" behavior - once the middleware fix means real requests essentially never hit
that path anymore, changing a shared method other callers may intentionally rely on for a genuinely-global
count felt like more risk than the actual bug warranted.

## Verification

`tests/Feature/SetDefaultStoreFallbackTest.php` (new, 3 tests): the fallback picks the earliest active store
when none is flagged default; an explicitly-flagged default store is still preferred over the fallback when
one exists; a default-flagged but inactive (`status != 1`) store is correctly skipped in favor of the
fallback. Full suite: 386/386 passing (was 383; +3 for this phase).
