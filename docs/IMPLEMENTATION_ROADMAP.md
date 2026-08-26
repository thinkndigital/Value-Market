# Implementation Roadmap — Value Market

This roadmap follows the master prompt's phase list (Section 43) and phase-execution rule (Section 44):
each phase is analyze → define → implement → migrate → test → fix → verify existing functionality →
document → phase report. No phase is marked complete on UI placeholders alone.

## Blocking Item — Must Be Resolved Before Phase 1 Can Start

**The actual application source code (Laravel `app/`, `routes/`, `resources/`, config, tests, and the
Flutter mobile apps) is not yet in this repository.** This Phase 0 audit was produced from
`composer.json/.lock`, `package.json/.lock`, and a full database dump only — see
`INITIAL_CODEBASE_AUDIT.md` for exactly what that does and doesn't prove.

Everything from Phase 2 onward in the master prompt depends on code-level facts this audit could not
verify: actual RBAC enforcement, actual API endpoints, actual controller/policy authorization, actual
mobile app structure, actual test coverage. Proceeding into those phases on schema-only evidence would
mean guessing at controller logic — exactly what the master prompt's "do not blindly assume" and "do not
claim completion without inspecting the actual files" rules forbid.

**Requested action**: push the extracted eShop Plus 1.0.6 source (backend + the two/three Flutter mobile
apps) into this repository, on this branch or a clearly-named subfolder. Once it lands, Phase 0 finishes
with a source-verified update to `INITIAL_CODEBASE_AUDIT.md` (API list, RBAC enforcement points, mobile
app inventory, real security findings) before Phase 1 architecture work begins.

## Phase 0 — Audit ✅ (this document set, partial as noted above)

Delivered: `INITIAL_CODEBASE_AUDIT.md`, `TARGET_ARCHITECTURE.md`, `DATABASE_GAP_ANALYSIS.md`,
`EXISTING_FEATURE_MATRIX.md`, this roadmap. Outstanding: source-verified update once code is available
(see blocking item).

## Phase 1 — Architecture & Database Design

- Resolve the multi-company-vs-multi-vendor tenant question (`TARGET_ARCHITECTURE.md` §1) with the user.
- Design `companies`/`branches`/`warehouses` schema nested under the resolved tenant unit.
- Write the additive migrations for Section 3–4 of `DATABASE_GAP_ANALYSIS.md`: MyISAM→InnoDB on the 11
  affected tables, `double`→`DECIMAL` money-column migration plan (parallel-write/verify/cutover, not a
  single destructive pass).
- Produce `DATABASE.md`.

## Phase 2 — RBAC + Multi-Tenancy

- Inspect actual Spatie usage and the legacy `role_id` path in source; retire the legacy path.
- Define the full role set from master prompt Section 6 as Spatie roles/permissions.
- Implement tenant scoping (global scopes/policies) verified against real controllers, plus IDOR tests.

## Phase 3 — Commerce Core

- Extend existing products/orders/cart with what's missing (structured returns/RMA, order-origin
  discriminator for POS/affiliate/marketplace).

## Phase 4 — Vendor System

- Extend `stores`/`seller_store` with branch/warehouse assignment, employee model.

## Phase 5 — Inventory + Procurement

- Net-new: warehouses, stock movements, transfers, valuation, suppliers, POs, GRNs (see
  `DATABASE_GAP_ANALYSIS.md` §5). Produce `INVENTORY.md`.

## Phase 6 — POS

- Net-new: shifts, till, split payments, cash reconciliation, wired to the same inventory/ledger as
  e-commerce. Produce `POS.md`.

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

- Full pass once source is available: IDOR testing, the CodeIgniter-pattern auth columns flagged in
  `INITIAL_CODEBASE_AUDIT.md` §11, audit logging. Produce `SECURITY.md`.

## Phase 16 — Performance Optimization

- Indexing, caching, queue tuning, reporting-query optimization once real query patterns are visible.

## Phase 17 — Full QA and Production Readiness

- End-to-end verification against the master prompt's Section 51 standard before calling anything done.

## Rebranding Track (Section 47, can run in parallel once identified)

Prepare configurable logo/colors/app-name/email-branding architecture; do not begin actually stripping
eShop Plus branding until asked, since that's cosmetic work best sequenced after the functional phases
that touch the same views/components.
