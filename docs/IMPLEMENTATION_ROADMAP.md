# Implementation Roadmap — Value Market

This roadmap follows the master prompt's phase list (Section 43) and phase-execution rule (Section 44):
each phase is analyze → define → implement → migrate → test → fix → verify existing functionality →
document → phase report. No phase is marked complete on UI placeholders alone.

## Remaining Gap — Should Close Before Mobile/Frontend Phases

**Backend PHP source is now verified** (`app/`, `routes/`, `config/`, `resources/views`, and the 3 actual
migration files — see `INITIAL_CODEBASE_AUDIT.md`, second pass). Still not in this repository: the **Flutter
mobile apps**, the **`resources/js` build pipeline** (currently just an empty `bootstrap.js` in what was
provided), **`database/factories`/`database/seeders`**, and **`tests/`**. Phases 1–2 (architecture, RBAC,
DB migrations, commerce/vendor/inventory/POS/affiliate/delivery/accounting schema and backend logic) can
proceed on the current evidence. **Phase 13 (mobile apps) and any frontend-asset-pipeline work cannot be
scoped until the Flutter source and the real `resources/js` are available** — same request as before, just
narrower now: push those two pieces (zipped subfolders under GitHub's size limit worked well for the
backend pass) when convenient, no need to block Phase 1 on it.

## Phase 0 — Audit ✅ (this document set)

Delivered: `INITIAL_CODEBASE_AUDIT.md`, `TARGET_ARCHITECTURE.md`, `DATABASE_GAP_ANALYSIS.md`,
`EXISTING_FEATURE_MATRIX.md`, this roadmap — updated with source-verified findings (RBAC's actual dual
mechanism, the real POS/StockController code, zero `DB::transaction` usage, the schema-vs-migrations gap,
and the Blade-not-Inertia admin-panel correction). Outstanding: mobile apps, JS build, factories/seeders,
tests (see gap above).

## Phase 1 — Architecture & Database Design ✅

**Complete and verified** — see `PHASE_1_FINAL_REPORT.md` for exact numbers, `CHANGELOG.md` for the itemized
list, and `PHASE_1_DATABASE_MIGRATION_PLAN.md`/`PHASE_1_DATA_INTEGRITY_REPORT.md`/
`PHASE_1_FINANCIAL_PRECISION.md`/`PHASE_1_TRANSACTION_BOUNDARIES.md`/`PHASE_1_ARCHITECTURE.md`/
`SECURITY_AUDIT.md`/`TECHNICAL_DEBT.md` for the full detail behind each task. The tenant question below was
resolved (`seller_data`, not `stores` — see `PHASE_1_ARCHITECTURE.md` Task G), the baseline migrations were
built and verified, and the original task list is kept below for historical record:

- Resolve the multi-company-vs-multi-vendor tenant question (`TARGET_ARCHITECTURE.md` §1) with the user.
- Backfill Laravel migrations that reproduce the current live schema (`INITIAL_CODEBASE_AUDIT.md` §3 /
  `DATABASE_GAP_ANALYSIS.md` §7) so `php artisan migrate` becomes the real source of truth going forward,
  before adding a single new table on top of an untracked schema.
- Design `companies`/`branches`/`warehouses` schema nested under the resolved tenant unit.
- Write the additive migrations for Section 3–4 of `DATABASE_GAP_ANALYSIS.md`: MyISAM→InnoDB on the 11
  affected tables, `double`→`DECIMAL` money-column migration plan (parallel-write/verify/cutover, not a
  single destructive pass), and start wrapping money-moving code paths in `DB::transaction` (currently
  zero usage anywhere — `INITIAL_CODEBASE_AUDIT.md` §3).
- Produce `DATABASE.md`.

## Phase 2 — RBAC + Multi-Tenancy

- Decide the fate of the legacy `role_id`/`Role::belongsTo` mechanism vs. Spatie roles, given the concrete
  split found in source: `RoleMiddleware` and `CheckPermissions`'s super-admin bypass both key off the
  legacy `$user->role->name`, while granular gates use Spatie's `hasPermissionTo()`
  (`INITIAL_CODEBASE_AUDIT.md` §1). Sweep for other hardcoded `role_id` comparisons (one found:
  `Admin\CashCollectionController` hardcodes `role_id === 3` for delivery boys) before retiring anything.
- Define the full role set from master prompt Section 6 as Spatie roles/permissions.
- Implement tenant scoping (global scopes/policies) verified against real controllers, plus IDOR tests.
- Extract FormRequest/Policy classes as each domain's endpoints are touched — none exist today; the three
  monolithic `ApiController`s (7.5k/5k/1.5k lines) validate inline (`INITIAL_CODEBASE_AUDIT.md` §5).

## Phase 3 — Commerce Core

- Extend existing products/orders/cart with what's missing (structured returns/RMA, order-origin
  discriminator for POS/affiliate/marketplace).

## Phase 4 — Vendor System

- Extend `stores`/`seller_store` with branch/warehouse assignment, employee model.

## Phase 5 — Inventory + Procurement

- Net-new: warehouses, stock movements, transfers, valuation, suppliers, POs, GRNs (see
  `DATABASE_GAP_ANALYSIS.md` §5). Produce `INVENTORY.md`.

## Phase 6 — POS

- Extend the existing `Seller\PosController`/`StockController` (real order-placement code, confirmed in
  source) rather than building from scratch: add shifts, till, split payments, cash reconciliation, wired
  to the same inventory/ledger as e-commerce. Produce `POS.md`.

## Phase 7 — Affiliate / Reseller Engine

- Net-new: link generation, click/conversion tracking, affiliate storefronts, commission rule engine.
  Produce `COMMISSION_ENGINE.md`.

## Phase 8 — Delivery

- Extend existing delivery-boy/parcel system with zones, dispatch, structured driver earnings. Produce
  `DELIVERY.md`.

## Phase 9 — Accounting + Unified Ledger

- Net-new: chart of accounts, journal entries, GL, AR/AP — the platform's financial foundation, every
  other module's money movement posts here. Produce `ACCOUNTING.md`.

## Phase 10 — Partners + Assets + Liabilities

- Net-new, built on the Phase 9 ledger.

## Phase 11 — CRM + Employees

- Net-new, built on existing `users`/`orders` history plus the Phase 4 employee model.

## Phase 12 — Analytics / BI

- Read-layer over everything above; no independent numbers. Produce `ANALYTICS.md`.

## Phase 13 — Mobile Applications

- Cannot be scoped until the Flutter source is available and inspected (see blocking item).

## Phase 14 — AI Analytics Layer

- API/service scaffolding only, per master prompt Section 34 — no hardcoded fake insights.

## Phase 15 — Security Hardening

- Full pass, building on Phase 1's `SECURITY_AUDIT.md` (which already found and fixed one destructive IDOR,
  documented a second, and flagged that the pattern likely recurs — a systematic sweep across the three
  monolithic API controllers is this phase's most concrete starting point) and `TECHNICAL_DEBT.md`'s
  `role_id = NULL` crash-risk finding: full IDOR sweep, RBAC redesign (dual role_id/Spatie mechanism →
  one), audit logging. Extend `SECURITY_AUDIT.md` rather than starting a new `SECURITY.md`.

## Phase 16 — Performance Optimization

- Indexing, caching, queue tuning, reporting-query optimization once real query patterns are visible.

## Phase 17 — Full QA and Production Readiness

- End-to-end verification against the master prompt's Section 51 standard before calling anything done.

## Rebranding Track (Section 47, can run in parallel once identified)

Prepare configurable logo/colors/app-name/email-branding architecture; do not begin actually stripping
eShop Plus branding until asked, since that's cosmetic work best sequenced after the functional phases
that touch the same views/components.
