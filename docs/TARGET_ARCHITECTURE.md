# Target Architecture — Value Market (All-in-One Commerce & Business Operating System)

This document maps the master prompt's target architecture onto the eShop Plus 1.0.6 foundation
(`INITIAL_CODEBASE_AUDIT.md`), stating what layer already exists, what layer extends existing structure,
and what layer is genuinely new. It intentionally defers implementation detail (exact table columns, API
routes) to the phase-specific docs (`ACCOUNTING.md`, `COMMISSION_ENGINE.md`, etc.) that get written as
each phase starts, per the master prompt's phase-execution rule.

## 1. Guiding Decisions Carried Over From the Audit

- **Build on Laravel 10 / Sanctum / Spatie Permission, with the existing server-rendered Blade admin,
  seller, and delivery-boy panels.** (Source inspection corrected an earlier draft of this doc set that
  assumed an Inertia+React admin panel from `package.json` alone — no `Inertia::render()` calls,
  no `inertiajs/inertia-laravel`, and no Livewire components exist in source; those packages are vestigial.
  See `INITIAL_CODEBASE_AUDIT.md` §1.) Nothing in the audit justifies a framework replacement; the gaps are
  almost entirely missing *domain* schema and modules, not a wrong foundation. Whether the admin surface
  stays Blade or moves to a modern SPA is a deliberate Phase 1 decision, not an assumption either way.
- **Collapse to one RBAC system** (Spatie Permission) before layering the new user types from Section 6 of
  the master prompt onto it. The legacy `role_id`/`user_permissions` path is retired, not extended.
- **Tenant unit = `stores`.** The existing schema already treats `stores` as the vendor/business boundary.
  The master prompt's "Companies" concept in Section 4 maps onto `stores` rather than requiring a brand-new
  layer above it — **open question for Phase 1**: does the target platform need a `companies` tier *above*
  `stores` (one company owning multiple stores/brands) as literal multi-company, or does "multi-company"
  mean "the platform hosts many independent vendor companies," which today's `stores` model already is?
  This must be decided with the user before the org-structure migration is written, since it changes every
  downstream table's tenant key.
- **Branches/warehouses/locations are new, nested under the tenant unit** decided above.

## 2. Layered Target Architecture

```
Platform (Super Admin)
│
├── Identity & RBAC Layer                    [Spatie Permission — extend role/permission set]
│
├── Tenant Layer                              [stores today → confirm company/branch nesting in Phase 1]
│   ├── Companies (pending decision above)
│   ├── Branches                              [NEW]
│   ├── Warehouses                            [NEW]
│   └── Vendor Stores                         [EXISTING — stores, seller_store, seller_data]
│
├── Commerce Engine                           [EXISTING, extend]
│   ├── Products / Categories / Attributes    [EXISTING]
│   ├── Cart / Checkout / Orders              [EXISTING]
│   ├── Payments (5+ gateways wired)          [EXISTING]
│   ├── Promotions / Coupons                  [EXISTING]
│   └── Returns                               [PARTIAL — extend into full RMA flow]
│
├── Inventory & Procurement Engine            [NEW]
│   ├── Stock (location-aware)                [NEW — today: single int per product/variant]
│   ├── Stock Movements / Transfers           [NEW]
│   ├── Valuation (FIFO / weighted-avg)       [NEW]
│   └── Suppliers / POs / GRNs                [NEW]
│
├── POS Engine                                [NEW, hangs off Commerce+Inventory+Accounting]
│   ├── Shifts / Till / Cash Reconciliation   [NEW — today: is_pos_order flag only]
│   └── Split Payments                        [NEW]
│
├── Affiliate / Reseller Engine               [NEW, extends referral_code plumbing]
│   ├── Link Generation / Click Tracking      [NEW]
│   ├── Affiliate Storefronts                 [NEW]
│   └── Commission Rules + Ledger             [NEW — extends seller_commissions]
│
├── Delivery Engine                           [PARTIAL, extend]
│   ├── Delivery Boys / OTP delivery          [EXISTING]
│   ├── Zones / Dispatch                      [EXISTING geography, NEW dispatch logic]
│   └── Driver Earnings / Cash Recon          [PARTIAL — fund_transfers exists, needs structure]
│
├── Accounting Engine                         [NEW — the platform's financial foundation]
│   ├── Chart of Accounts                     [NEW]
│   ├── Journal Entries / General Ledger      [NEW]
│   ├── AR / AP                               [NEW]
│   ├── Expenses / Assets / Liabilities       [NEW]
│   └── Partners / Shareholders               [NEW]
│
├── Unified Ledger                            [NEW — every module above posts here]
│
├── CRM & Employees                           [NEW]
│
├── Analytics / BI                            [NEW — reads from the above, never a separate source of truth]
│   └── AI Insights layer                     [NEW, late phase]
│
├── Notifications                             [EXISTING — FCM push wired; extend to email/SMS/in-app abstraction]
│
└── Mobile Apps (Customer / Vendor / Affiliate / Delivery)   [status unverified — see audit §9-10]
```

## 3. Source-of-Truth Rules (binding for every subsequent phase)

Per the master prompt's Section 50, these are fixed for the whole build and must not be violated by any
later phase's schema:

1. **Inventory**: one stock ledger. POS and e-commerce read/write the same stock rows — no separate
   "POS inventory" table.
2. **Orders**: one `orders`/`order_items` model extended (not duplicated) to carry POS, affiliate, and
   marketplace order origin as a discriminator column, not a parallel table.
3. **Financial transactions**: one ledger (journal entries). `wallet_transactions`, `transactions`,
   commission payouts, expense payments, and POS cash movements all ultimately post journal entries there;
   they don't each maintain their own balance independently of the ledger.
4. **Identity**: one `users` table remains authoritative; new user types (Section 6 of the master prompt)
   are roles/profiles layered on top via Spatie Permission + a per-type profile table (e.g. `employees`,
   `affiliates`) that references `users.id`, not a second identity table.
5. **Tenant**: whatever unit Phase 1 settles on (see §1) is authoritative; every new table carries that
   tenant key and every query is scoped through it — enforced in code (global scopes / policies), since
   the schema audit found the database will not enforce this on its own (2 FKs in 90 tables).
6. **Analytics**: reporting tables/materialized views are read-optimized *copies* derived from the sources
   above, rebuilt on a schedule or via events — never a place where a number is written that doesn't also
   exist in its source module.

## 4. Accounting-Impact Checklist (applied per module, per the master prompt's Section 49)

Every new module design in every future phase must answer, and the answer must be reflected in that
module's migration/service design before it's considered done:

Inventory change? · Revenue? · Expense? · Payable? · Receivable? · Cash/bank change? · Equity impact?
Commission created? · Tax impact?

This checklist is the acceptance gate for Phases 3–11 in `IMPLEMENTATION_ROADMAP.md`.

## 5. What This Document Deliberately Does Not Yet Decide

- Exact company/branch/warehouse table shapes (Phase 1, after the multi-company question in §1 is
  resolved with the user).
- Exact chart-of-accounts structure and journal-entry schema (documented in `ACCOUNTING.md` when Phase 9
  starts).
- Exact commission rule DSL (documented in `COMMISSION_ENGINE.md` when Phase 7 starts).
- API versioning/namespace conventions beyond what the master prompt already specifies
  (`/api/v1/{admin,vendor,customer,affiliate,delivery}/...`), since the *existing* API surface is
  unverified — Phase 2 starts with cataloguing it, not assuming it's empty.
