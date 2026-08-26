# Initial Codebase Audit — eShop Plus 1.0.6 → Value Market

**Phase:** 0 (Audit)
**Status:** Source-verified for backend PHP (app/, routes/, config/, resources/views, the 3 migration
files present). Still not verified: the Flutter mobile apps, `resources/js` build pipeline beyond a
near-empty `bootstrap.js`, `database/factories`/`database/seeders`, and the automated test suite — none of
these were part of either upload. Every claim below is evidence-based; anything not directly confirmed in
source is labeled **[UNVERIFIED]**.

## Audit Scope & Evidence (read this first)

Two evidence passes went into this document:

1. **Manifests + DB dump** (first pass): `composer.json/.lock`, `package.json/.lock`, and a full 90-table
   MySQL/MariaDB structure dump (`eshop_plus.sql`, no data). Findings from this pass are in
   `DATABASE_GAP_ANALYSIS.md`.
2. **Real backend source** (this pass, supersedes prior inferences): `app/`, `routes/`, `config/`,
   `resources/` (views + lang, not the JS build), and `database/migrations/` — uploaded as five zips and
   fully inspected (all 90-odd controllers, all 74 models by directory listing, the middleware stack, all
   route files, and the three actual migration files present).

**The 442MB full source archive still hasn't been transferred wholesale** — this audit is built from the
targeted zips above, which is why frontend build tooling, the mobile apps, and tests remain unverified.
Everything else — routing, controllers, models, middleware, auth config — is now first-hand, not inferred.

**One important correction to the prior version of this document**: the earlier draft, working only from
`package.json`'s dependency list, described the admin panel as "Inertia.js + React." **That was wrong.**
Source inspection shows zero `Inertia::render()` calls anywhere in the codebase, `inertiajs/inertia-laravel`
(the required server-side half) is absent from `composer.json`, and there are zero Livewire components
(`extends Component`) despite `livewire/livewire` being installed. The admin, seller, and delivery-boy
panels are **traditional server-rendered Blade views** (`resources/views/admin`, `/seller`,
`/delivery_boy`, with an AJAX/DataTables pattern for list screens — see `CashCollectionController::list()`
for a representative example). The React/Inertia/MUI/NextUI packages in `package.json` and the
`HandleInertiaRequests`/`RedirectIfAuthenticated` middleware in `app/Http/Middleware` appear to be
**vestigial** — leftovers from a Laravel Breeze/Jetstream-style scaffold that was never wired up, not an
active part of the running application. This is exactly why the master prompt's audit-before-coding rule
exists: the manifest-only inference was plausible and wrong.

## 1. What Already Exists

**Framework/stack (confirmed)**: Laravel 10 (`v10.48.25`) on PHP `^8.1`. Auth: `laravel/sanctum` for API
tokens, but `config/auth.php`'s `api` guard is configured with `'driver' => 'session'`, with
`'driver' => 'sanctum'` **commented out** in the file. Routes still use `->middleware(['check_token',
'auth:sanctum'])` — Sanctum's own service provider registers the `sanctum` guard independently of this
config array, so the API likely still works, but the config file itself is inconsistent/misleading and
should be cleaned up in Phase 1. **[flag for verification once the app can actually be booted]**

**Applications (confirmed, three distinct route/controller/view sets in one Laravel app)**:
- **Admin** — `routes/admin_routes.php` (80KB, by far the largest route file), `Controllers/Admin/*`
  (47 controllers), `resources/views/admin/pages/{forms,tables,views}` (Blade).
- **Seller** (= "vendor" in the master prompt's terminology — see terminology note below) —
  `routes/seller_routes.php` + `routes/seller_api.php`, `Controllers/Seller/*` (25 controllers, including
  **`PosController.php` and `StockController.php` — real, working code**), `resources/views/seller`.
- **Delivery boy** — `routes/delivery_boy_routes.php` + `routes/delivery_boy_api.php`,
  `Controllers/Delivery_boy/*` (6 files), `resources/views/delivery_boy`.
- **Customer-facing mobile/API** — `routes/api.php` (9KB) routes almost everything to one controller:
  `App\Http\Controllers\App\v1\ApiController` — **94 public methods in a single 7,572-line file.** The
  Seller and Delivery-boy apps follow the identical pattern: `Seller\v1\ApiController` (85 methods, 4,997
  lines) and `Delivery_boy\v1\ApiController` (1,458 lines). This is the actual mobile API surface; no
  separate `resources/js` SPA consumes it — the composer/package React stack noted above is not it.

**Terminology note (binding for all later docs)**: this codebase calls marketplace merchants **"Seller"**,
not "Vendor" — `Seller.php`, `SellerStore.php`, `SellerCommission.php`, `Controllers/Seller/*`. The
`Controllers/vendor/Chatify/*` namespace is unrelated — it's just Chatify's package-publish path
(lowercase `vendor`), not the business "Vendor" concept. Future phases should decide whether to rename
Seller→Vendor to match the master prompt's language, or keep "Seller" and treat it as a synonym — flagging
now so it isn't mistaken for two different entities.

**RBAC (confirmed, and more precisely characterized than the prior draft's guess)**: `User` uses **both**
Spatie's `HasRoles`/`HasPermissions` traits **and** a custom `role()` `belongsTo(Role::class)` relation
against a separate legacy `roles` table keyed by `users.role_id`. The two systems are used for different
things, in different middleware, on the same request lifecycle:
- `RoleMiddleware` gates access by comparing `$user->role->name` (the **legacy** relation) against an
  allow-list — e.g. admin routes require `role:super_admin` etc.
- `CheckPermissions` middleware bypasses entirely for `role_name === 'super_admin'` (**also** using the
  legacy relation), and otherwise checks Spatie's `$user->hasPermissionTo($permission)` for granular
  gates.
- Elsewhere, delivery-boy identity is checked by **hardcoded magic number** — `User::where('role_id', 3)`
  appears directly in `Admin\CashCollectionController` rather than through a named constant or the role
  system at all.

So: Spatie *permissions* are real and enforced; Spatie *roles* (`model_has_roles`) appear unused in favor
of the legacy single `role_id` — worth confirming with a full-text search once more source is available,
but not found in any file inspected so far. This is the concrete version of "dual RBAC" flagged in the
prior audit draft; Phase 2 should decide whether to migrate the legacy role check onto Spatie roles or
formally keep the legacy table as the "role" concept and use Spatie purely for granular permissions.

**POS (confirmed, more built than previously assessed)**: `Seller\PosController` (965 lines) implements a
real walk-in-sale flow — register/lookup a customer, browse products and combo products, `place_order()` /
`combo_place_order()` that creates real `Order`/`OrderItems` rows. `Seller\StockController` (273 lines)
gives sellers a stock-adjustment screen for products and combo products. **What's still missing** (matches
the schema audit): no shift/till model, no cash-drawer open/close, no split-payment handling in this
controller — it places one order per transaction like the e-commerce flow, just with an in-person
customer-selection step first.

**Cash reconciliation (confirmed)**: `Admin\CashCollectionController` and the `fund_transfers` table
implement delivery-boy cash-in-hand tracking via the flat `transactions` table (`type = 'delivery_boy_cash'`
vs. a "collected" state), not a structured ledger.

**Commission (confirmed)**: `SellerCommission` is exactly what the schema suggested — a flat
per-seller/store/category commission **rate**, no ledger. `OrderService.php` computes an actual commission
amount for **delivery boys** (percentage of order total, or a flat bonus, capped at the order total) and
credits it via `WalletService::updateBalance()` at order-completion time — this is a real, working
single-entry wallet credit, not a chart-of-accounts posting.

## 2. What Works (evidenced, not just schema-plausible)

Confirmed end-to-end by controller code, not just the schema: vendor/seller onboarding and storefronts;
product + variant + combo-product catalog management (admin and seller side); cart/checkout via the
mobile-facing `ApiController`; order lifecycle with delivery-boy assignment, OTP delivery confirmation, and
commission payout to the delivery boy's wallet; seller-side POS order creation; stock adjustment; wallet
balance updates; support tickets; Chatify chat; Spatie permission-gated admin actions; multi-language label
delivery (`get_language_labels` endpoint, `resources/lang/{ar,en,es,hi,hn,ja}`); Shiprocket courier
integration; PayPal/Stripe/Razorpay/Paystack/PhonePe/Midtrans payment libraries all present under
`app/Libraries/` with real client code (not just composer dependencies).

## 3. What Is Incomplete

- **POS**: real order-placement flow, but no shift/till/cash-drawer/split-payment structure (confirmed
  above, matches the prior schema-based assessment).
- **Money-moving code has zero database transaction boundaries**: `grep -r "DB::transaction" app/` returns
  **zero matches** anywhere in the codebase. Multi-step financial writes (place an order → decrement stock
  → log a transaction → credit a wallet/commission) are not wrapped atomically. Combined with `orders` and
  `wallet_transactions` being MyISAM (no transaction support even if `DB::transaction` were used — see
  `DATABASE_GAP_ANALYSIS.md`), this is a confirmed, concrete correctness risk, not a theoretical one.
- **Auth config inconsistency**: `config/auth.php`'s `api` guard says `session`, not `sanctum` (see §1) —
  needs runtime verification, not just a read of the config file, before Phase 2 relies on it.
- **Analytics/reporting**: `Admin\ReportController` and `Seller\ReportController` are each just two
  methods (`index`, `list`) — a single generic sales listing, not the multi-dimensional analytics the
  master prompt asks for. Confirms the schema-based assessment; now source-verified.
- **Migrations don't track the schema**: only 3 files exist under `database/migrations/`
  (`create_users_table`, `add_avatar_to_users`, and one with a stray smart-quote character in its filename
  — `2024_04_03_124401_add_google_id_column#U201d.php`, almost certainly a copy-paste artifact that should
  be renamed before it causes a tooling problem). The other ~87 tables in `eshop_plus.sql` were not created
  by any migration in this codebase — this product ships/installs via a raw SQL import (a common CodeCanyon
  pattern), meaning **Laravel migrations are not the schema's source of truth today.** This has to be
  fixed in Phase 1: either backfill migrations for the full existing schema before adding new tables, or
  explicitly decide new tables get real migrations while old ones stay SQL-installer-managed (riskier,
  less reproducible).

## 4. What Should Be Reused

Unchanged from the prior draft, now on firmer footing: the Laravel/Sanctum/Spatie-Permission foundation,
the seller/marketplace core, translatable+RTL i18n pattern, the already-integrated payment gateway client
code in `app/Libraries/` (not just SDK dependencies — actual integration code), Chatify, the ticketing
system, Shiprocket integration. **Correction**: do not plan to reuse or extend an "Inertia+React admin" —
it doesn't exist; the admin/seller/delivery panels to extend are Blade.

## 5. What Should Be Refactored

- **Three monolithic API controllers** (7,572 / 4,997 / 1,458 lines, 94/85/~40 methods respectively) with
  no FormRequest validation classes, no Policies, no Repository layer anywhere in `app/` — confirmed by
  directory search. This is the single biggest code-quality refactor the target platform needs before
  layering POS/inventory/accounting endpoints onto the same pattern; the master prompt's phase discipline
  (Section 44) argues for extracting resource-scoped controllers + Form Requests as part of whichever phase
  first touches each domain, rather than a big-bang rewrite.
  - One concrete bug found in passing during this audit: `Admin\CashCollectionController::list()` contains
    `foreach ($$txnSearchRes as $row)` — a variable-variable typo (should be `$txnSearchRes`) that would
    error at runtime. Left as-is pending Phase-appropriate fix (out of scope for Phase 0, noted for
    triage).
- **Dual RBAC** (§1) — collapse onto one system in Phase 2, per the concrete mechanism now documented
  above rather than a generic "there are two systems" note.
- **Money as `double`, no DB transactions, MyISAM on financial tables** — unchanged from the prior draft,
  now compounded by the confirmed absence of any `DB::transaction` usage. This is the highest-priority
  Phase 1 fix given how directly it threatens the "every ledger entry traceable, never silently wrong"
  requirement in the master prompt.
- **Remove or genuinely adopt the vestigial React/Inertia/Livewire dependencies** — carrying unused
  frontend frameworks as dependencies is dead weight and a source of confusion (as this very audit's first
  draft demonstrates). Decide in Phase 1 whether the target platform's admin panel stays Blade (extend what
  exists) or migrates to a modern SPA (bigger, deliberate call, not a side effect of adding features).

## 6. What Should Be Replaced / Newly Developed

Unchanged from the prior draft — confirmed by source, nothing found in `app/` moves any of these from
"missing" to "existing": full accounting engine (chart of accounts, journal entries, GL, AR/AP), inventory
engine (warehouses, stock movements, valuation), procurement (suppliers, POs, GRNs), POS shift/till
structure, partners/assets/liabilities, employee management distinct from sellers, affiliate/reseller
engine (link generation, click tracking, commission ledger — `SellerCommission` and the delivery-boy
commission code in `OrderService` are the only commission-shaped code found, and neither is a
rule-engine/ledger), multi-company structure, CRM, AI BI layer.

## 7. Existing Database Relationships

See `DATABASE_GAP_ANALYSIS.md` for the full table inventory. Source confirms the schema audit's finding
about weak referential integrity: Eloquent relationships in the models (`belongsTo`/`hasMany`/etc.) are
extensive and generally sensible, but — as the schema dump already showed — almost none of them are backed
by an actual DB foreign-key constraint, so integrity depends entirely on this application code being
correct and consistently used, which fat, un-tested, un-policed controllers make harder to guarantee.

## 8. Existing APIs — Now Verified

Three route files, three matching monolithic controllers, all confirmed in source (see §1). Public/no-auth
endpoints include catalog browsing, registration/login/OTP, settings, and payment webhooks
(`ipn`, `app_payment_status`, `handle_paystack_callback`, `paypal_transaction_webview`). Authenticated
endpoints (`middleware(['check_token', 'auth:sanctum'])`) cover the rest of the customer lifecycle
(favorites, addresses, cart, orders, wallet, etc. — full enumeration deferred to Phase 2's API cataloguing
work rather than reproduced here). No API versioning beyond the `v1` namespace already in place; no OpenAPI
spec or route-level documentation found.

## 9. Existing Mobile Applications — Still Unverified

The Flutter source zip has still not been transferred into this session. Everything in §1 about the
customer/seller/delivery-boy `ApiController`s describes the **backend API surface** those apps presumably
call, not the apps themselves. No change from the prior draft here — still **[UNVERIFIED]**.

## 10. Technical Risks (updated)

1. Zero `DB::transaction` usage across money-moving code, compounded by MyISAM on `orders`/
   `wallet_transactions` — **now confirmed in source, not just inferred from engine choice.** Highest
   priority.
2. Money as `double` throughout — unchanged, confirmed.
3. Schema not tracked by migrations (§3) — confirmed; a real risk for reproducible environments and for
   safely layering new migrations on top without collisions.
4. Three 1,000–7,500-line controllers with no validation/policy/repository layer — confirmed; raises the
   cost and risk of every future change that touches the mobile API.
5. `config/auth.php`'s `api` guard misconfiguration (§1) — needs runtime verification.
6. Dual RBAC with an inconsistent enforcement mechanism (§1) — confirmed, now precisely characterized.

## 11. Security Risks (updated)

- The CodeIgniter-style `users` columns (`activation_selector`, `forgotten_password_selector`, etc.)
  flagged in the prior draft as a plausible legacy-carryover concern were **not** found to be actively used
  in the `User` model's `$fillable` or in any controller inspected — `User::$fillable` doesn't include
  them, and Laravel's own `password_reset_tokens` table exists alongside. They may be genuinely dead
  columns from an earlier version rather than active legacy auth code. Downgrading this from "plausible
  risk" to "likely dead schema, verify before assuming it's a live code path."
- The hardcoded `role_id === 3` check for delivery boys (§1) bypasses the named-role/permission system
  entirely in at least one controller — a magic number like this is exactly the kind of thing that causes
  authorization bugs when role IDs are reseeded or renumbered. Worth a full-text sweep for other hardcoded
  `role_id` comparisons in Phase 2.
- No FormRequest validation layer (§5) means input validation is presumably inline per-method across
  ~200+ controller methods — consistency and coverage cannot be assessed without reading every method;
  flagged as a Phase-2/15 verification task rather than assumed-safe or assumed-broken.

## 12. Performance Risks

Unchanged from the prior draft (MyISAM table-level locking); now additionally: three controllers averaging
thousands of lines each are also a maintainability-driven performance risk indirectly — large classes with
mixed concerns are harder to profile and optimize safely.

## 13. Next Step

Backend PHP is now source-verified. The two remaining gaps before Phase 1 can start with full confidence:
push the **Flutter mobile app source**, and either the **`resources/js` build config + `database/factories`
+ `database/seeders` + `tests/`**, or confirmation that no meaningful frontend JS / test suite exists beyond
what's already been seen. Given the volume of real findings already surfaced by targeted zip uploads (this
approach worked well), the same pattern — zipped subfolders under GitHub's per-file size limit — is the
recommended way to get the remaining pieces in.
