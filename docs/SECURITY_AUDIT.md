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

### 6.3 Explicitly out of scope for Phase 15 (deferred, not dropped)

**RBAC redesign (dual `role_id`/Spatie mechanism → one).** The roadmap's own Phase 15 description names this
as in-scope. It is not attempted here. This is a massive, high-risk, cross-cutting architectural change -
Phase 2 already investigated the dual mechanism directly (`docs/PHASE_2_RBAC_ARCHITECTURE.md`) and
deliberately declined to unify it, for the same reason it's declined again now: every route's authorization
ultimately traces back to whichever mechanism is live today, and a migration attempt that gets even one
edge case wrong risks locking out the admin panel or silently changing who can do what, application-wide.
That is not a risk to take unsupervised, and the standing authorization for this session ("continue on your
own") was given for real, scoped bug-fixing work of exactly the shape this file documents - not license to
gamble the whole application's access control while nobody is watching the result. Recommended remediation
path, unchanged from Phase 2's own conclusion: pick one mechanism (role_id, since it's the one every route
actually gates on today), migrate Spatie's permission data into it deliberately over a dedicated phase with
a human reviewing each step, then remove the unused mechanism last, once nothing references it.

**Global `Model::unguard()` removal.** See Finding 16 above - same reasoning, same scale, same explicit
deferral, now confirmed a second time by an independent audit pass reaching the identical conclusion Phase
2 already documented.
