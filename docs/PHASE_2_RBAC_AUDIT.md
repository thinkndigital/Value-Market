# Phase 2 — RBAC Audit (Task 1)

Full source-tree audit of every authorization mechanism in the codebase, source-verified (grep + direct
inspection of the actual `roles`/`permissions`/`model_has_roles`/`model_has_permissions`/
`role_has_permissions` table contents in the audited schema dump), not inferred.

## 1. The `roles` table — ground truth

```
id | name          | description
1  | super_admin   | Administrator
2  | members       | General User (i.e. customer)
3  | delivery_boy  | Delivery Boys
4  | seller        | Sellers
5  | admin         | Admin
6  | editor        | Editor
```

Every hardcoded `role_id` comparison in the codebase (Task 4) is checked against **these real values**,
not assumed.

## 2. Two coexisting mechanisms, precisely characterized

### 2a. Legacy: `users.role_id` → `roles` table (the actual coarse-grained role gate)

- **94 references to `role_id`** across **27 files**.
- **43 of those are hardcoded numeric comparisons** (`role_id == 3`, `where('role_id', 4)`, etc.) — full
  inventory in `PHASE_2_RBAC_ARCHITECTURE.md` Task 4 section.
- **This is the mechanism that actually gates every web-panel route.** All three web route files wrap
  their entire route set in one outer group:
  - `admin_routes.php`: `['auth', 'role:super_admin,admin,editor', 'CheckPurchaseCode', 'CheckStoreNotEmpty']`
  - `seller_routes.php`: `['auth', 'role:seller', 'CheckPurchaseCode']`
  - `delivery_boy_routes.php`: `['auth', 'role:delivery_boy', 'CheckPurchaseCode']`
  - `role:` middleware (`RoleMiddleware`) resolves via `$user->role->name` — the legacy `belongsTo(Role::class)`
    relation, **not** Spatie.

### 2b. Spatie `laravel-permission` — permissions only, roles unused, assigned per-user not per-role

- **Spatie's role mechanism is completely vestigial.** Zero rows in `model_has_roles` (verified against the
  audited schema's seed data) and **zero calls to `hasRole()` anywhere in the codebase** (grepped).
  `HasRoles` is used on the `User` model only because `HasPermissions` doesn't exist as a standalone trait
  without it — it's a required dependency of the permissions half, not something the app actually uses.
- **Permissions are assigned directly per-user, not via roles.** `Admin\UserPermissionController::update()`
  calls `$user->syncPermissions($permissions)` — every admin/editor user gets their own individually
  curated permission set from the 277-row `permissions` table (`create address`, `edit products`, etc.),
  not a shared role-based bundle. `role_has_permissions` is correspondingly unused (0 rows in seed data,
  and no code path found that would populate it).
- **Enforcement**: `CheckPermissions` middleware (`permissions:` alias), used **178 times** in
  `admin_routes.php` and once in `web.php` — always as a second, more granular gate layered *under* the
  outer `role:` gate, e.g. `role:super_admin,admin,editor` at the route-group level, then
  `permissions:edit,products` on the specific "edit product" route. `CheckPermissions` itself bypasses
  entirely for `role_name === 'super_admin'` — using the **legacy** `$user->role` relation for that check,
  not Spatie.
- `hasPermissionTo()` — used in exactly 2 places: inside `CheckPermissions` itself, and in
  `UserPermissionController::edit()` to pre-check which permission checkboxes should render as checked in
  the UI.

### 2c. `Gate` / `Policy` — new in Phase 1, still minimal

- `AuthServiceProvider::Gate::before()` — pre-existing, grants `super_admin` (via the legacy `$user->role`
  relation) every ability unconditionally.
- `ProductPolicy` (Phase 1) — the only Policy class in the codebase, wired into exactly one call site
  (`Seller\ProductController::update()`).
- No other `Gate::`, `->authorize(`, `@can`, or Policy usage found anywhere else in `app/` or `resources/views`.

### 2d. No `isAdmin()`/`isSeller()`/`isDelivery()`/`isCustomer()` semantic helpers exist anywhere

Grepped for these and equivalents — none found on `User` or any other model. Every one of the 43 hardcoded
checks re-derives "is this a seller/delivery-boy/customer" from a raw `role_id` integer at its own call
site, independently.

## 3. Categorization of every authorization mechanism found

| # | Mechanism | Category | Where |
|---|---|---|---|
| 1 | `role:` middleware (web route groups) | Legacy RBAC | 3 route-group declarations, gates ~1,000+ routes |
| 2 | `RoleMiddleware`'s per-route role args | Legacy RBAC | Same 3 groups |
| 3 | `permissions:` middleware | Spatie RBAC (permission-only) | 179 route declarations, `admin_routes.php`/`web.php` |
| 4 | `CheckPermissions`'s super-admin bypass | Legacy RBAC (reads `$user->role`, not Spatie) | `app/Http/Middleware/CheckPermissions.php` |
| 5 | `Gate::before()` super-admin bypass | Legacy RBAC (reads `$user->role`) | `AuthServiceProvider` |
| 6 | `hasPermissionTo()` direct calls | Spatie RBAC | `CheckPermissions`, `UserPermissionController` |
| 7 | 43 hardcoded `role_id == N` comparisons | Direct role checks | 20 files — full list in `PHASE_2_RBAC_ARCHITECTURE.md` |
| 8 | `ProductPolicy` | Policy (ownership check) | Wired into 1 call site (Phase 1) |
| 9 | Ad-hoc `Seller::where('user_id', Auth::id())->value('id')` then `->where('seller_id', $sellerId)` pattern | Ownership check (not centralized) | Throughout `Seller\*Controller` — see `PHASE_2_MULTITENANCY.md` |
| 10 | `AddressController` ownership check (Phase 1 fix) | Ownership check (centralized in one controller) | `app/Http/Controllers/Admin/AddressController.php` |
| 11 | `check_token`/`auth:sanctum` (API) | Authentication only, not authorization | `api.php`, `seller_api.php`, `delivery_boy_api.php` |
| 12 | Resource loads by ID with no ownership/tenant check at all | **Unprotected** | See `PHASE_2_IDOR_AUDIT.md` for the full sweep |
| 13 | Everything not yet reviewed at method level | Unknown / requires investigation | See `PHASE_2_IDOR_AUDIT.md` coverage statement |

## 4. What this audit does and doesn't establish

**Establishes with confidence**: the outer authentication/role gate is comprehensive and consistent across
all three web panels and the API — nothing found is missing `auth`+`role:` at the route-group level. The
actual, confirmed gap (Phase 0/1's IDOR findings, and this phase's further sweep — `PHASE_2_IDOR_AUDIT.md`)
is entirely *inside* that gate: individual controller methods trusting a request-supplied ID as sufficient
proof of ownership.

**Does not establish**: full per-endpoint authorization correctness for all ~1,066 routes — see
`PHASE_2_IDOR_AUDIT.md` for exactly what was and wasn't covered by the systematic sweep, and why.
