# Phase 4 Final Report — Vendor System (Branches & Employees)

**Status: complete for the scope delivered — see the explicit boundary below.** `docs/PHASE_4_VENDOR_SYSTEM.md`
carries the full design/implementation detail; this report is the index and the numbers.

## Exact numbers

| Metric | Count |
|---|---|
| Commits this phase | 1 |
| New tables | 2 (`branches`, `employees`) |
| New models | 2 (`Branch`, `Employee`) |
| New services | 1 (`EmployeeService`) |
| New controllers | 2 (`Seller\BranchController`, `Seller\EmployeeController`) |
| New routes | 8 (4 branch + 4 employee, all under the existing `auth`/`role:seller`/`CheckPurchaseCode` group) |
| New Phase 4 test files | 4 |
| New Phase 4 tests | 13 |
| Existing test updated (not a regression — expected table-count shift) | 1 (`MigrationBaselineTest`, 93 → 95) |
| Total test suite (Phase 1–4) | 181 passing, 0 failing |
| Total test suite at Phase 4 start | 168 |

## What changed

- `branches`: physical locations owned by a `seller_data` tenant. `Seller\BranchController` gives the owning
  seller CRUD, scoped via `TenantContext` (never a request-supplied id).
- `employees`: real login-capable staff (their own `users` row, role `seller`) linked to an employer's
  `seller_id` and optionally a `branch_id`. `EmployeeService::create()` is the single creation path — hashed
  password, one DB transaction. `Seller\EmployeeController` gives the owning seller CRUD, including
  rejecting an attempt to assign staff onto another seller's branch.
- `TenantContext::sellerIdFor()` extended to resolve an active employee to their employer's `seller_id` —
  see below for exactly what this does and doesn't unlock.

## The one thing to read carefully: employee panel access is partial by design, not by oversight

An employee's `users` row logs in successfully through the same `/seller/login` every seller uses (same
role, same middleware — no new auth path was added). `TenantContext::sellerIdFor()` correctly resolves them
to their employer's `seller_id`, and everything that already goes through `TenantContext` — every Phase 2
IDOR fix, every Phase 3 fix, and this phase's own `Branch`/`EmployeeController` — recognizes them correctly
today.

What does **not** yet recognize them: the roughly 90 pre-existing Seller-panel controllers (products,
orders, POS, reports, and more) that inline the equivalent `Seller::where('user_id', ...)->value('id')`
query directly instead of calling `TenantContext`. Phase 2 explicitly declined to rewrite those sites in one
pass, calling it "exactly the kind of large, high-risk refactor this phase's master prompt rules out" — that
reasoning still holds, and this phase does not reverse it. An employee can log in today; they will see an
empty product/order/POS panel until each of those controllers is migrated to `TenantContext`, one at a time,
with the same verification discipline this repo has applied everywhere else.

**This is documented as explicit, scoped follow-up work**, not a phase marked "done" while quietly missing
its most visible use case. `TenantContext`'s docblock and `PHASE_4_VENDOR_SYSTEM.md` §3 both point back here.

## Documented, not fixed this phase (with reason)

| Finding | Why not fixed now | Doc |
|---|---|---|
| ~90 Seller-panel controllers don't yet recognize an employee login (see above) | Same large-surface-rewrite risk Phase 2 explicitly deferred; needs one-controller-at-a-time migration to `TenantContext`, each verified against real Seller-panel functionality | `PHASE_4_VENDOR_SYSTEM.md` §3 |
| `employees.permissions` JSON column is captured/stored but not read or enforced anywhere | Per-employee permission checks are meaningless until employees can actually reach the panels they'd be scoped within — naturally sequenced after the item above | `PHASE_4_VENDOR_SYSTEM.md` §5 |
| No `branches`/`employees` admin or seller-panel UI (Blade views) | This phase delivers the backend (migration/model/service/controller/tests), matching Phase 3's pattern of extending flows via API rather than new screens | `PHASE_4_VENDOR_SYSTEM.md` §5 |

## Verification performed

- Migration run against the real MariaDB instance this repo's test suite uses (not SQLite): clean, no
  errors, idempotent (`Schema::hasTable()` guards, matching every other migration in this repo).
- `php artisan route:list` confirms all 8 new routes registered under unique names — no collisions with the
  69 pre-existing duplicate route names documented as a separate, unrelated follow-up.
- `php -l` clean on every touched/new PHP file.
- Full suite run after the change: **181/181 passing**, zero regressions (the one test that needed updating —
  `MigrationBaselineTest`'s hardcoded table count — was a direct, expected consequence of adding two tables,
  not a regression; updated 93 → 95 with the reasoning left in the test's own comment).
- IDOR coverage from the start (not bolted on after): every ownership-sensitive endpoint
  (`Branch`/`EmployeeController`'s update/destroy, and the branch-assignment check in employee creation) has
  a test proving a non-owning seller is rejected and the target row is left unchanged.

## What Phase 4 did not do (explicitly, scope boundaries)

Did not build warehouses or location-aware stock (`stock_items`, `stock_movements`) — that's Phase 5,
which will reference `branches` rather than duplicate it. Did not build a structured department/role
hierarchy — `employees.position` is a free-text field; nothing in the roadmap's one-line scope asked for
more, and inventing one now would be speculative. Did not migrate the ~90 existing Seller-panel controllers
to recognize employee logins (see above). Did not build any new UI screens.
