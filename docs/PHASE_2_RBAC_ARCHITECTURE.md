# Phase 2 — RBAC Architecture Decision & Hardcoded Role Check Replacement (Tasks 2–4)

## 1. Architectural decision: keep `users.role_id` as the authoritative coarse-grained role, do not migrate to Spatie roles this phase

**Decision**: the legacy `users.role_id` → `roles` table remains the single source of truth for "what kind
of user is this" (super_admin / admin / editor / seller / delivery_boy / customer). Spatie's role mechanism
is **not** activated or migrated to in Phase 2.

**Why**, based on `PHASE_2_RBAC_AUDIT.md`:

- Spatie's role mechanism is already completely vestigial in production data — 0 rows in `model_has_roles`,
  0 rows in `role_has_permissions`, 0 calls to `hasRole()` anywhere in the codebase. Activating it now would
  mean inventing a role-assignment dataset that doesn't exist today, not "turning on" something latent.
- All three web route groups (`admin_routes.php`, `seller_routes.php`, `delivery_boy_routes.php`), the
  `role:` middleware, `Gate::before()`, and `CheckPermissions`'s super-admin bypass all resolve identity via
  the legacy `role_id` → `roles.name` relation. Switching the source of truth would require touching every
  one of those simultaneously and re-verifying ~1,066 routes in one pass — far outside "fix authorization
  bugs without rebuilding the RBAC system," which this phase's master prompt explicitly rules out.
- Spatie's **permission** mechanism (`permissions:` middleware, `hasPermissionTo()`, per-user
  `syncPermissions()`) is genuinely used and stays exactly as-is — this decision only concerns the unused
  *role* half of Spatie.

**What Phase 2 does instead**: eliminates the *symptom* the audit found dangerous — 43+ call sites each
re-deriving "is this user a seller/delivery-boy/customer/admin" from a raw `role_id` integer literal,
independently, with no null safety — by centralizing that logic into named `Role` constants and `User`
semantic helper methods (Task 4, §3 below), without changing which mechanism is authoritative. A future
phase can revisit whether to formally migrate to Spatie roles; this phase makes that decision reversible by
funneling every check through one place (`App\Models\Role` constants, `User::is*()` methods) instead of
scattered magic numbers.

## 2. `Role` constants and `User` semantic helpers added (Task 3/4 foundation)

`app/Models/Role.php` gained 6 public class constants, verified against the real seeded `roles` table
(`database/migrations/2025_02_01_000000_seed_roles_reference_data.php`):

```php
const SUPER_ADMIN  = 1;
const CUSTOMER     = 2;  // roles.name = 'members'
const DELIVERY_BOY = 3;
const SELLER       = 4;
const ADMIN        = 5;
const EDITOR       = 6;
```

`app/Models/User.php` gained 7 null-safe semantic methods, all comparing `(int) $this->role_id` against
these constants (no relation load, no possibility of the `$user->role->name` null-pointer crash fixed in
Task 3 — see `RoleNullSafetyTest.php`):

`isSuperAdmin()`, `isAdmin()`, `isEditor()`, `isSeller()`, `isDeliveryBoy()`, `isCustomer()`,
`isPlatformStaff()` (= super_admin OR admin OR editor).

## 3. Role-NULL safety fixes (Task 3)

Three call sites did `$user->role->name` (or equivalent) with no null check — a crash for any user with
`role_id = NULL` (a legitimate, nullable column) or a dangling `role_id` (no FK constraint enforces the
reference). Fixed by routing through the null-safe helpers above instead of loading the relation:

| File | Before | After |
|---|---|---|
| `app/Providers/AuthServiceProvider.php` (`Gate::before()`) | `$user->role->name == 'super_admin'` | `$user->isSuperAdmin()` |
| `app/Http/Middleware/RoleMiddleware.php` | `$user->role->name` accessed unconditionally | Null-checked (`$user !== null && $user->role !== null`) before access; denies (throws `UnauthorizedException`) instead of crashing |
| `app/Http/Middleware/CheckPermissions.php` | `$user_role = $user->role; $role_name = $user_role->name;` | `$user->isSuperAdmin()`; unauthorized JSON response also now correctly returns HTTP 403 (was previously implicit 200) |

Also discovered in this pass: the Phase 1 baseline migrations reproduced the `roles` table's *structure*
but never its 6 seed rows — a fresh install would have had an empty `roles` table and no way to designate a
super admin at all. Fixed with a new idempotent seed migration
(`2025_02_01_000000_seed_roles_reference_data.php`). Documented as a Phase 1 gap discovered during Phase 2
work; see `docs/TECHNICAL_DEBT.md`.

Regression coverage: `tests/Feature/Phase2/RoleNullSafetyTest.php` (5 tests) — proves each of the three
previously-crashing paths now degrades to a denial instead of an `ErrorException`, and that super-admin
status is still granted correctly using the real seeded role.

## 4. Hardcoded `role_id` replacement — full inventory (Task 4)

**Every** hardcoded numeric `role_id` comparison found by exhaustive per-file grep sweep (not just the
initial audit's estimate) was replaced. The Task 1 audit's initial grep-based estimate was **43 sites across
~20 files**; the actual completed sweep — which re-grepped each file individually before editing, catching
several occurrences the initial broad grep missed or undercounted — found and fixed **50 distinct hardcoded
sites across 19 files**. Post-fix, a repo-wide sweep for the pattern
`role_id (==|!=|===|!==) <digit>` / `where('role_id', <digit>)` / `'role_id' => <digit>` outside of
`Role::` constant usage returns **zero matches** — confirmed by direct grep, not by count alone.

Two replacement styles, chosen by call-site semantics (documented per-file below) — this deliberately
preserves exact existing behavior while removing every magic number:

- **Semantic `User::is*()` method** — used where the check answers "is this specific user (typically
  the authenticated user, or a loaded `User` model instance) a seller/delivery-boy/customer," i.e. an
  authorization-style gate on a concrete user object.
- **`Role::CONSTANT`** — used where the value feeds a query builder (`where('role_id', ...)`),
  an array literal, or a mass-assignment/insert payload — contexts where a named integer constant is the
  natural replacement for a magic number, not a boolean predicate.

| # | File | Site(s) | Before → After |
|---|---|---|---|
| 1 | `app/function_helper.php` | `countDeliveryBoys()`, `getDeliveryBoys()` | `role_id`, `3` → `Role::DELIVERY_BOY` |
| 2 | `app/function_helper.php` | `count_new_user()` | `role_id`, `2` → `Role::CUSTOMER` |
| 3 | `app/Services/OrderService.php` | 2 sites (order-fetching scope check) | `$user->role_id == 3` → `$user->isDeliveryBoy()` |
| 4 | `app/Http/Controllers/Seller/v1/ApiController.php` | 3 sites | `$user->role_id == 4` → `$user->isSeller()` |
| 5 | `app/Http/Controllers/Seller/v1/ApiController.php` | 1 site | `$user->role_id != 4` → `!$user->isSeller()` |
| 6 | `app/Http/Controllers/Seller/v1/ApiController.php` | 1 site | `User::where(...)->where('role_id', 4)` → `Role::SELLER` |
| 7 | `app/Http/Controllers/Seller/PosController.php` | 2 sites | `User::where('role_id', 2)` → `Role::CUSTOMER` |
| 8 | `app/Http/Controllers/Seller/PosController.php` | 1 site | `'role_id' => 2` (insert payload) → `Role::CUSTOMER` |
| 9 | `app/Http/Controllers/Seller/PosController.php` | 1 site | `Auth::user()->role_id === 4` → `Auth::user()->isSeller()` |
| 10 | `app/Http/Controllers/Seller/ReturnRequestController.php` | 1 site | `User::where('role_id', 3)` → `Role::DELIVERY_BOY` |
| 11 | `app/Http/Controllers/Admin/ReturnRequestController.php` | 1 site | Same pattern → `Role::DELIVERY_BOY` |
| 12 | `app/Http/Controllers/Seller/OrderController.php` | 1 site | `User::with('city')->where('role_id', 3)` → `Role::DELIVERY_BOY` |
| 13 | `app/Http/Controllers/Seller/UserController.php` | 1 site | `'role_id' => 4` (insert payload) → `Role::SELLER` |
| 14 | `app/Http/Controllers/Admin/Delivery_boyController.php` | 1 site | `where('role_id', 3)` → `Role::DELIVERY_BOY` |
| 15 | `app/Http/Controllers/Admin/CashCollectionController.php` | 2 sites | `where('role_id', 3)` → `Role::DELIVERY_BOY` |
| 16 | `app/Http/Controllers/Admin/ManageStockController.php` | 2 sites | `where('users.role_id', 4)` → `Role::SELLER` |
| 17 | `app/Http/Controllers/Admin/SellerController.php` | 1 site (mobile lookup) | `where('role_id', 4)` → `Role::SELLER` |
| 18 | `app/Http/Controllers/Admin/SellerController.php` | 2 sites | `'role_id' => 4` (insert payload) → `Role::SELLER` |
| 19 | `app/Http/Controllers/Admin/SellerController.php` | 1 site | `$q->where('role_id', 4); // Seller` → `Role::SELLER` |
| 20 | `app/Http/Controllers/Admin/SellerController.php` | 2 sites | `->where('role_id', 4)` → `Role::SELLER` |
| 21 | `app/Http/Controllers/Admin/UserController.php` | 1 site | `$formFields['role_id'] = 2` → `Role::CUSTOMER` |
| 22 | `app/Http/Controllers/Admin/UserController.php` | 1 site | `where('role_id', '!=', '4')` → `Role::SELLER` |
| 23 | `app/Http/Controllers/Admin/UserController.php` | 1 site | `where('users.role_id', '4')` → `Role::SELLER` |
| 24 | `app/Http/Controllers/Admin/UserController.php` | 2 sites | `User::where('role_id', 2)` → `Role::CUSTOMER` |
| 25 | `app/Http/Controllers/Admin/OrderController.php` | 4 sites | `User::where('role_id', 3)` → `Role::DELIVERY_BOY` |
| 26 | `app/Http/Controllers/Admin/NotificationController.php` | 1 site | `$fcm->user->role_id == 4` (closure) → `$fcm->user->isSeller()` |
| 27 | `app/Http/Controllers/Admin/NotificationController.php` | 1 site | `$fcm->user->role_id != 4` (closure) → `!$fcm->user->isSeller()` |
| 28 | `app/Http/Controllers/Admin/NotificationController.php` | 1 site | `User::where(...)->where('role_id', 4)` → `Role::SELLER` |
| 29 | `app/Http/Controllers/Delivery_boy/v1/ApiController.php` | 2 sites | `$user->role_id == 3` → `$user->isDeliveryBoy()` |
| 30 | `app/Http/Controllers/Delivery_boy/v1/ApiController.php` | 1 site | `$user_id->role_id != 3` → `!$user_id->isDeliveryBoy()` |
| 31 | `app/Http/Controllers/Delivery_boy/v1/ApiController.php` | 1 site | `$user['role_id'] == '3'` → `$user->isDeliveryBoy()` |
| 32 | `app/Http/Controllers/UserController.php` (root) | 1 site | `'role_id' => 2` (registration payload) → `Role::CUSTOMER` |
| 33 | `app/Http/Controllers/App/v1/ApiController.php` | 2 sites | `'role_id' => 2` (registration payload) → `Role::CUSTOMER` |
| 34 | `app/Http/Controllers/vendor/Chatify/MessagesController.php` | 1 site | `Auth::user()->role_id == 2` → `Auth::user()->isCustomer()` |
| 35 | `app/Http/Controllers/vendor/Chatify/MessagesController.php` | 1 site | `whereNotIn('role_id', [2, 3])` → `[Role::CUSTOMER, Role::DELIVERY_BOY]` |

Sites deliberately **left unchanged** (not hardcoded magic numbers, so out of Task 4's scope):

- `app/Http/Controllers/Admin/SellerController.php` `wallet_transactions_list()` and
  `App/v1/ApiController.php` (two sites) / `App/v1/ApiController.php` line ~4805 — all resolve `role_id`
  dynamically via `Role::where('name', $roleName)->value('id')` or `fetchDetails(Role::class, ['id' =>
  $role_id])`, i.e. they already look the role up by name/id rather than embedding a magic number.
- `app/Http/Controllers/Delivery_boy/v1/ApiController.php` line 706 — `fetchDetails(User::class, [...],
  'role_id')` is a column-selection string, not a comparison.

## 5. Verification performed for Task 4

- `php -l` on all 19 modified controller/service/model files plus `Role.php`, `User.php`,
  `AuthServiceProvider.php`, `RoleMiddleware.php`, `CheckPermissions.php` — zero syntax errors.
- Repo-wide grep sweep for the hardcoded pattern (`role_id (==|!=|===|!==) \d`, `where('role_id', \d)`,
  `'role_id' => \d`) — zero remaining matches outside `Role::` constant usage.
- Full Feature test suite: **52 passed (86 assertions)**, same as immediately after Task 3 — no regressions
  introduced by Task 4.
- `php artisan route:list --json` — **1,066 routes**, matching the Phase 1/Task 3 baseline exactly (Task 4
  touches no routing files).
- One line-ending defect caught and fixed during verification: an earlier Python-scripted edit to
  `app/Services/OrderService.php` (predating this pass) had silently converted the file from CRLF to LF on
  write, producing a 6,274-line `git diff` for what was actually a 2-line content change. Restored CRLF to
  match the rest of the file and `HEAD`, confirmed the diff collapsed to the real 2-line change, and
  re-verified `php -l` and the full test suite afterward.
