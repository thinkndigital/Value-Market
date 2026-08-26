# Phase 2 — IDOR Sweep & the `generatParcelInvoicePDF` Fix (Tasks 8–9)

## 1. Scope and methodology

This pass targeted the highest-confidence, highest-impact category first: **document-generating endpoints**
(invoice/parcel PDFs) — because a single unscoped lookup there discloses a full record's worth of PII
(customer name, address, mobile number, order contents, pricing) in one response, the worst-case shape of an
IDOR — plus the **known, already-documented** `generatParcelInvoicePDF` bug (`docs/SECURITY_AUDIT.md` §1b,
carried in `docs/TECHNICAL_DEBT.md` since Phase 1 as "documented, not fixed"). Investigating that one bug
led directly to two further real findings via the same route file, which were pursued to conclusion rather
than left half-found. This is **not** a full per-method review of all ~200+ controller methods carrying an
`{id}` parameter — that exhaustive pass remains future work; §4 below hands off a concrete, evidence-based
worklist rather than closing the topic.

## 2. Confirmed and fixed this task

### 2a. `Seller\OrderController::generatParcelInvoicePDF()` — the known bug, now fixed

**Severity: Critical.** Confirmed via direct code reading (not assumed): the initial parcel/order lookup
(`getOrderDetails(['o.id' => $parcels[0]->order_id], false)`) was filtered **only by order id**, with zero
seller scoping, populating the PDF with `$res[0]->uname` (customer name), `->address`, `->mobile_number`
(surfaced into `custom_fields['mobile_number']`), `->payment_method`, `->discount`, and every line item's
product/price/delivery-boy details. Any requester able to reach the route (see 2b — historically, any
authenticated user of any role) could view **any order's full customer and pricing detail** by
guessing/incrementing a parcel id, with no relationship to the order required at all.

**Fix**: before doing anything else, verify the requesting seller owns at least one item in the parcel
(`OrderItems::whereIn('id', $parcel_items->pluck('order_item_id'))->where('seller_id', $seller_id)->exists()`),
`abort(403)` otherwise, `abort(404)` if the parcel doesn't exist at all. "At least one item," not "every
item," because a parcel can span order items from multiple sellers — the existing per-item
`seller_id`-enrichment loop further down the same method already handles that multi-seller case for the line
items themselves; this fix doesn't change that.

`Seller\OrderController::generatInvoicePDF()` (the flat, non-parcel invoice) was also reviewed on the
assumption it shared the same bug. It doesn't: its query (`getOrderDetails(['o.id' => $id, 'oi.seller_id' =>
$seller_id], true)`) already scopes by the **authenticated user's own server-derived** `seller_id` — a
different seller passing an arbitrary order id gets zero matching rows, not another seller's data. Its real
(minor) issues were (a) the same missing `role:seller` route gate as 2b, and (b) a crash
(`Undefined array key 0`) instead of a clean 404 when the scoped query legitimately returns nothing. Both
fixed: `$seller_id` is now unconditionally re-derived from `Auth::id()` (removing the now-provably-unused
branch that would otherwise trust a request-supplied `{seller_id}` route parameter), and an `empty($res)`
guard returns `abort(404)`.

### 2b. Route authorization gaps — `routes/seller_routes.php` and `routes/web.php`

Investigating 2a's reachability surfaced two distinct, real routing bugs:

**`seller.orders.generatParcelInvoicePDF` / `seller.orders.generatInvoicePDF`** (Severity: High) were
registered at the top of `seller_routes.php`, **outside** that file's own `['auth', 'role:seller',
'CheckPurchaseCode']` group. Verified empirically with `route:list -v` (stashing the fix to check the
*original* state, not assumed): `web.php`'s own outer `Route::group(['middleware' => ['auth']], function ()
{ include_once('seller_routes.php'); ... })` wrapper still applied plain `auth` to them — so this was **not**
fully unauthenticated — but `role:seller` was missing. **Any authenticated user of any role** (a customer, a
delivery boy, an unapproved seller account) could reach both endpoints. Fixed by moving both declarations
inside the file's own `auth`+`role:seller`+`CheckPurchaseCode` group, matching every other seller-panel order
route; confirmed post-fix via `route:list -v` showing `Authenticate` + `RoleMiddleware:seller` on both.

**`admin.orders.generatInvoicePDF`** (Severity: Critical) was a genuine duplicate: the *same* route name,
URI, and HTTP method were registered twice — once correctly, inside `admin_routes.php`'s
`['auth', 'role:super_admin,admin,editor', ...]` group (line 541), and again in `web.php` (line 173),
**outside any group at all**. Laravel's `RouteCollection` keys routes by method+URI, so the second
registration silently **replaced** the first in its entirety — `route:list -v`, run before any fix, showed
exactly one entry for this route with `web` as its *only* middleware. **Any unauthenticated visitor could
fetch any order's invoice PDF** (full customer PII) by guessing an order id — no login required at all.
Confirmed, not assumed, by that direct `route:list -v` check. Fixed by deleting the unauthenticated duplicate
in `web.php`; the correctly-gated `admin_routes.php` registration is now the sole one.

A third duplicate in the same neighborhood, `admin.stores.index` (`web.php:174`), was checked and found
low-severity: `Admin\StoreController::index()` returns only a static admin-panel view shell with no store
data (the actual store list loads via a separate endpoint). Left unauthenticated as a minor, deferred finding
(§4) rather than fixed here, to keep this pass's fixes to the confirmed-severe cases.

### 2c. `routes/web.php`'s `include_once()` — a real infrastructure bug, not just a test artifact

Found while writing 2b's regression test: calling `route('seller.orders...')` threw `RouteNotFoundException`,
but *only* when a different test ran first in the same PHPUnit process. Root cause: `admin_routes.php`,
`seller_routes.php`, `delivery_boy_routes.php`, and `front_end_routes.php` were all loaded via
`include_once()`. That function executes a file's top-level code (here, every `Route::` call inside it) only
on its **first** load within a PHP process — a non-issue under traditional PHP-FPM (one process per request,
so "first load" is every request), but a real, silent-route-loss bug under **any persistent-process
deployment model** (Laravel Octane, a long-running queue worker that boots the application more than once)
and under this project's own test suite, which is exactly how it was caught. Fixed: all four `include_once()`
calls changed to `include()`, so every fresh `Application` boot re-registers these routes as intended. Not
a security vulnerability itself, but directly relevant to this phase's verification discipline (Definition
of Done requires the full test suite to actually pass) and worth carrying into `docs/TECHNICAL_DEBT.md` as a
deployment-model risk for whoever picks Octane/queue-worker deployment later.

## 3. Verification performed

- `php -l` clean on every touched file (`Seller\OrderController.php`, `routes/web.php`,
  `routes/seller_routes.php`).
- `tests/Feature/Phase2/ParcelInvoiceOwnershipTest.php` (6 tests): a seller with no items in a parcel is
  denied (403, using real `Order`/`OrderItems`/`Parcel`/`Parcelitem`/`Product`/`Product_variants`/
  `OrderCharges` fixture rows, not mocks); the genuine owning seller is not blocked by that same check
  (execution proceeds past the authorization boundary into PDF rendering, verified by asserting no 403 is
  thrown); a seller with no matching order item gets a clean 404 from the flat invoice rather than crashing;
  and all three affected routes (`seller.orders.generatParcelInvoicePDF`, `seller.orders.generatInvoicePDF`,
  `admin.orders.generatInvoicePDF`) now redirect an unauthenticated request rather than serving a PDF.
- Full Feature test suite: **83 passed (135 assertions)** — up from 77 before this task, zero regressions.
  (This run itself only became reliably possible after the §2c fix — before it, test order affected which
  route-name-dependent tests could even resolve their routes.)
- `php artisan route:list --json` — **1,066 routes**, unchanged (no routes added or removed, only
  re-registered in the correct location/with the correct middleware).

## 4. Confirmed candidates not fixed this task — handoff worklist

Per the master prompt's severity/component/reason/remediation format, for whichever future pass (Task 13's
dedicated route audit, or a continuation of the systematic sweep) picks these up:

| Finding | Severity | Component | Reason not fixed now | Recommended remediation |
|---|---|---|---|---|
| `Seller\MediaController::destroy($id)` deletes any media row (and its underlying file) by id with no seller ownership check | High | `app/Http/Controllers/Seller/MediaController.php:237-243` (found in `docs/PHASE_2_MULTITENANCY.md`, Task 6) | Kept this task's fixes to the two proven, highest-confidence findings (2a/2b) rather than expanding scope mid-task | Same pattern as this task's fixes: `TenantContext::userOwnsSeller()` check before `find()`/`delete()`, `abort(404)` on mismatch |
| `Admin\CronJobController::settleCashbackDiscount()` and `sendCartReminders()` are reachable with **no authentication or permission check at all** (`routes/admin_routes.php:60-61`) — the sibling `settleSellerCommission()` on the same lines correctly has `permissions:edit seller` | Medium | `routes/admin_routes.php:60-61` | Different vulnerability class from this task's scope (unauthenticated trigger of a privileged batch/financial-settlement job and a job that calls paid third-party AI APIs, not per-record ID-guessing data disclosure) — belongs to Task 13's dedicated route security audit, not this IDOR-focused pass | Add `->middleware('permissions:edit seller')` (or an equivalent) to both, matching their sibling; if these are meant to be triggered by an external system cron with no user session, gate them with a shared-secret query param or move them behind Laravel's `schedule:run` instead of a public URL |
| `admin.stores.index` (`web.php:174`) is reachable unauthenticated | Low | `routes/web.php:174` | Confirmed low-impact (returns only a static view shell, no store data) - not worth the risk/attention budget alongside the two critical fixes above | Move inside `admin_routes.php`'s auth group for consistency, at low priority |
| Every "not independently audited this task" row in `docs/PHASE_2_MULTITENANCY.md` §4 (`seller_store`, `seller_commissions`, `order_charges`, `combo_products`, `favorites`, `images`) | Unknown - not yet assessed | Various `Seller\*Controller` classes | Exhaustive per-method review of ~200+ controller methods is explicitly out of this task's bounded scope | Continue the same method: grep each table's controllers for `::find($id)`/`::where('id', $id)` lookups not scoped by `seller_id`/`user_id`, verify with a direct read, fix with `TenantContext` + a regression test |

This table is the honest state of play: two critical, evidence-verified, real vulnerabilities were found and
fixed end-to-end (code + routing + regression tests), a real infrastructure bug was found and fixed as a
side effect of verifying the tests actually pass, and everything else surfaced along the way is named
explicitly rather than silently dropped.
