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

### 2d. Task 10 — Address security re-verification and pattern search

Re-verified: `Admin\AddressController`'s `store()`/`destroy()` ownership checks (the Phase 1 fix) are still
correct and still covered by `tests/Feature/Phase1/AddressOwnershipTest.php`; no regressions from this
phase's changes.

Searching for the same "look up an Address purely by an id the caller controls, no ownership check"
shape elsewhere found it in every file touching the `Address` model. Two clearly distinct severities:

- **Read-only, lower severity** (`App\v1\ApiController` - 6 call sites around delivery-charge/pincode
  calculation; `CartController` - 4 similar call sites, e.g. `fetchDetails(Address::class, ['id' =>
  $address_id])` at `CartController.php:765`/`900`/`1179` and `Address::where('id',
  $request->input('address_id'))->value('pincode')` at `:817`): these resolve a client-supplied
  `address_id` to its pincode/city (occasionally full address text) with no `user_id` check, to compute
  shipping cost/serviceability during checkout. Impact is limited to inferring another user's rough
  location (pincode/city), not full PII, and nothing is mutated. Carried to §4 as a lower-severity,
  multi-site finding rather than fixed here (6+4 call sites, each needing its own read of how `$user_id`
  is available in that method's scope before scoping the lookup).
- **Write-capable, high severity, new finding** — `Seller\PosController::update_user_address()`
  (`app/Http/Controllers/Seller/PosController.php:949-990`, route `PUT
  seller/point_of_sale/update_user_address`, inside the `auth`+`role:seller` group): takes `address_id`
  from the request and directly overwrites that Address row's `name`/`mobile`/`address`/`city`/`state`/
  `country` with **no check that it belongs to any particular customer, let alone one this seller is
  transacting with**. Any authenticated seller can silently corrupt or redirect **any customer's saved
  address anywhere in the system** - including that customer's future *online* orders, not just this POS
  sale - by guessing an `address_id`. Its sibling `getCustomerAddress()` (`:909-947`) has a related but
  more ambiguous shape: it looks up any customer's saved address by `pos_user_id` with no restriction, but
  this may be intentional POS design (staff looking up a walk-in customer's profile by phone/id is normal
  retail-POS behavior) rather than a bug - unlike `update_user_address`, which has no legitimate reading
  under which arbitrary, unscoped **mutation** of another customer's saved delivery address is intended
  behavior.

  **Not fixed in this task**, deliberately: the correct fix likely needs to tie `update_user_address` back
  to the specific customer the seller is actually transacting with (e.g. requiring the same `pos_user_id`
  the current POS lookup flow already has, and verifying the `address_id` belongs to that user) - designing
  that correctly means reading the POS frontend flow to confirm what data is actually available at update
  time without guessing, which risks breaking a currently-working seller workflow if done wrong. Recorded
  in §4 as the highest-severity deferred finding from this task rather than risk an unverified fix, per this
  phase's explicit "the existing application must remain functional throughout" constraint.

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
| `Seller\PosController::update_user_address()` overwrites any Address row's name/mobile/address/city/state/country by id, with no ownership check at all - can redirect any customer's future deliveries | **Critical (write)** | `app/Http/Controllers/Seller/PosController.php:949-990` | Correct fix needs to verify what customer-identifying data is actually available in the POS frontend flow at update time (the request currently carries only `address_id`, not the customer's own id) - risks breaking a currently-working seller workflow if guessed at rather than confirmed against the real frontend | Add `pos_user_id` (or equivalent) to the update payload, verify the target `Address.user_id` matches it server-side before saving, `abort(403)`/404 otherwise; add a regression test mirroring `AddressOwnershipTest` |
| `Seller\PosController::getCustomerAddress()` returns any customer's saved address/mobile/name by `pos_user_id`, with no restriction | Medium (ambiguous) | `app/Http/Controllers/Seller/PosController.php:909-947` | May be intentional POS design (staff look up a walk-in customer's profile by phone/id) rather than a bug - needs a product decision, not just a code fix | If unintended: require the customer to be present/verified via OTP or similar before exposing their saved address to POS staff; if intended, document it explicitly as accepted behavior |
| `App\v1\ApiController` (6 call sites, e.g. `:2406`,`:2479`,`:2669`,`:3165`,`:4315`) and `CartController` (4 call sites, e.g. `:765`,`:817`,`:900`,`:1179`) resolve a client-supplied `address_id` to its pincode/city (occasionally full address) for delivery-charge calculation, with no `user_id` check | Low-Medium (read, PII-adjacent) | `app/Http/Controllers/App/v1/ApiController.php`, `app/Http/Controllers/CartController.php` | 10 call sites, each needing its own read of how `$user_id`/`Auth::id()` is available in that method's scope before scoping the lookup - broader than this task's bounded time | Add `->where('user_id', Auth::id())` (or equivalent) to each lookup; confirm none of the 10 are legitimately used in a guest/pre-auth checkout context first |
| `Seller\MediaController::destroy($id)` deletes any media row (and its underlying file) by id with no seller ownership check | High | `app/Http/Controllers/Seller/MediaController.php:237-243` (found in `docs/PHASE_2_MULTITENANCY.md`, Task 6) | Kept this task's fixes to the two proven, highest-confidence findings (2a/2b) rather than expanding scope mid-task | Same pattern as this task's fixes: `TenantContext::userOwnsSeller()` check before `find()`/`delete()`, `abort(404)` on mismatch |
| `Admin\CronJobController::settleCashbackDiscount()` and `sendCartReminders()` are reachable with **no authentication or permission check at all** (`routes/admin_routes.php:60-61`) — the sibling `settleSellerCommission()` on the same lines correctly has `permissions:edit seller` | Medium | `routes/admin_routes.php:60-61` | Different vulnerability class from this task's scope (unauthenticated trigger of a privileged batch/financial-settlement job and a job that calls paid third-party AI APIs, not per-record ID-guessing data disclosure) — belongs to Task 13's dedicated route security audit, not this IDOR-focused pass | Add `->middleware('permissions:edit seller')` (or an equivalent) to both, matching their sibling; if these are meant to be triggered by an external system cron with no user session, gate them with a shared-secret query param or move them behind Laravel's `schedule:run` instead of a public URL |
| `admin.stores.index` (`web.php:174`) is reachable unauthenticated | Low | `routes/web.php:174` | Confirmed low-impact (returns only a static view shell, no store data) - not worth the risk/attention budget alongside the two critical fixes above | Move inside `admin_routes.php`'s auth group for consistency, at low priority |
| Every "not independently audited this task" row in `docs/PHASE_2_MULTITENANCY.md` §4 (`seller_store`, `seller_commissions`, `order_charges`, `combo_products`, `favorites`, `images`) | Unknown - not yet assessed | Various `Seller\*Controller` classes | Exhaustive per-method review of ~200+ controller methods is explicitly out of this task's bounded scope | Continue the same method: grep each table's controllers for `::find($id)`/`::where('id', $id)` lookups not scoped by `seller_id`/`user_id`, verify with a direct read, fix with `TenantContext` + a regression test |

This table is the honest state of play: two critical, evidence-verified, real vulnerabilities were found and
fixed end-to-end (code + routing + regression tests) in Tasks 8-9, a real infrastructure bug was found and
fixed as a side effect of verifying the tests actually pass, Task 10's Address re-verification surfaced one
further critical write-capable finding and several lower-severity read ones, and everything surfaced along
the way is named explicitly rather than silently dropped.

## 5. Task 11 — API authorization audit of the three large controllers

Given the combined size (~14,000 lines across `App\v1\ApiController`, `Seller\v1\ApiController`,
`Delivery_boy\v1\ApiController`), this pass used the same grep-and-verify method as above rather than a
line-by-line read of all three files, each method's finding confirmed by direct code reading before acting
(not taken on faith from a first pass).

### 5a. `App\v1\ApiController.php` — 6 confirmed IDORs, fixed this task

All six take an `order_id`/`order_item_id`/`ticket_id` directly from request input and act on it (mutate
status, delete, attach a transaction/proof/message) with no check tying the record back to the authenticated
customer. Fixed with the same ownership-check-before-acting pattern used throughout this phase, each
returning the endpoint's own existing "not found" response shape on a mismatch (no new information leak):

| Method | What was exposed | Fix |
|---|---|---|
| `update_order_item_status()` | Any customer could cancel/return any other customer's order item (with real refund/stock side effects) | `OrderItems::where('id', ...)->where('user_id', auth()->id())->exists()` guard added before acting |
| `update_order_status()` | Any customer could change any order's status and trigger a refund | `(int) $order->user_id !== (int) auth()->id()` guard added after the existing `Order::findOrFail()` |
| `delete_order()` | Any customer could permanently delete any other customer's order (crediting *that* customer's wallet as a side effect) | Ownership guard added before any of the existing deletion logic runs |
| `add_transaction()` | Any customer could attach a fabricated "success" transaction to another customer's order, which other endpoints treat as proof of payment | Guard added: only blocks when `order_id` resolves to a real order that isn't the caller's - a standalone (non-order) wallet-recharge transaction, which doesn't require `order_id` to reference a real order, is unaffected |
| `send_bank_transfer_proof()` | Any customer could attach an uploaded "proof" file to another customer's order | Added `user_id` to the existing lookup's where-clause (one-line change, reuses the existing not-found response) |
| `send_message()` (ticket messages) | Any customer could post a message into another customer's support ticket | `Ticket::where('id', ...)->where('user_id', ...)->exists()` guard added - the same check `edit_ticket()` elsewhere in this file already correctly applies |

**Not yet covered by a dedicated regression test** - existing `tests/Feature/Phase2/*` suite (83 tests)
re-run clean after these fixes (no regressions), but new tests proving each fix specifically are pending;
tracked as follow-up work alongside §5b below.

### 5b. `Seller\v1\ApiController.php` and `Delivery_boy\v1\ApiController.php` — 8 confirmed findings, not yet fixed

Found via the same method, verified against the source but **not yet fixed** - recorded here rather than
left undocumented, to be picked up in the next continuation of this task:

| # | Finding | File:line | Severity |
|---|---|---|---|
| 1 | `delete_product()` deletes any seller's product (and variants/attributes) by `product_id`, no seller check | `Seller/v1/ApiController.php` ~1690 | High |
| 2 | `update_product_status()` checks the *caller's* store permission but updates the target product unscoped - any seller can (de)activate any other seller's product | `Seller/v1/ApiController.php` ~2166 | High |
| 3 | `update_product_deliverability()` bulk-updates any `product_id`s' deliverable type/zones, only checks existence not ownership | `Seller/v1/ApiController.php` ~4760 | Medium |
| 4 | `update_combo_product_deliverability()` - same flaw as #3 for `ComboProduct` | `Seller/v1/ApiController.php` ~4793 | Medium |
| 5 | `delete_combo_product()` deletes any combo product by `product_id`, no ownership check | `Seller/v1/ApiController.php` ~3683 | High |
| 6 | `delete_order()` deletes any order and its items by `order_id`, no ownership check (the Seller-app counterpart to §5a's customer-app finding) | `Seller/v1/ApiController.php` ~3101 | Critical |
| 7 | `delete_order_parcel()` computes `seller_id` but never uses it - any seller can delete any parcel by id | `Seller/v1/ApiController.php` ~4626 | High |
| 8 | `update_returned_order_item_status()` lets any delivery boy flip the return status of an order item not assigned to them | `Delivery_boy/v1/ApiController.php` ~1339, ~1372 | High |

Checked and confirmed **not** vulnerable despite a similar surface shape (for completeness, not because they
were skipped): `download_order_invoice`/`download_parcel_invoice` in `Seller\v1\ApiController` only check
existence unscoped themselves, but delegate to `generatInvoicePDF`/`generatParcelInvoicePDF`, which (per §2a
above) correctly re-derive and enforce `seller_id` from `Auth`; and `get_orders`/`get_wallet_transaction`/
`update_fcm`/`delete_delivery_boy`/`send_withdrawal_request`/`get_withdrawal_request` in the delivery-boy
controller are all correctly scoped by `auth()->user()->id`/`delivery_boy_id` in the same query.
