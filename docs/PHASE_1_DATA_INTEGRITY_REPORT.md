# Phase 1 — Data Integrity Report (Foreign Keys, Task D)

## 1. Scope and honest limitation

**This repository has no real production data.** The only data available in this session is the reference
seed data in the audited schema dump (countries, currencies, roles, permissions, languages, settings —
18 `INSERT` statements total) and whatever this session inserted for its own testing. Every transactional
table (`orders`, `order_items`, `products`, `wallet_transactions`, ...) is empty. This means:

- The orphan counts in this document are **not** a real production audit — they're a demonstration that the
  tooling works, run against effectively-empty tables.
- **Before adding any foreign key to a real eShop Plus production database, `php artisan db:orphan-report`
  must be re-run against that production data**, and every flagged row resolved or explicitly accepted,
  before the corresponding `ADD CONSTRAINT` migration is written. This report does not substitute for that.

## 2. What was built and verified

`app/Console/Commands/DatabaseOrphanReport.php` (`php artisan db:orphan-report [--csv=path]`) checks 29
relationships covering the tenant/financial/inventory core (see the file for the full list — every
`orders`/`order_items`/`products`/`wallet_transactions`/`seller_data` relationship, plus the identity chains
those depend on). It does not touch data — it only counts and reports, per Task D's explicit rule against
automatic deletion.

**Verified detection, not just written**: seeded two deliberate orphans into a scratch database (an
`orders` row with `user_id = 999` where no such user exists, a `products` row with `seller_id = 888` where
no such seller exists) and confirmed the command reported exactly those two, with zero false positives on
the legitimate rows around them. Automated as `tests/Feature/Phase1/OrphanReportCommandTest.php` (clean-data
and orphan-detection cases, both passing).

## 3. Why only 29 of ~88 implicit relationships are covered

The full schema has roughly 88 columns that behave like foreign keys without being declared as one (see
`docs/DATABASE_GAP_ANALYSIS.md` §2). Task D's scope for Phase 1 is the financial/tenant-critical core this
phase actually touches — orders, wallet, commerce, and the tenant chain (`users` → `seller_data` →
products/orders). Content/merchandising tables (`sliders`, `offers`, `faqs`, ...) and localization tables
were deliberately left out of this pass; extending `DatabaseOrphanReport::$relationships` to cover them is a
mechanical, low-risk addition for whichever later phase actually adds constraints on those tables.

## 4. Foreign keys **not** added in Phase 1

Per Task D ("add foreign keys incrementally where data integrity allows"), **no new foreign key constraints
were added to the schema in this phase.** The two pre-existing constraints on `seller_store` are preserved
exactly as-is in the baseline. Adding real constraints on the 29 relationships above requires the orphan
report to be run against actual production data first (§1) — that hasn't happened yet, so adding constraints
now would be exactly the "add without auditing existing data compatibility" the master prompt and this
phase's own rules forbid. This is deferred, not skipped: `docs/PHASE_1_DATABASE_MIGRATION_PLAN.md` §5 lays
out the order of operations (orphan report → resolve → add constraints) for whoever runs this against
production.

## 5. Other data-integrity findings surfaced while doing this work

Not orphan records, but relevant integrity findings discovered during Phase 1 verification, worth carrying
into later phases:

- **`model_has_roles` and `model_has_permissions` (Spatie's own pivot tables) have zero declared indexes or
  primary key** in the live schema — confirmed by the same absence in the audited dump that every other
  table's index section has. Spatie's own migrations normally add a composite primary key here; its absence
  is a performance and integrity gap on tables that get queried on every permission check. Not fixed in
  Phase 1 (adding an index is safe and additive, but doing it correctly requires confirming there are no
  duplicate rows already in production first — the same "audit before constraining" discipline as §4).
- **`Seller::$fillable`** (the Eloquent model) lists columns — `store_name`, `store_url`,
  `store_description`, `commission`, `account_number`, `bank_name`, `bank_code`, `account_name`,
  `address_proof`, `tax_name`, `tax_number`, `permissions` — **that do not exist on the `seller_data` table
  at all.** They live on `seller_store` instead. This was discovered writing `ProductPolicyTest.php` (a
  naive `Seller::create()` with those fields throws "Unknown column"). It does not appear to be a live bug
  — `Admin\SellerController`'s real seller-creation code builds a separate, correct array for `seller_data`
  and a separate one for `seller_store` — but the stale `$fillable` list is misleading and worth trimming in
  a later cleanup pass.
- **`Role` model doesn't disable `$timestamps`**, but the `roles` table has no `created_at`/`updated_at`
  columns. Dormant (nothing in the app creates roles through Eloquent — they're seeded via the SQL
  installer), but would throw immediately if anything ever did `Role::create(...)`.
- **`AuthServiceProvider`'s `Gate::before()` hook, `RoleMiddleware`, and `CheckPermissions` all do
  `$user->role->name` with no null check.** `users.role_id` is a nullable column, and this session's testing
  confirmed the crash empirically (`Attempt to read property "name" on null`) the moment a role-less user
  hits any of these three. Whether this is reachable in production depends on whether plain customers (who
  presumably have no admin/seller role) ever exercise a code path that calls `Gate::` or hits
  `RoleMiddleware`/`CheckPermissions` — worth a deliberate look in Phase 2 (RBAC), not fixed here since it's
  an authorization code change outside Phase 1's database-foundation scope.
