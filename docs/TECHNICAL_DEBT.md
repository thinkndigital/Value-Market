# Technical Debt Register

Consolidates every technical-debt finding from Phase 0 and Phase 1 into one place, per Task 7/13. Each item
cross-references where it was found and verified in detail rather than repeating the full write-up. This is
a living document — later phases should add to it, not fork a parallel list.

## Architecture-level debt

| Item | Detail | Where documented |
|---|---|---|
| Three monolithic API controllers | `App\v1\ApiController` (7,572 lines, 94 methods), `Seller\v1\ApiController` (4,997 lines, 85 methods), `Delivery_boy\v1\ApiController` (1,458 lines) — this is most of the mobile API surface | `INITIAL_CODEBASE_AUDIT.md` §5 |
| No FormRequest layer | Validation is inline, per-method, across ~200+ controller methods | `INITIAL_CODEBASE_AUDIT.md` §5, `PHASE_1_ARCHITECTURE.md` Task F |
| No Policy layer (before Phase 1) | Ownership/authorization checks were ad-hoc per method; `ProductPolicy` is the first, Phase 1 doesn't claim it's the last needed | `PHASE_1_ARCHITECTURE.md` Task F |
| No Repository layer | Controllers/Services talk to Eloquent directly. Per Task 7, this is **not** debt to fix — an unnecessary Repository layer is exactly the "abstraction for appearance" Phase 1 was told to avoid | `PHASE_1_ARCHITECTURE.md` Task F |
| Dual RBAC mechanism | `RoleMiddleware`/`Gate::before()`'s super-admin bypass use the legacy `role_id`/`Role` relation; granular gates use Spatie `hasPermissionTo()`; a hardcoded `role_id === 3` check for delivery boys exists in `Admin\CashCollectionController` | `INITIAL_CODEBASE_AUDIT.md` §1, `PHASE_1_DATA_INTEGRITY_REPORT.md` §5 |
| Migration bookkeeping was fabricated | The live `migrations` table records 8 migrations as "run," including one whose `up()` method is entirely empty — the real schema came from a raw SQL installer, not these files | `PHASE_1_DATABASE_MIGRATION_PLAN.md` §2 |

## Code-level bugs found (not all fixed — see status column)

| Bug | Status | Where documented |
|---|---|---|
| `Admin\Webhook.php` imported a non-existent class, fatally erroring on every payment webhook | **Fixed** (Phase 1 — needed to even boot the app for verification) | `PHASE_1_ARCHITECTURE.md` |
| `delete_address`/`update_address` IDOR — any user could delete/edit any other user's address | **Fixed** (Phase 1) | `SECURITY_AUDIT.md` §1a |
| `generatParcelInvoicePDF` IDOR — any seller can view another seller's order/customer PII | **Documented, not fixed** — Phase 2 | `SECURITY_AUDIT.md` §1b |
| POS `place_order()`'s item loop returns after its first iteration — multi-item POS carts silently drop all but one line item | **Documented, not fixed** — Phase 6 | `PHASE_1_TRANSACTION_BOUNDARIES.md` §2 |
| POS `place_order()` never decrements stock for regular products | **Documented, not fixed** — Phase 6 | `PHASE_1_TRANSACTION_BOUNDARIES.md` §2 |
| `Admin\CashCollectionController::list()` — `foreach ($$txnSearchRes as ...)`, a variable-variable typo that would error at runtime | **Documented, not fixed** — noted for triage, not exercised by any test in this phase | `INITIAL_CODEBASE_AUDIT.md` §5 |
| `wallet_transactions.last_updated` had an invalid `'0000-00-00 00:00:00'` default, rejected by strict-mode MySQL | **Fixed** (Phase 1, in the baseline migration) | `PHASE_1_DATABASE_MIGRATION_PLAN.md` §4 |
| `currencies.exchange_rate` and `combo_products.delivery_charges` were non-numeric column types for money | **Fixed** (Phase 1) | `PHASE_1_FINANCIAL_PRECISION.md` §3 |

## Schema/model mismatches (dormant — not live bugs, but misleading)

| Item | Detail |
|---|---|
| `Seller::$fillable` lists columns (`store_name`, `store_url`, `commission`, `account_number`, ...) that don't exist on `seller_data` — they belong to `seller_store` | Not a live bug: the real seller-creation controller code builds separate, correct arrays for each table. Confirmed by writing `ProductPolicyTest.php`, where a naive `Seller::create()` with those fields throws "Unknown column." Cleanup candidate: trim `$fillable` to match reality. |
| `Role` model doesn't disable `$timestamps`, but `roles` has no `created_at`/`updated_at` columns | Dormant — nothing in the app creates roles through Eloquent (seeded via SQL installer). Would throw immediately if anything ever called `Role::create()`. |
| `model_has_roles`/`model_has_permissions` (Spatie's own pivot tables) have zero declared indexes or primary key in the live schema | A performance/integrity gap on tables queried on every permission check. Not fixed in Phase 1 (adding an index safely requires confirming no duplicate rows exist in real production data first — same discipline as the FK work). |
| `AuthServiceProvider`/`RoleMiddleware`/`CheckPermissions` all do `$user->role->name` with no null check | `users.role_id` is nullable; confirmed empirically to crash (`Attempt to read property "name" on null"`). Whether reachable in production depends on whether role-less users (likely plain customers) ever hit these paths — Phase 2 (RBAC) territory. |

## Infrastructure/deployment debt

| Item | Detail |
|---|---|
| 10 files with PSR-4 namespace/directory case mismatches (`Seller/PaymentRequestController.php` declares `namespace ...\seller`, etc.) | Confirmed **not currently breaking** — PHP resolves both casings via fallback (tested with `class_exists()`), and `php artisan route:list` resolves all 1,066 routes cleanly. Would break under `composer install --classmap-authoritative`. Deployment-pipeline flag for later, not fixed now (10 cosmetic renames, unrelated to Phase 1's actual scope). |
| Vestigial React/Inertia/Livewire dependencies in `composer.json`/`package.json` | No `Inertia::render()`, no `inertiajs/inertia-laravel`, no Livewire components anywhere in source — the admin/seller/delivery panels are Blade. Decide in a later phase whether to remove the dead dependencies or actually adopt them; not touched in Phase 1. |
| `resources/js` is effectively empty (`bootstrap.js` only) | Confirms the above; no frontend build pipeline currently does anything real. |
| Money display formatting is currency-blind | `formatePriceDecimal()`/`CurrencyService::formateCurrency()` hardcode 2 decimal places regardless of currency (JPY would show decimals it shouldn't; KWD would be missing one it should have). Storage precision was fixed in Phase 1; display formatting wasn't touched (separate concern, real UI risk if left unaddressed before a true multi-currency launch). |

## What Phase 1 deliberately did NOT do here (and why that's not debt left behind)

Per Task 7: "Do not create unnecessary Repository layers. Do not create abstractions merely for
appearance." Actions, Events/Listeners, and Jobs were not scaffolded speculatively — `PHASE_1_ARCHITECTURE.md`
Task F names concrete future candidates (an `OrderPlaced` event extracted from `placeOrder()`'s inline
notification block is the clearest one) rather than building empty classes nothing calls yet. That's a
conscious decision, not an oversight — recorded here so it isn't rediscovered as "missing" later without
context.
