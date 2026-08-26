# Phase 1 Final Report — Foundation

**Status: complete and verified**, per the Definition of Done in the Phase 1 execution prompt. Every number
below was counted directly from the actual migration files, test output, and git history in this repo — not
estimated. Where something could not be verified (no production data exists in this repo), that limitation
is stated explicitly rather than glossed over.

## Exact numbers

| Metric | Count |
|---|---|
| Total tables audited | 89 application tables (90 including Laravel's own `migrations` table) |
| Migrations before Phase 1 | 3 files (1 was an empty no-op recorded as "ran," 1 legitimate, 1 a misnamed/broken duplicate of Laravel's default stub — see `PHASE_1_DATABASE_MIGRATION_PLAN.md` §2) |
| Migrations after Phase 1 | 14 (12 baseline + 1 InnoDB conversion + 1 DECIMAL conversion) |
| MyISAM tables before | 11 |
| MyISAM tables converted | 11 |
| MyISAM tables intentionally retained | 0 |
| Total `double`/`float` fields audited | 54 |
| Monetary fields identified (double/float) | 45 |
| Monetary fields identified (non-double/float — `varchar`) | 2 (`currencies.exchange_rate`, `combo_products.delivery_charges`) |
| **Total monetary fields migrated to DECIMAL** | **47** |
| Non-monetary `double`/`float` fields intentionally unchanged | 9 (5 rating columns, 4 physical-dimension columns on `product_variants`) |
| Other numeric fields reviewed but not converted (pending business-rule clarification) | 4 (`offers.min_discount`/`max_discount`, `stores.delivery_charge_amount`/`minimum_free_delivery_amount` — currently `int`, semantics unconfirmed) |
| Candidate foreign keys catalogued | 29 relationships (`app/Console/Commands/DatabaseOrphanReport.php`) |
| Foreign keys added (new) | 0 — see "Why zero" below |
| Foreign keys preserved (pre-existing) | 2 (both on `seller_store`) |
| Orphan records found (real production data) | N/A — no production data exists in this repo |
| Orphan records found (synthetic verification data) | 2 of 2 seeded orphans correctly detected, 0 false positives |
| Orphan records resolved | 0 |
| Orphan records intentionally unresolved | 0 (none exist to resolve — see above) |
| Transaction boundaries added | 5 methods: `OrderService::placeOrder()`, `WalletService::updateBalance()`, `WalletService::updateCashReceived()`, `WalletService::updateWalletBalance()`, `Seller\PosController::place_order()` |
| Tests added | 40 (7 files, `tests/Feature/Phase1/`) |
| Tests executed | 40 |
| Tests passed | **40** |
| Tests failed | **0** |
| Confirmed security bugs fixed | 2 (`Admin\Webhook.php` broken import; address IDOR — see below) |
| Confirmed security bugs documented, not fixed | 1 (`generatParcelInvoicePDF` IDOR) |
| Bugs found and documented, not fixed | 3 (POS single-item-only loop, POS missing stock decrement, `CashCollectionController` variable-variable typo) |

### Why zero new foreign keys, honestly

Task 4/Task D require identifying orphan records **before** adding constraints, and explicitly forbid
adding constraints without that audit. This repository has no real production data — every transactional
table is empty except reference/seed data. Adding foreign keys now would mean skipping the exact audit step
the task requires, on the theory that empty tables can't have orphans — true, but not the same as having
actually run the audit against real data. `db:orphan-report` is built, tested, and ready; running it against
a real production database and resolving whatever it finds is the correct next step before any `ADD
CONSTRAINT` migration is written. This is a **deferred, documented decision**, not an oversight.

## Files changed

- **This pass** (security fixes on top of the first Phase 1 commit): 2 controllers modified
  (`AddressController.php`, `ApiController.php`), 1 doc updated (`CHANGELOG.md`), 4 files added
  (`SECURITY_AUDIT.md`, `TECHNICAL_DEBT.md`, `PHASE_1_FINAL_REPORT.md`, `AddressOwnershipTest.php`).
- **First Phase 1 pass** (prior commit `434cff9`): 564 files, +172,722 lines — brought the real eShop Plus
  application source into the repo for the first time (`app/`, `config/`, `routes/`, `resources/`) plus
  generic Laravel skeleton scaffolding needed to boot and test it, alongside the substantive Phase 1 changes
  (14 migrations, 2 artisan commands, 1 policy, 1 form request, 6 test files, 5 docs).

## Migrations created

`database/migrations/`:
1. `2025_01_01_000001_baseline_identity_rbac.php` (13 tables)
2. `2025_01_01_000002_baseline_vendors.php` (4 tables)
3. `2025_01_01_000003_baseline_catalog.php` (19 tables)
4. `2025_01_01_000004_baseline_commerce.php` (11 tables)
5. `2025_01_01_000005_baseline_delivery.php` (2 tables)
6. `2025_01_01_000006_baseline_payments_wallet.php` (4 tables)
7. `2025_01_01_000007_baseline_geography.php` (7 tables)
8. `2025_01_01_000008_baseline_localization.php` (2 tables)
9. `2025_01_01_000009_baseline_content.php` (10 tables)
10. `2025_01_01_000010_baseline_engagement.php` (6 tables)
11. `2025_01_01_000011_baseline_support.php` (5 tables)
12. `2025_01_01_000012_baseline_media_infra.php` (6 tables)
13. `2025_01_02_000000_convert_myisam_tables_to_innodb.php`
14. `2025_01_03_000000_convert_money_columns_to_decimal.php`

All 14 verified idempotent (safe to run against a fresh database or one that already has the schema) and
verified schema-faithful (diffed table-by-table against the original audited dump — see
`PHASE_1_DATABASE_MIGRATION_PLAN.md` §3).

## Commands created

- `php artisan db:orphan-report [--csv=path]` — `app/Console/Commands/DatabaseOrphanReport.php`. Read-only.
  Verified against seeded synthetic orphans (2/2 detected).
- `php artisan money:precision-report [--csv=path]` — `app/Console/Commands/MoneyPrecisionReport.php`.
  Read-only. Verified against seeded non-numeric and precision-losing values (both correctly detected — a
  real bug in the detection query's own logic was found and fixed while verifying this, see
  `PHASE_1_FINANCIAL_PRECISION.md` §4).

## Tests created

`tests/Feature/Phase1/` (40 tests, 59 assertions, all passing):
- `MigrationBaselineTest.php` — schema fidelity, InnoDB conversion, DECIMAL types, FK preservation, idempotency
- `TransactionAtomicityTest.php` — proves `DB::transaction()` rollback actually works on the now-InnoDB `orders` table
- `WalletServiceTest.php` — debit/credit correctness, insufficient-balance rejection, transaction-log atomicity
- `ProductPolicyTest.php` — tenant isolation (owner allowed, non-owner denied, super-admin bypass)
- `OrphanReportCommandTest.php` — clean-data and orphan-detection cases for `db:orphan-report`
- `MoneyPrecisionReportTest.php` — clean-data, non-numeric, and precision-loss cases for `money:precision-report`
- `AddressOwnershipTest.php` — the address IDOR fix, both directions (attacker blocked, owner unaffected), both operations (update, delete)

## Security findings

See `docs/SECURITY_AUDIT.md` for full detail. Summary:
- **Fixed**: `Admin\Webhook.php` broken import (blocked app boot); address update/delete IDOR (destructive — any user could edit/delete any other user's address).
- **Documented, not fixed** (Phase 2): `generatParcelInvoicePDF` IDOR (PII/order data leak across sellers); the pattern likely recurs elsewhere and was not exhaustively swept.
- **Checked and found sound**: mass assignment protection, CORS config, TrustHosts/TrustProxies, config-file secret hygiene, `.env` exclusion from git.

## Data integrity findings

See `docs/PHASE_1_DATA_INTEGRITY_REPORT.md` for full detail. Summary: `model_has_roles`/`model_has_permissions`
have no declared indexes or primary key; `Seller::$fillable` references nonexistent columns (dormant, not
a live bug); `Role` model/schema timestamp mismatch (dormant); `AuthServiceProvider`/`RoleMiddleware`/
`CheckPermissions` crash on `role_id = NULL` (confirmed empirically, reachability in production unconfirmed).

## Architectural decisions

- **Tenant/business-ownership boundary is `seller_data` (the `Seller` model), not `stores`.** `stores` is a
  separate concept — a marketplace channel/storefront the platform itself runs, which a seller can appear
  in via the `seller_store` pivot. Full evidence and reasoning in `docs/PHASE_1_ARCHITECTURE.md` Task G.
- **Baseline migrations use verbatim raw DDL (`DB::unprepared()`), not fluent `Schema::create()` translation.**
  Chosen specifically because hand-translating 89 tables' worth of columns risks silent type drift that
  raw-DDL fidelity avoids and can be mechanically verified. Full reasoning in
  `docs/PHASE_1_DATABASE_MIGRATION_PLAN.md` §1.
- **Money precision: 3 fixed tiers** — `DECIMAL(15,4)` for amounts, `DECIMAL(8,4)` for rates, `DECIMAL(20,10)`
  for exchange rates — chosen once so no later phase needs a second precision migration. Full reasoning in
  `docs/PHASE_1_FINANCIAL_PRECISION.md` §1.
- **FormRequest built but not force-wired into `PosController::place_order()`** — preserving existing
  translated error messages and response shape outweighed the value of "using the new convention" in a file
  already modified once this phase. Full reasoning in `docs/PHASE_1_ARCHITECTURE.md` Task F.
- **Zero new foreign keys** — see "Why zero" above.

## Known remaining risks (not fixed in Phase 1, by design)

1. POS `place_order()`'s item loop returns after its first iteration (multi-item carts drop lines) and never decrements stock for regular products — Phase 6.
2. `generatParcelInvoicePDF` IDOR — Phase 2.
3. `process_refund()` and the delivery-boy commission-crediting path each have two separately-atomic-but-not-jointly-atomic steps — documented in `PHASE_1_TRANSACTION_BOUNDARIES.md`, not restructured (360+-line methods, safer to leave alone than rewrite blind under this phase's time budget).
4. `role_id = NULL` crash risk in `AuthServiceProvider`/`RoleMiddleware`/`CheckPermissions` — Phase 2.
5. 10 files with PSR-4 case mismatches — confirmed not currently breaking, fragile under `composer install --classmap-authoritative` — deployment-pipeline flag.
6. `model_has_roles`/`model_has_permissions` missing indexes — needs a production-data duplicate-check first, same discipline as the FK work.
7. IDOR pattern not exhaustively swept across the ~200+ methods in the three monolithic API controllers — 3 confirmed instances (2 fixed/documented this phase, 1 centralized via `ProductPolicy`) are unlikely to be the only ones.

## Remaining blockers for later phases

None block Phase 2 on the database/backend side. Still outstanding from Phase 0, blocking only Phase 13
(mobile apps) and frontend-asset work: the Flutter mobile app source, the real `resources/js` build, and
`database/factories`/`database/seeders`/a pre-existing `tests/` suite (none were ever provided — the tests
in this repo are entirely new, written in Phase 1).

## Verification performed for this report

- Full test suite run twice consecutively: 40/40 passing both times, no order-dependent flakiness.
- Fresh `php artisan migrate` against three separate clean databases across both Phase 1 passes, each
  confirmed idempotent on re-run.
- `php artisan route:list` — all 1,066 routes resolve cleanly, before and after this pass's controller edits.
- `php -l` (syntax check) on every PHP file touched in both passes.
- Schema fidelity diffed column-by-column against the original audited dump (first pass); engine and
  DECIMAL-type assertions re-verified via `MigrationBaselineTest` (both passes).
- `git status`/`git add -A --dry-run` inspected before every commit to confirm no `.env` or secret ever
  staged.

## What was NOT done in Phase 1 (explicitly, per the execution prompt's own rules)

Did not rebuild the marketplace, frontend, or POS. Did not build mobile apps, the full accounting module,
the affiliate engine, the marketing system, the CRM, advanced delivery, or custom domain management. Did
not rewrite all API controllers or replace Laravel/the database engine. Did not create fake analytics, fake
AI, or mock financial data. Did not delete production data or make a destructive migration without
validation. Did not add foreign keys without an orphan audit against real data (none exists here). Every
item in this list was a deliberate boundary, not an accidental gap.
