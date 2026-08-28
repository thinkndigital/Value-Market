# Security Audit — Phase 1 (Task 8)

**Scope**: a focused Phase 1 security review, not a full penetration test and not the RBAC redesign
Phase 2 owns. Everything below was verified against actual source and, where noted, actual runtime
behavior (tests, `php artisan tinker`) — not inferred from reading alone. This document doesn't repeat
findings already fully written up elsewhere; it cross-references them and adds what's new from this pass.

## 1. Confirmed IDOR vulnerabilities

### 1a. `delete_address` / `update_address` — FIXED in this phase

**Before this phase**: `Admin\AddressController::destroy()` did `Address::find($id)->delete()` with no
ownership check anywhere in the call chain; `store()`'s update path did
`updateDetails($addressData, ['id' => $request->input('id')], Address::class)`, also with no ownership
check. **Any authenticated customer could delete or modify any other customer's saved address by passing
its numeric id** — both are destructive/integrity-violating, not just information disclosure, and address
IDs are small sequential integers (trivially enumerable).

**Fix**: both methods now verify the target address's `user_id` matches the requesting user before making
any change; `destroy()` gained an optional `?int $requestingUserId` parameter (its one call site was
updated to pass it — grepped for other callers first, there are none, so this couldn't have broken an
existing legitimate use).

**Verified, not just written**: `tests/Feature/Phase1/AddressOwnershipTest.php` — 4 tests: an attacker
cannot delete or update another user's address; the legitimate owner still can, for both operations. All
passing. (The "owner can still update" test initially passed for the wrong reason — the update wasn't
running at all for either owner or attacker, because `AddressController::store()` reads input via the
global `request()` helper, not its `$request` parameter, and a manually-constructed test `Request` isn't
automatically bound to that helper. Caught by writing the negative test's counterpart, not left as a false
positive — see the test file's own comment.)

**Known residual gap in the fix**: `delete_address`'s caller (`App\v1\ApiController`) still returns
`"Address Deleted Successfully"` unconditionally, regardless of whether `destroy()` actually deleted
anything — this was already true before the fix (the original code never checked `destroy()`'s return
value either) and wasn't expanded in scope to change the response contract. The important property — that
another user's data cannot be touched — now holds; the response text being uninformative about a
silently-blocked attempt is a smaller, pre-existing issue.

### 1b. `Seller\OrderController::generatParcelInvoicePDF()` — documented, NOT fixed

`generatParcelInvoicePDF($id)` fetches a parcel and generates an invoice PDF from it
(`fetchDetails(Parcel::class, ['id' => $id])`) with **no check that the parcel belongs to the
authenticated seller** before rendering order details, product names, prices, delivery-boy info, and the
customer's mobile number into the response. `$seller_id` is computed and used later for *enriching* line
items, but the initial parcel/order lookup is unscoped. **Any authenticated seller can view another
seller's (and that seller's customer's) order and contact details by guessing/incrementing a parcel ID.**

**Not fixed in this phase.** Unlike the address case, this method has more downstream logic that assumes
`$parcels[0]` exists and multiple enrichment steps after it; a safe fix means deciding what should happen
on a mismatch (redirect, 403 JSON, empty PDF) in a way that doesn't break the legitimate multi-seller-order
PDF case this method also seems to handle (a parcel can span order items from multiple sellers — see the
`$seller_ids`/`$seller_user_ids` handling). That's a judgment call belonging to a proper security/RBAC pass
(Phase 2), not a Phase 1 database-foundation change made under time pressure to a method not otherwise
touched this phase.

### 1c. Pattern likely recurs elsewhere — not exhaustively swept

These two were found by spot-checking a handful of methods, not by auditing all ~200+ controller methods
across the three monolithic API controllers (`docs/TECHNICAL_DEBT.md`) — that volume of manual review is
disproportionate for "a focused Phase 1 security review." The `Seller\ProductController` ownership check
Phase 1 already centralized into `ProductPolicy` (`docs/PHASE_1_ARCHITECTURE.md` Task F) is the third
confirmed instance of the same underlying pattern: ownership scoping implemented ad-hoc, inconsistently, or
not at all, per controller method. **Recommendation for Phase 2**: a systematic IDOR sweep of all
seller/customer-scoped endpoints, informed by the tenant model Phase 1 already established (`seller_data`
is the tenant boundary — `docs/PHASE_1_ARCHITECTURE.md` Task G), is a named, explicit Phase 2 deliverable,
not an assumption that Phase 1's two fixes covered the problem.

## 2. Areas checked and found sound (verified, not assumed)

- **Mass assignment**: no model declares `$guarded = []`. 10 models (`Order`, `OrderItems`,
  `OrderCharges`, `SellerStore`, `Role`, others) declare neither `$fillable` nor `$guarded`, which means
  Laravel's own default (`$guarded = ['*']` — nothing mass-assignable) applies; these are maximally
  protected, not exposed. Where the app needs to write to those tables anyway, it uses `forceCreate()`
  (11 call sites, all grepped and checked) — every one of them builds its data array from hand-curated,
  server-side-constructed fields, never `$request->all()` or an unfiltered `$request->only([...])` with a
  broad field list. No `::create($request->all())` anti-pattern found anywhere in `app/`.
- **CORS** (`config/cors.php`): `allowed_origins => ['*']` with `supports_credentials => false` — this
  combination is safe (the dangerous case is wildcard origin *with* credentials, which browsers reject
  outright anyway); this API authenticates via Sanctum bearer tokens, not cookies, so `false` here is
  correct, not an oversight.
- **TrustHosts**: uses Laravel's safe default (`allSubdomainsOfApplicationUrl()`), not a wildcard.
- **TrustProxies**: `$proxies` is `null` (Laravel's safe default — no proxy headers trusted until
  explicitly configured for a real reverse-proxy deployment).
- **Config files** (`services.php`, `mail.php`, `filesystems.php`, `database.php`, `sanctum.php`): grepped
  for anything that looks like a hardcoded credential/key literal instead of an `env()` call — none found.
- **No secrets committed**: `.env` is git-ignored (confirmed — `git status`/`git add -A --dry-run` show it
  excluded) and was never staged. `.env.example` contains only placeholder values, consistent with the
  vanilla Laravel skeleton (`APP_KEY=` empty, `PUSHER_APP_KEY=` empty, etc.) — nothing that resembles a
  real secret.

## 3. Operational reminder (not a code defect)

`.env.example` ships `APP_DEBUG=true` / `APP_ENV=local` — Laravel's own standard local-development
defaults. This is not a vulnerability in this repository (it's a template, not the real production `.env`,
which was never provided or committed), but it's worth stating plainly for whoever deploys this: **production
must set `APP_DEBUG=false`**. With it `true`, an unhandled exception renders a full stack trace, including
environment variable values, to any visitor — a critical misconfiguration if it ever ships that way. This
belongs in `docs/DEPLOYMENT.md` when that's written (Phase 17), flagged here so it isn't lost.

## 4. Findings already documented elsewhere (cross-referenced, not repeated)

- **Tenant isolation / authorization**: `docs/PHASE_1_ARCHITECTURE.md` Task G (the `seller_data`-as-tenant
  decision) and Task F (the `ProductPolicy` fix). `docs/PHASE_1_DATA_INTEGRITY_REPORT.md` §5 covers the
  `AuthServiceProvider`/`RoleMiddleware`/`CheckPermissions` null-`role_id` crash risk (confirmed
  empirically) and the hardcoded `role_id === 3` delivery-boy check.
- **API authorization / validation coverage**: `docs/PHASE_1_ARCHITECTURE.md` Task F (no FormRequest/
  Policy/Repository layer existed before this phase; what was and wasn't introduced, and why).
- **Full technical-debt inventory** (PSR-4 case mismatches, model/schema mismatches, dual RBAC mechanism):
  `docs/TECHNICAL_DEBT.md`.

## 5. What Phase 1 did NOT do (explicitly out of scope, per Task 8's own instruction)

Did not redesign RBAC. Did not add centralized authorization middleware/policies beyond the one
(`ProductPolicy`) directly tied to Phase 1's own tenant-model work. Did not perform an exhaustive
endpoint-by-endpoint IDOR audit. Did not add rate limiting, audit logging, or 2FA — none of these were
identified as Phase 1 blockers, and adding them speculatively would be exactly the "abstractions for
appearance" this phase's own rules warn against. All are reasonable Phase 2/15 candidates, named here for
continuity rather than silently dropped.

## 6. Phase 15 — self-audit of Phase 4-14's new code (Security Hardening)

### 6.0 Methodology

Phases 4-14 (docs/IMPLEMENTATION_ROADMAP.md) added roughly a dozen new subsystems on top of the Phase 1-3
foundation this file otherwise covers: branches/employees/suppliers, purchase orders, POS shifts/payments,
the affiliate/commission engine, delivery dispatch and earnings, a double-entry ledger, CRM notes/tags, and
analytics. None of that new surface had been through a dedicated security pass before shipping. A background
review agent was run against every new controller and service from those phases (file list scoped to the
Phase 4-14 diff), specifically checking: tenant-ownership on every write, mass-assignment of
tenant/ownership foreign keys, unvalidated input reaching money-moving or stock-moving code, and any
authorization check that only verifies "this resolves to a seller_id" where the actual question is "is this
specific action allowed for this specific actor." It reported 17 findings; each was independently re-read
against the actual source (not taken on the agent's word) before being fixed, deferred, or - in one case -
found to already be a known, documented, deliberately-deferred issue and reverted rather than shipped as a
misleading partial fix. Every fix below has a regression test proving the exploit path is closed and the
legitimate path still works.

### 6.1 Fixed this phase

**Finding 1 (HIGH) — cross-tenant stock injection via purchase orders.**
`Seller\PurchaseOrderController::store()` (`app/Http/Controllers/Seller/PurchaseOrderController.php`)
verified `supplier_id` and `branch_id` belonged to the acting seller, but never checked
`product_variant_id`. Variant ids are small, sequential, and visible on the public storefront - a seller
could receive stock (via `receive()`) into *any other seller's* product, inflating that seller's on-hand
quantity and poisoning their weighted-average cost basis, both without that seller's knowledge or consent.
Fixed by requiring every line item's variant to join back to a product this seller owns, same request-reject
pattern as the existing supplier/branch checks. Test:
`tests/Feature/Phase5/PurchaseOrderControllerTest.php::test_a_seller_cannot_create_a_po_against_another_sellers_product_variant`.

**Finding 3 (MEDIUM) — affiliate self-referral payout.**
`AffiliateService::recordConversion()` (`app/Services/AffiliateService.php`) attributed a conversion (and
therefore a real wallet credit on approval) to whoever created the affiliate link, with no check that the
buyer and the link owner weren't the same account. An affiliate could buy from their own link repeatably,
controlling the order total themselves, and collect commission on their own purchases. Fixed: a self-
referral records no conversion at all (same "sale still completes, just untracked" behavior the existing
no-matching-rule case already has). Test:
`tests/Feature/Phase7/AffiliateServiceTest.php::test_a_self_referral_is_not_recorded_as_a_conversion`.

**Finding 4 (MEDIUM) — no commission clawback on return/cancellation.**
`AffiliateService::approveConversionsForOrder()` credits a real wallet balance on delivery, but had no
counterpart: an order returned or cancelled after delivery left the commission paid, permanently. Combined
with Finding 3's self-referral gap (or simple buyer/affiliate collusion), this was a repeatable way to
extract real money - buy, get paid, return for a full refund, keep the commission. Added
`reverseConversionsForOrder()`, wired into both places an order item can be cancelled/returned:
`ReturnRequestService::applyTransition()` (the customer-return-request path) and
`Admin\OrderController`'s direct status-change path, with the same idempotency-key discipline
`approveConversionsForOrder()` already uses. A wallet-debit shortfall (affiliate already spent/withdrew the
balance) is recorded via `auditLog('affiliate.commission_reversal_shortfall', ...)` rather than silently
dropped or retried forever - the platform then has a known, logged, uncollected receivable instead of a
silent loss. Tests:
`tests/Feature/Phase7/AffiliateServiceTest.php::test_reverse_conversions_for_order_debits_back_an_approved_commission`
and `::test_reverse_conversions_for_order_is_a_no_op_when_nothing_was_approved`.

**Finding 6 (MEDIUM) — cross-tenant POS shift injection.**
`PosShiftService::recordSaleForOpenShift()` (`app/Services/PosShiftService.php`) trusted a caller-supplied
`pos_shift_id` as long as its status was OPEN, with no check it belonged to the seller the sale was actually
for. A malicious cashier could pass another seller's (or another seller's cashier's) open shift id and have
their own sale's cash/payment lines posted into it, corrupting that shift's cash reconciliation at close
time. Fixed by threading the sale's own `seller_id` (already resolved in `Seller\PosController` for branch
ownership) through to `recordSaleForOpenShift()` and requiring it to match on both the requested-shift
lookup and the cashier's-own-active-shift fallback. Test:
`tests/Feature/Phase6/PosShiftServiceTest.php::test_a_requested_shift_belonging_to_a_different_seller_is_not_used`.

**Finding 7 (MEDIUM) — unvalidated POS payment splits.**
The same method recorded a caller-supplied `payments` array verbatim, with no check its lines summed to the
order's `total_payable`. Shift cash variance is computed purely from what's recorded in `pos_payments`, so a
cashier under-reporting a split (e.g. claiming only 60 of a 100 cash sale) would never show as a
discrepancy at close time - an invisible, repeatable way to skim cash. Fixed: a supplied split that doesn't
sum to the order total (small float-rounding tolerance aside) is discarded in favor of the same single
trustworthy line used when no split is given - a sale is never blocked by a bad split, it's just not allowed
to under-report through one. Test:
`tests/Feature/Phase6/PosShiftServiceTest.php::test_a_payments_split_that_under_reports_the_order_total_is_discarded`.

**Finding 9 (MEDIUM) — employee privilege escalation over the roster itself.**
`TenantContext::sellerIdFor()` (Phase 4, `app/Services/TenantContext.php`) deliberately resolves the same
`seller_id` for an employee as for the seller who employs them, so that Phase 2/3/4 code already scoped
through `currentSellerId()` works correctly for logged-in employees. `EmployeeController::store()`/
`update()`/`destroy()` used exactly that same resolution - meaning every active employee had full owner
authority over the roster itself: creating more employees, deactivating coworkers, reassigning branches.
Added `TenantContext::isSellerOwner()` - the one predicate distinguishing the actual owner account from an
employee acting for that owner's tenant - and gated all three roster-mutation endpoints on it. `list()` is
left open to employees (read-only, no privilege implication). Tests:
`tests/Feature/Phase4/EmployeeControllerTest.php::test_an_employee_cannot_create_another_employee` and
`::test_an_employee_cannot_deactivate_a_coworker`.

**Finding 10 (MEDIUM) — unbounded percentage commission rate.**
`Admin\CommissionRuleController` validated `rate_value` as `numeric|min:0` with no upper bound. A
fat-fingered or malicious admin setting e.g. 1000 on a `percentage`-type rule would auto-pay 10x order value
on every affiliate conversion platform-wide from the moment the rule went live - a single bad write with
immediate, ongoing, platform-scale financial impact. Capped percentage rates at 100 on both `store()` and
`update()` (including switching an existing flat rule to percentage); flat rates are a fixed currency amount
with no equivalent natural ceiling and are correctly left unbounded. Tests:
`tests/Feature/Phase15/CommissionRuleRateCapTest.php` (5 cases).

**Finding 11 (LOW) — unguarded-null TypeError risk.**
`Seller\CrmController::listNotes()`/`customerLifetimeValue()` called `customerBelongsToSeller(int
$customerUserId, int $sellerId)` - a non-nullable parameter - without first checking `currentSellerId()`
could return null (an authenticated user who owns/works for no seller), throwing an uncaught TypeError (a
500) instead of the clean "Data Not Found" every sibling CRM method already returns for the same condition.
Not a data-exposure bug, but an unhandled-error/DoS-adjacent gap worth the one-line guard every sibling
method already has. Tests: `tests/Feature/Phase15/LowSeverityFixesTest.php` (2 cases).

**Finding 12 (LOW) — unthrottled public click-tracker.**
`GET r/{code}` (`AffiliateController::trackAndRedirect()`) is public by design (no account needed to click
an affiliate link), but had no rate limiting at all - anyone could script arbitrary `clicks_count` inflation
for any affiliate link, gaming performance metrics, or simply grow `link_clicks` without bound. Added
Laravel's standard `throttle:60,1` middleware (60/minute/IP - generous for a real visitor, not for a
script). Verified via `php artisan route:list -v`; not covered by an HTTP-level test since this suite's
existing affiliate tests call the controller method directly rather than through real HTTP requests (no
precedent in this codebase for route-middleware-level tests), and adding one pattern for a single one-line
middleware change wasn't judged worth the divergence.

**Finding 13 (LOW) — no validation on supplier updates.**
`Seller\SupplierController::update()` had no `Validator::make()` call at all, while `store()` validates the
same fields - `email` wasn't required to look like an email, `status` could be set to any value outside the
active/inactive enum. Mirrored `store()`'s validator into `update()`. Tests:
`tests/Feature/Phase15/LowSeverityFixesTest.php` (3 cases).

**Finding 14 (LOW) — no validation on branch coordinates.**
`Seller\BranchController` accepted and stored `latitude`/`longitude` in both `store()` and `update()` with
zero validation - not range-checked, not even type-checked. Added `numeric|between:-90,90` /
`numeric|between:-180,180`. Tests: `tests/Feature/Phase15/LowSeverityFixesTest.php` (2 cases).

**Finding 15 (LOW) — no format validation on CRM tag color.**
`Seller\CrmController::tagCustomer()`'s `color` field had no validation before being stored and presumably
rendered back into an admin/seller UI. Added a hex-color regex (`^#[0-9a-fA-F]{6}$`). Test:
`tests/Feature/Phase15/LowSeverityFixesTest.php::test_tag_customer_rejects_a_non_hex_color`.

### 6.2 Investigated, found to already be a known and documented deferral - not re-fixed

**Finding 16 (LOW as reported) — "latent mass-assignment surface" on `Branch`/`Supplier` `$fillable`.**
The background agent flagged that both models' `$fillable` arrays include `seller_id`, which would be
exploitable if any controller ever mass-assigned request data directly into `::create()`/`::update()`. On
investigation this is **not actually a two-model issue**: `Model::unguard()` is called globally in
`app/Providers/AppServiceProvider.php::boot()` (line 58), on every single request, with no matching
`Model::reguard()` anywhere in the codebase. This makes `$fillable`/`$guarded` **inert for every model in
the entire application**, not just these two - confirmed empirically (a test asserting `seller_id` was
rejected via mass-assignment on a narrowed `$fillable` failed: the value came through anyway). This is
Phase 2's own already-documented finding (`docs/PHASE_2_MASS_ASSIGNMENT_AUDIT.md`, Task 12), deliberately
deferred at that time for the same reason it's deferred again here: safely removing the global unguard
requires auditing every `::create()`/`::fill()`/`::update()` call site across the app (Phase 2 counted
~200+ methods across three ~14,000-line API controllers alone) to confirm none currently *relies* on
receiving fields outside its declared `$fillable` - a project of its own scale, not a sub-task of either
phase. Narrowing just `Branch`/`Supplier`'s `$fillable` here would have shipped a code comment implying
protection that does not exist anywhere it's written, while leaving the actual global gap completely
unaddressed - worse than not touching it, since it creates false confidence. The edit was made, its test
failed, and both were reverted rather than shipped. **Current actual protection**: every write to `Branch`/
`Supplier` in this codebase goes through `forceCreate()` with an explicit, hand-built array (`seller_id` set
server-side, never from request input) - confirmed by re-grepping both controllers; the risk is real but not
currently exploited by any call site, exactly Phase 2's own conclusion.

**Finding 17 (LOW) — silent stock-quantity clamping diverges from the movement ledger.**
`InventoryService::recordMovement()` (`app/Services/InventoryService.php:42-75`) writes a `StockMovement`
row with the full requested quantity, then applies the delta to `StockItem.quantity` clamped to a floor of
zero (`max(0, ...)`, line 71). If a movement would drive quantity negative, the audit trail (`StockMovement`)
records what was *requested* while the actual state (`StockItem.quantity`) reflects only what was *possible*
- the two silently diverge, and nothing surfaces the gap. This is a real, confirmed inconsistency, but not a
tenant-isolation or authorization bug (both rows stay correctly scoped to `seller_id`), and fixing it means
choosing new behavior for stock math this codebase has treated with above-average care everywhere else -
Phase 3 explicitly deferred a comparable proportional-refund/restock decision (see
`docs/PHASE_3_COMMERCE_CORE.md`) rather than guess at financial-adjacent behavior unsupervised. Two real
remediation options exist and neither was picked blind: (a) reject a movement that would drive quantity
negative outright (`throw` instead of clamp), which is more correct but could break any existing caller that
currently relies on over-decrementing being silently absorbed; (b) record the *actually-applied* (post-clamp)
delta on the `StockMovement` row instead of the requested one, keeping the ledger truthful without changing
any caller's behavior. (b) is the lower-risk option and the one this deferral recommends for the dedicated
follow-up pass, but it wasn't implemented blind here without first confirming no report or reconciliation
logic elsewhere already assumes `StockMovement.quantity` means "requested," not "applied."

**Follow-up (attempted, reverted): option (b) is not actually safe as originally scoped.** Implemented it
directly - clamp against `StockItem.quantity`'s own running total before writing the ledger row - and the
full test suite caught a real regression immediately:
`tests/Feature/Phase5/ProductServiceUpdateStockLedgerTest::test_a_deduction_call_writes_an_out_movement`
expects a deduction against a variant seeded with `stock: 5` (via the legacy `Product_variants.stock`
field) to ledger a quantity of `2`; the attempted fix ledgered `0` instead. Root cause: `StockItem` (the
Phase 5 ledger's own running total) and `Product_variants.stock`/`Product.stock` (the pre-Phase-5 legacy
field `ProductService::updateStock()` still authoritatively decrements) are **two independent tracking
systems that were never backfilled to agree** - a `StockItem` row is created lazily at `quantity = 0` on
first use (`InventoryService::recordMovement()`), regardless of what the legacy field already held. For
any variant whose stock predates Phase 5 (or simply hasn't had a `recordMovement()` call yet, which is
every variant on first deduction), clamping against `StockItem.quantity` clamps against a value with no
relationship to the real available stock - turning "record what was actually possible" into "record zero,
because the shadow ledger never knew the real number." This would have silently zeroed out ledger entries
for real, valid order-placement deductions across the majority of pre-existing inventory - a worse bug than
the one being fixed. Reverted immediately (matching this project's established discipline: a fix the test
suite catches gets reverted, not shipped with the test loosened).

**What (b) actually requires, not yet done:** a one-time backfill migration seeding `stock_items.quantity`
from `Product_variants.stock`/`Product.stock` for every existing variant (choosing which legacy field wins
where a product has both product- and variant-level stock, `stock_type` already encodes that per-product)
*before* `recordMovement()` can safely trust `StockItem.quantity` as ground truth. That backfill is a real,
separate data-migration decision (which stock number is "correct" for a variant that already drifted
between the two systems pre-Phase-5) - out of scope for this security-audit pass to guess at, and squarely
the kind of "financial/inventory math this codebase treats with above-average care" this finding already
flagged as needing a dedicated, deliberate pass rather than a quick patch.

### 6.3 Explicitly out of scope for Phase 15 (deferred, not dropped)

**RBAC redesign (dual `role_id`/Spatie mechanism → one) - superseded by direct re-investigation, corrected
here.** The roadmap's Phase 15 description names this as in-scope, and this file originally deferred it
here as "a massive, high-risk, cross-cutting architectural change... every route's authorization ultimately
traces back to whichever mechanism is live today" - repeating Phase 2's own framing
(`docs/PHASE_2_RBAC_ARCHITECTURE.md`) without re-verifying it against the current code. A direct
investigation (re-reading `RoleMiddleware`, `CheckPermissions`, `UserPermissionController`, `User.php`'s
actual trait usage, and the live database) found that framing to be **wrong**: `role_id` and Spatie do not
duplicate each other and there is no merge to perform.

- **`role_id`** (`App\Models\Role`, the app's own `roles` table, 6 fixed rows: super_admin/admin/editor/
  seller/delivery_boy/customer) answers *who this user broadly is* - checked by `RoleMiddleware` and
  throughout the app (confirmed: this is the only mechanism ~90+ pre-existing call sites and every phase
  of this project's own new code actually gate on).
- **Spatie's Permission mechanism** (`hasPermissionTo()`/`syncPermissions()`) answers a completely different
  question - *what is this specific admin/editor account allowed to do* - and is genuinely, extensively
  live: confirmed **180 routes** in `routes/admin_routes.php` gated by its `permissions:` middleware
  (`App\Http\Middleware\CheckPermissions` → `$user->hasPermissionTo()`), wired up via
  `UserPermissionController::permissionsUpdate()` (`$user->syncPermissions($permissions)`) - this is the
  real feature letting a Super Admin grant an Editor account specific permissions (e.g. "create categories"
  but not "delete blogs") without giving them the broader Admin role. Not dead code; not a duplicate of
  `role_id` - a finer-grained layer *within* the accounts `role_id` already marks as admin/editor.
- **Spatie's Role *assignment* mechanism** (`assignRole()`, `hasRole()`, Spatie's own `Role` model) **is**
  confirmed genuinely unused - no code anywhere calls it - and additionally not even usable as installed:
  Spatie's `Role` model expects a `guard_name` column on its `roles` table, but that table name collides
  with this app's own pre-existing legacy `role_id` table (`App\Models\Role`), which has no such column.
  Attempting to actually remove this specific piece was tried live (dropping the `HasRoles` trait from
  `App\Models\User`, keeping only `HasPermissions`) and reverted immediately: the full test suite caught
  that `HasPermissions::hasPermissionViaRole()` internally calls `hasRole()`, which only `HasRoles` defines
  - the two traits are not independently optional despite `HasPermissions` being the only one whose own
  methods are called directly elsewhere in this app. The unused capability (role *assignment* specifically)
  has no isolated removal path within the User model as currently structured; both traits stay.

**Conclusion: no RBAC redesign is needed.** The "dual mechanism" was never two competing systems to merge -
`role_id` and Spatie permissions are two different, correctly-coexisting concerns, and the previously-cited
"lock out the admin panel" risk does not apply to a merge that was never actually necessary. This section is
corrected rather than deleted, so the earlier (wrong) framing and the reasoning that superseded it both stay
visible for anyone reading this file's history.

**Global `Model::unguard()` removal.** See Finding 16 above - same reasoning, same scale, same explicit
deferral, now confirmed a second time by an independent audit pass reaching the identical conclusion Phase
2 already documented.

### 6.4 `SetDefaultStore` / `StoreService::getStoreId()` IDOR sweep (post-Phase 15 follow-up)

**Root cause.** `SetDefaultStore` (`app/Http/Middleware/SetDefaultStore.php`) runs in the global `web`
middleware group and reads `?store=<slug>` off *any* web request's query string with zero ownership check,
overwriting `session('store_id')`. This is a correct, intentional feature for anonymous customer storefront
browsing (real demo links like `?store=prime-pantry` exist in `resources/views/webCategoriesStyle.blade.php`
and `webProductCardStyle.blade.php`). The bug is that `StoreService::getStoreId()` - which just reads back
that same session value - is also trusted by Seller-panel (and Admin-panel) controllers for
authorization-relevant `store_id` resolution, letting an authenticated seller silently repoint the session
their own write requests use just by loading any page with `?store=<victim-slug>` in the URL first (no
special access needed - any public storefront link works). `StoreService::getStoreId()` itself can't be
fixed centrally without breaking the admin panel (admins aren't rows in `seller_store`), so the fix is an
opt-in verification helper, `TenantContext::verifiedSellerStoreId($candidateStoreId)`
(`app/Services/TenantContext.php`): confirms the *authenticated* user actually manages the given `store_id`
via a `SellerStore` row, returning `null` (reject) otherwise. Callers keep resolving their own candidate
`store_id` (session, request, or both) and just verify it before using it.

**Write-path (money/stock/data-creating) call sites fixed, in the order worked through:**

- `Seller\PosController::place_order()` / `combo_place_order()` - a POS sale (real stock deduction, wallet/
  earnings effects) previously didn't check `Auth::user()` at all.
- `Seller\v1\ApiController::add_brands()` (mobile API) and `Seller\BrandController::store()` (web panel) -
  `brands` has no `seller_id` of its own, `store_id` *is* the tenant boundary.
- `Seller\ProductController::update()` - the same `seller_id`-forgery-on-write gap `store()` and
  `ComboProductController::update()` had already been fixed for, but this method was missed in that earlier
  pass.
- `Seller\CategoryController::store()`.
- `Seller\MediaController::upload()` - worse than the rest: this one trusted `$request->input('store_id')`
  and `$request->input('seller_id')` **directly**, no session/`SetDefaultStore` step needed at all.
- `Seller\ComboProductController::store()` - same direct-request-trust pattern as `MediaController::upload()`
  for both `seller_id` (via `$request->user_id`) and `store_id`.
- `Seller\ComboProductController::update()` - `store_id` had the same unverified-candidate write gap;
  `seller_id` was already protected by a pre-existing ownership check (Phase 2, Task 16).

Each fix has a paired regression test under `tests/Feature/Phase15/*StoreOwnershipTest.php` (or, for
`PosController`, `tests/Feature/Phase15/PosStoreOwnershipTest.php`) proving both the attack is rejected and
the legitimate owning seller is unaffected.

**Checked and found to have no working write path (nothing to fix):**

- `Seller\AttributeController` - the `Route::resource(...)->except('show')` registration implies
  `store`/`update`/`destroy`, but the controller only defines `index`/`list`/`getAttributes`/
  `getAttributeValue` - those routes would fatal with "call to undefined method" if ever hit. No seller-side
  attribute-creation code exists anywhere in the app (grepped); pre-existing dead routes, out of scope for a
  security-focused pass since there is no live request to secure.
- `Seller\ComboProductAttributeController` - only `index()`/`list()`, both read-only.

**Read-only call sites - completed.** `StoreService::getStoreId()` has ~59 call sites across ~16
Seller-panel controllers (and a separate ~24 in the Admin panel, out of scope - admins legitimately choose
stores). All write/data-creating ones were fixed first (above); the remaining ~50 read-only
(`list()`/`index()`/`show()`/report-style) call sites were then triaged by an actual risk check rather than
fixed uniformly: most of them **also** filter by the authenticated seller's own `seller_id` (derived from
`Auth::id()`, not attacker-controlled) in the same query - for those, even a `SetDefaultStore`-hijacked
`store_id` can only narrow results toward empty, it cannot leak another seller's data, so they were left
as-is (confirmed safe, not fixed for the sake of uniformity). Only the call sites where `store_id` was the
*sole* tenant boundary - because the model has no `seller_id` concept at all (`Brand`, `Attribute`,
`Attribute_values`), or the check was simply missing where a sibling method already had it
(`ComboProductFaqController::list()`) - were genuinely exploitable, and those are now fixed:

- `Seller\ProductController::edit()` (the single most severe of this batch - no ownership check *at all*,
  not even store_id-hijack-dependent: a store can host multiple sellers, so any co-seller in the same store
  could load another seller's full product edit-form data, pricing/shipping/brand included, with zero
  session manipulation needed). Fixed via `ProductPolicy`'s `view` ability, the same rule `update()` already
  uses.
- `Seller\ProductController::get_brands()`/`getBrands()`/`getDigitalProductData()` - `Brand` has no
  `seller_id`; `getDigitalProductData()` listed digital products (name/price/stock) with no seller filter at
  all.
- `Seller\ComboProductFaqController::list()` - this controller's other methods already verify per-record
  ownership via `TenantContext::userOwnsSeller()`; `list()` alone had no equivalent check.
- `Seller\AttributeController::list()`/`getAttributeValue()` - `Attribute`/`Attribute_values` have no
  `seller_id` concept; `getAttributeValue()` took `store_id` directly from the request, no session hijack
  even needed.

All seven verified via `TenantContext::verifiedSellerStoreId()`, each rejecting with an empty result shaped
to match that method's existing success response (view name / plain array / JSON keys) rather than a generic
error wrapper, so no API contract changed - only what data an unauthorized `store_id` can retrieve. Paired
tests: `tests/Feature/Phase15/ProductControllerReadOwnershipTest.php` and
`tests/Feature/Phase15/ComboProductFaqAndAttributeReadOwnershipTest.php`.

### 6.5 CRITICAL - full account takeover via `Seller\UserController`/`Delivery_boy\UserController::update()`

Found while continuing the §6.4 sweep by checking whether other Seller-panel controllers had similar
unguarded write paths. This is unrelated to `SetDefaultStore`/`store_id` - a distinct, more severe bug found
along the way, and the single worst finding of this entire security effort.

**The bug.** `seller/account/update/{id}` (`Seller\UserController::update()`) and
`delivery_boy/account/update/{id}` (`Delivery_boy\UserController::update()`) both took `$id` straight from
the URL/form-action with **no ownership check at all** - `User::find($id)`, then that record's
`username`/`email`/`mobile`/`address`/**`password`** were overwritten (a full password reset via the
`old_password`/`new_password` fields) and `role_id` was force-set (to `Role::SELLER`/`3` respectively), with
**no filter on what role `$id` actually was**. Any authenticated seller or delivery boy could take over
**any other account** - another seller, another delivery boy, or any user id at all - just by changing the
id in the request. Both controllers' `edit()` methods had the matching read-side gap (the target user's full
row, unfiltered, handed to the view). Confirmed via the route definitions
(`routes/seller_routes.php:52`, `routes/delivery_boy_routes.php:46`) that `{id}` is a raw, unbound URL
segment, not tied to the authenticated user by any middleware or route-model-binding constraint.

**Why this wasn't caught by the store_id sweep.** These pages are "my own account settings" - there is no
`store_id` involved anywhere in the vulnerable code path, so none of the `TenantContext`/`SellerStore`
ownership patterns used throughout §6.4 apply. The fix is simpler and different in kind: `$id !==
Auth::id()` → reject, before touching anything else.

**Checked for the same pattern elsewhere:**
- The customer-facing mobile API (`App\v1\ApiController::update_user()` and every other `User::find($user_id)`
  call site in that file except one, grepped individually) already derives `$user_id` from
  `auth()->user()->id`, never from a request/route parameter - safe by construction.
- No separate Seller/Delivery_boy mobile-API "update profile" endpoint exists (grepped
  `Seller\v1\ApiController`/`Delivery_boy\v1\ApiController` for `update`/`*Profile*` methods - none found);
  the mobile apps for those roles call the same two now-fixed web routes.
- `App\v1\ApiController::paypal_transaction_webview($user_id, $order_id, $amount)` also takes `$user_id` as
  a raw parameter and does `User::find($user_id)` - but only *reads* the user (email, for the PayPal form)
  and never writes to it. Lower-severity (info disclosure of an email address via a payment redirect flow,
  not account takeover). **Fixed as a follow-up** (see below) rather than left deferred, once the PayPal
  integration itself was actually read in enough depth to fix it safely.

#### 6.5.1 Follow-up: `paypal_transaction_webview()` info disclosure - fixed

The endpoint is unauthenticated by necessity - it's loaded directly as a webview URL by the mobile app, with
no bearer token available for a normal `auth:sanctum` check - so the fix couldn't be "require login" the way
the account-takeover fix above was. Instead it closes the actual gap: previously `$order_id` was looked up
with zero check that it belonged to `$user_id` at all (`OrderService::fetchOrders($order_id)` with no
`$user_id` argument, despite the method supporting one), and the target user's real email was embedded in
the rendered PayPal auto-submit form's hidden `custom` field regardless of whether a matching order was even
found - the "no order found" branch disclosed the email exactly as readily as the "order found" branch. An
attacker who knew nothing but a candidate `user_id` (sequential, easily enumerated) could view any user's
email by hitting the URL with any `order_id`.

`$order_id` has two legitimate shapes in this app, both already handled elsewhere in the codebase (see
`Admin\Webhook.php`'s own parsing of the same convention): a numeric real order id, or a synthetic
`wallet-refill-user-{user_id}-{time}-{random}` id used for wallet top-ups (confirmed via
`App\v1\ApiController.php:7588` and `Admin\Webhook.php:216-223`). Both are now required to actually belong to
`$user_id` before anything is disclosed - the numeric case via `fetchOrders($order_id, $user_id)` (now
passing the owner filter the method already supported), the wallet-refill case by parsing the embedded user
id out of the string and comparing it to `$user_id`. Anything else (mismatched owner, or an `$order_id` that
matches neither shape) gets a generic "Order Not Found" JSON response instead. The two near-identical
found/not-found branches in the original method (which built the exact same PayPal form either way) were
also collapsed into one, since `$data['order']` was assigned but never actually used by
`paypal_auto_form()`.

Found and fixed incidentally while writing this test: `OrderService::fetchOrders()`'s per-item return-window
calculation read `$settings['max_days_to_return_item']` with no null-coalescing fallback, and
`database/migrations/2025_01_01_000016_baseline_default_settings.php`'s default `system_settings` row never
seeded that key (it's only ever written once an admin saves the System Settings page) - so any order-detail
fetch on a fresh install crashed with `Undefined array key "max_days_to_return_item"`. Defaulted to `0` days,
matching that same migration's own stated purpose (documented in its own header comment) of not trusting
settings keys to exist unconditionally.

Tests: `tests/Feature/PaypalTransactionWebviewOwnershipTest.php` (6 tests) - a numeric order belonging to a
different user does not leak the email; the same order belonging to the requesting user still renders the
form with their own email; a wallet-refill id for a different user does not leak the email; a matching
wallet-refill id still renders the form; an unrecognised non-numeric `order_id` does not leak the email; an
unknown `user_id` gets a clean JSON error. Full suite: 381/381 passing.
- `Admin\*` controllers' many `User::find($id)` call sites are unrestricted **by design** - an admin
  managing arbitrary sellers/delivery boys/customers is the actual, intended feature of the admin panel, not
  a bug.

**Fix.** Both `edit()` and `update()` in both controllers now reject with `admin.pages.views.no_data_found`
(GET) or a JSON `{'error':true,...}` (PUT, matching each controller's existing response style) before doing
anything else, unless the requested `$id` equals `Auth::id()`. Tests:
`tests/Feature/Phase15/SellerUserControllerAccountOwnershipTest.php` and
`tests/Feature/Phase15/DeliveryBoyUserControllerAccountOwnershipTest.php` (4 tests each: update denied
cross-account with the victim's password proven unchanged, edit denied cross-account, edit allowed for the
owner, update allowed past the ownership gate for the owner).
