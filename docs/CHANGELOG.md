# Changelog

All notable changes to the Value Market transformation are recorded here, newest first. Entries reference
the phase and task they belong to per `docs/IMPLEMENTATION_ROADMAP.md`.

## Phase 1 — Foundation

### Security (Task 8, added in the second Phase 1 pass)
- Fixed a confirmed, destructive IDOR: `AddressController::store()`'s update path and `destroy()` operated
  purely by address id with no ownership check, so any authenticated customer could edit or delete any
  other customer's saved address. Both now verify the address belongs to the requesting user first.
  Verified with `tests/Feature/Phase1/AddressOwnershipTest.php` (4 tests: attacker blocked, legitimate
  owner unaffected, for both update and delete).
- Documented (not fixed — Phase 2) a second confirmed IDOR: `Seller\OrderController::generatParcelInvoicePDF()`
  lets any seller view another seller's order and customer PII by guessing a parcel id.
- Added `docs/SECURITY_AUDIT.md` and `docs/TECHNICAL_DEBT.md`, consolidating every security and
  technical-debt finding from both phases in one place.

### Database
- Added 12 baseline migrations (`database/migrations/2025_01_01_*_baseline_*.php`) reproducing the audited
  eShop Plus 1.0.6 schema (89 tables) verbatim, idempotent against both a fresh database and an existing
  production one. Retired the 3 pre-existing migration files (one empty no-op, one legitimate and preserved
  in spirit, one a misnamed/broken duplicate of Laravel's default stub — see
  `docs/PHASE_1_DATABASE_MIGRATION_PLAN.md` §2).
- Fixed one schema bug the literal dump couldn't run without: `wallet_transactions.last_updated`'s invalid
  `'0000-00-00 00:00:00'` default, rejected by MySQL/MariaDB strict mode (Laravel's default).
- Converted 11 MyISAM tables to InnoDB (`orders`, `products`, `wallet_transactions`, `return_requests`,
  `sections`, `settings`, `sliders`, `time_slots`, `notifications`, `favorites`,
  `delivery_boy_notifications`) — `database/migrations/2025_01_02_000000_convert_myisam_tables_to_innodb.php`.
- Converted 47 monetary/rate/exchange-rate columns from `double`/`float`/`varchar` to `DECIMAL` (3
  precision tiers) — `database/migrations/2025_01_03_000000_convert_money_columns_to_decimal.php`. Includes
  fixing `currencies.exchange_rate`, which was `varchar(256)`, not numeric at all.
- Added `php artisan db:orphan-report` and `php artisan money:precision-report` — read-only validation
  tooling for foreign-key and money-precision data audits, required to run against real production data
  before the corresponding constraints/migrations are applied there (none is available in this repo).

### Transaction boundaries
- `OrderService::placeOrder()`: wrapped the wallet-debit-through-stock-decrement span in a transaction with
  rollback on every failure path, fixing a pre-existing bug where a failed order creation could leave a
  customer's wallet debited with no order to show for it.
- `WalletService::updateBalance()`, `updateCashReceived()`, `updateWalletBalance()`: added
  `DB::transaction()` + `lockForUpdate()`, fixing a non-atomic read-modify-write race on user balance and
  making the balance-change + transaction-log write atomic together.
- `Seller\PosController::place_order()`: added the same transaction-boundary treatment. Found, and
  deliberately left unfixed pending Phase 6 (POS), two real bugs: the item loop returns after its first
  iteration (multi-item POS carts silently drop all but one line), and stock is never decremented for
  regular products in this path.

### Architecture
- Added `app/Policies/ProductPolicy.php`, wired into `Seller\ProductController::update()`, replacing an
  inline ownership check (and fixing a latent type-safety issue in it).
- Added `app/Http/Requests/Pos/PlaceOrderRequest.php` (built and tested, deliberately not wired into
  existing code — see `docs/PHASE_1_ARCHITECTURE.md` Task F for why).
- Resolved the Phase 0 open question: `seller_data` (not `stores`) is the tenant/business-ownership
  boundary in this codebase (`docs/PHASE_1_ARCHITECTURE.md` Task G).

### Bugs found and fixed (required to verify this phase's work)
- `app/Http/Controllers/Admin/Webhook.php`: fixed an import of a non-existent class
  (`App\Http\Controllers\TransactionController` → `App\Http\Controllers\Admin\TransactionController`) that
  would fatally error on any payment webhook request (Paystack/PhonePe/Razorpay).

### Bugs found and documented, not fixed (out of Phase 1's scope)
- POS multi-item order bug and missing stock decrement (see above) — Phase 6.
- `CartService::addToCart()` returns `false` for a brand-new cart item (POS never passes `$fromApp=true`),
  and separately crashes outright on a cart with more than one distinct new product (`Undefined array key
  1`, from indexing a single-element `store_id` array per item) — both found by automated test
  (`PosSaleTest.php`), not by reading code — Phase 6.
- `generatParcelInvoicePDF` IDOR (see Security section above) — Phase 2.
- 10 files with PSR-4 namespace/directory case mismatches — confirmed not currently breaking (PHP resolves
  them via fallback), but fragile under `composer install --classmap-authoritative` — flagged for
  deployment-pipeline attention.
- `AuthServiceProvider`/`RoleMiddleware`/`CheckPermissions` all crash on a user with `role_id = NULL` (a
  legitimate, nullable state) — `docs/PHASE_1_DATA_INTEGRITY_REPORT.md` §5 — Phase 2 (RBAC).
- `Seller::$fillable` lists columns that don't exist on `seller_data` (they belong to `seller_store`) — not
  a live bug (the real seller-creation controller code doesn't hit it), but misleading — cleanup candidate.
- `Role` model doesn't disable `$timestamps` despite `roles` having no timestamp columns — dormant.
- `model_has_roles`/`model_has_permissions` (Spatie's own pivot tables) have no declared indexes or primary
  key in the live schema.

### Tests
- `tests/Feature/Phase1/` — 9 test classes, 47 tests, 79 assertions, all passing: migration baseline
  fidelity, transaction atomicity (proven on the real InnoDB-converted `orders` table), wallet service
  correctness, tenant-isolation policy enforcement, both new artisan commands' actual detection behavior
  (seeded orphans/bad values, confirmed caught), the address-ownership IDOR fix (attacker blocked, owner
  unaffected), stock-update correctness, and a real end-to-end POS sale — which surfaced two further
  pre-existing POS bugs (`CartService::addToCart()` failing on any brand-new cart item, and crashing
  outright on a genuine multi-item new-product cart) that reading the code alone hadn't found.
- Configured `phpunit.xml` to run against a real MySQL/MariaDB database rather than sqlite — the baseline
  migrations use MySQL-specific raw DDL that sqlite cannot execute.

### Documentation
- Added `docs/PHASE_1_DATABASE_MIGRATION_PLAN.md`, `docs/PHASE_1_DATA_INTEGRITY_REPORT.md`,
  `docs/PHASE_1_FINANCIAL_PRECISION.md`, `docs/PHASE_1_TRANSACTION_BOUNDARIES.md`,
  `docs/PHASE_1_ARCHITECTURE.md`, `docs/SECURITY_AUDIT.md`, `docs/TECHNICAL_DEBT.md`,
  `docs/PHASE_1_FINAL_REPORT.md`, this changelog.

## Phase 0 — Audit

- Added `docs/INITIAL_CODEBASE_AUDIT.md`, `docs/TARGET_ARCHITECTURE.md`, `docs/DATABASE_GAP_ANALYSIS.md`,
  `docs/EXISTING_FEATURE_MATRIX.md`, `docs/IMPLEMENTATION_ROADMAP.md` — first from dependency manifests and
  a full database schema dump, then corrected and substantially extended once the actual Laravel backend
  source was made available (see `INITIAL_CODEBASE_AUDIT.md`'s own revision note on the Inertia/React
  admin-panel correction).
