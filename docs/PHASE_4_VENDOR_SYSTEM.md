# Phase 4 — Vendor System: Branches & Employees

`docs/IMPLEMENTATION_ROADMAP.md` (Phase 4) scopes this as: *"Extend `stores`/`seller_store` with
branch/warehouse assignment, employee model."* `docs/DATABASE_GAP_ANALYSIS.md` §5 confirmed both are
genuinely absent from the real eShop Plus schema — "Warehouses / Branches" and "Employees" both listed as
"None." This phase delivers the tenant-owned parts of that gap: `branches` (physical locations a seller
owns) and `employees` (real login-capable staff, distinct from the seller owner). Warehouses and
location-aware stock (`stock_items`, `stock_movements`) are Phase 5's job — `branches` is the table Phase 5's
warehouse schema will reference, not something this phase tries to also build.

## 1. `branches`

New table, one row per physical location a seller owns: `name`, `address`, `city`/`zipcode` (reusing the
existing geography tables, per `DATABASE_GAP_ANALYSIS.md` §6's "reuse, don't reinvent" guidance),
`latitude`/`longitude`, `phone`, `is_default`, `status`. Scoped to `seller_data` (the tenant unit Phase 1's
architecture decision established — `PHASE_1_ARCHITECTURE.md` Task G), not `stores`: a seller can sell
through multiple stores (`seller_store`) but their physical locations belong to them as a business, not to
any one storefront.

**Schema note**: `seller_id`/`user_id` columns are plain signed `integer()`, not Laravel's default
`foreignId()` (unsigned bigint). `seller_data.id` and `users.id` are both legacy `int(11)` (see
`baseline_vendors.php`/`baseline_identity_rbac.php`) — using `foreignId()->constrained()` here would create a
bigint-vs-int type mismatch on the FK. No DB-level FK constraints are added, matching this codebase's
existing convention (Phase 1–3 migrations) of relationships enforced in the application layer.

`Seller\BranchController` gives the owning seller CRUD over their own branches (`GET/POST/POST/DELETE
seller/branches[/{id}]`), scoped via `TenantContext::currentSellerId()` — never a request-supplied
`seller_id`. Ownership-IDOR coverage: a seller cannot update or delete another seller's branch
(`tests/Feature/Phase4/BranchControllerTest.php`).

## 2. `employees`

New table: `seller_id` (employer), `branch_id` (nullable — not every employee is branch-specific),
`user_id` (their own login, unique), `position`, `permissions` (JSON, reserved for future fine-grained
scoping — not consumed by anything yet), `status`.

**`EmployeeService::create()`** is the only way an employee is created: it makes a real `users` row (role
`Role::SELLER`, so they log in through the exact same `/seller/login` endpoint every seller uses — no new
auth path) with `Hash::make()`'d password, then the `employees` row linking that user to the employer's
`seller_id` and (optionally) a branch, both inside one `DB::transaction()`.

`Seller\EmployeeController` gives the owning seller CRUD over their own staff, with the same
`TenantContext`-scoped ownership pattern as branches, plus one extra check: assigning an employee to a
`branch_id` verifies that branch belongs to the *same* seller first (a seller cannot silently assign staff
onto another seller's branch by guessing its id). `destroy()` deactivates (`status = 0`) rather than deletes
the `employees` row — deleting it would silently orphan a real `users` row that may already have order/audit
history attached, as a side effect of removing the *assignment*, which isn't what "delete employee" should
mean.

## 3. `TenantContext::sellerIdFor()` — the employee tenant-resolution point, and its real scope boundary

This is the one piece of this phase worth being precise about, because half-explaining it would be worse
than not mentioning it.

`TenantContext` (introduced in Phase 2, `PHASE_2_MULTITENANCY.md` Tasks 6–7) already existed as *the*
centralized resolver for "what `seller_id` does this user act as" — built specifically so new/fixed code
would have one correct place to ask, instead of adding another copy of the ~90-times-inlined
`Seller::where('user_id', $id)->value('id')` query Phase 1's architecture audit found scattered across the
Seller panel. Phase 2 explicitly declined to rewrite those ~90 existing call sites, calling it "exactly the
kind of large, high-risk refactor this phase's master prompt rules out."

This phase extends `sellerIdFor()` itself: if a user doesn't own a `seller_data` row directly, it now falls
back to checking for an active `employees` row and resolves to *their employer's* `seller_id` instead.

**What this does cover**, automatically, with no further work: every Phase 2 IDOR fix, every Phase 3 fix,
and this phase's own `Branch`/`EmployeeController` — anything that already goes through `TenantContext`
correctly recognizes an employee as acting on behalf of their employer.

**What this does NOT cover**: the ~90 pre-existing Seller-panel controllers (product management, order
management, POS, reports, and more) that inline the raw `Seller::where('user_id', ...)->value('id')` query
directly instead of calling `TenantContext`. Those sites only recognize the literal owner's `user_id` — an
employee logging into `/seller/login` today authenticates successfully (same role, same middleware) but will
see an **empty** product/order/POS panel through any of those unmigrated controllers, because their inline
lookup finds no `seller_data` row for the employee's own `user_id` and never consults `employees` at all.

This is a real, working piece of infrastructure — not a stub — but it is *not* "employees can now run the
seller panel." Making that true requires the same ~90-site rewrite Phase 2 correctly deferred, done
carefully (each site needs to move from the inline query to `TenantContext::currentSellerId()`, verified
against real Seller-panel functionality one controller at a time, the same discipline this repo has applied
to every other cross-cutting change). That is flagged here as explicit, scoped follow-up work — not folded
into this phase, and not silently declared done.

## 4. Tests

`tests/Feature/Phase4/` (4 new files, 13 new tests):

- `TenantContextEmployeeTest.php` (4 tests) — an owner resolves to their own `seller_id`; an active employee
  resolves to their employer's; a deactivated employee resolves to `null`; a user with neither resolves to
  `null`.
- `BranchControllerTest.php` (4 tests) — a seller can create/list their own branches; cannot update or
  delete another seller's branch; can update their own.
- `EmployeeServiceTest.php` (1 test) — creates a real, login-capable, correctly-hashed `users` row plus the
  linking `employees` row.
- `EmployeeControllerTest.php` (4 tests) — a seller can create/list their own employees; cannot assign an
  employee onto another seller's branch; cannot update another seller's employee; `destroy()` deactivates
  rather than deletes (the linked `users` row survives).

`tests/Feature/Phase1/MigrationBaselineTest.php`'s table-count assertion updated 93 → 95 for the two new
tables (a direct, expected consequence of this phase's migration, not a regression).

Full suite: **181 passing** (168 before this phase), zero regressions.

## 5. What this phase does not do

No `branches`/`employees` admin or seller-panel UI (Blade views) — this phase delivers the backend
(migration, models, service, controller, tests), matching how Phase 3 also extended existing flows via API
parameters rather than new screens. No warehouse or location-aware stock (Phase 5). No department model
beyond `position` (a free-text field) — a structured department/role hierarchy wasn't asked for by the
roadmap's one-line scope and would be speculative to add now. No fine-grained permission enforcement on the
`employees.permissions` JSON column — it's captured and stored, but nothing reads or enforces it yet; wiring
it up is naturally sequenced after (or alongside) the ~90-site `TenantContext` migration above, since
per-employee permission checks are meaningless until employees can actually reach the panels they'd be
scoped within.
