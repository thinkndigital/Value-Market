# Phase 2 — Multi-Tenant Context & Tenant Isolation Audit (Tasks 6–7)

## 1. The tenant model (established in Phase 1, not re-litigated here)

`docs/PHASE_1_ARCHITECTURE.md` (Task G) already established, from source, that **`seller_data` (the
`Seller` model) is the real tenant/business-ownership boundary** in this codebase — `stores` is a separate,
orthogonal concept (a marketplace storefront channel, not a tenant container). This phase does not revisit
that decision; it builds the centralized enforcement Phase 1 identified as missing: *"What's missing isn't a
Company table; it's centralized enforcement of the scoping that's currently copy-pasted per controller
(Phase 2's job)."*

A second, narrower tenant concept covered by this phase (Task 5) is **per-user ownership** — a resource that
belongs to one specific end-user account rather than one seller (an `Address`, a `Transaction`/wallet entry,
a `User`'s own account). See `docs/PHASE_2_RBAC_ARCHITECTURE.md` for the six Policy classes that formalize
both kinds of ownership (seller-tenant and per-user).

## 2. `TenantContext` — the new resolver (Task 6)

`app/Services/TenantContext.php`, bound as a singleton in `AppServiceProvider`. Centralizes exactly the
query Phase 1's audit found **copy-pasted independently well over 90 times** across the Seller-panel
controllers alone (`Seller::where('user_id', $user->id)->value('id')`):

```php
$tenantContext = app(TenantContext::class);

$tenantContext->sellerIdFor($user);              // this user's seller_data id, or null
$tenantContext->currentSellerId();                // the authenticated user's seller_data id, or null
$tenantContext->userOwnsSeller($user, $sellerId);  // the actual ownership predicate
```

Per-user memoized (a singleton instance's internal array, keyed by user id) so repeated calls in one request
don't re-query — proven by `tests/Feature/Phase2/TenantContextTest.php`'s `DB::listen()`-based test, which
fails the test if a second call for the same user issues a query.

**Deliberately not force-wired into all ~90 existing call sites** — rewriting that many already-working
query-builder call sites in one pass is exactly the large, high-risk, behavior-preserving-only-by-luck
refactor this phase's master prompt rules out ("do not over-refactor... preserve exact existing behavior").
Wired into **6 real call sites** instead, proving actual usage rather than shipping an unused abstraction
(the same discipline Phase 1 applied to `ProductPolicy` — wired into one real call site, not left unused):

- `ProductPolicy::manage()` (replaces its own inline query)
- `OrderPolicy::view()` (seller branch)
- `OrderItemsPolicy::manage()` (seller branch)
- `Seller\ProductFaqController` — 4 methods, as part of the IDOR fix below
- `Seller\ComboProductFaqController` — 4 methods, same fix

New code from Task 8-9's systematic IDOR sweep should use `TenantContext` rather than adding another copy
of the inline pattern; existing correct call sites are left alone.

## 3. A second null-safety bug found while auditing (Task 6)

`AppServiceProvider`'s global `View::composer('*', ...)` did `$user->role->name` with **no null check**, on
**every page render for every logged-in user** — a more severe manifestation of the exact bug class Task 3
fixed at three other call sites (`Gate::before()`, `RoleMiddleware`, `CheckPermissions`), but missed here.
Any authenticated request with `role_id = NULL` or a dangling `role_id` would crash rendering *any* view,
not just an authorization check. Fixed the same way — `$user->isSuperAdmin()` instead of loading and reading
the relation, and `$user->role->name ?? null` for the `user_role` view variable (safe: all 66 Blade
references compare it with loose `== 'super_admin'`, so `null` degrades to "not super admin" rather than
granting anything or crashing). Regression test:
`RoleNullSafetyTest::test_view_composer_does_not_crash_rendering_a_page_for_a_roleless_user` — verified to
actually fail against the old code before confirming the fix (reverted, watched it throw
`ErrorException: Attempt to read property "name" on null`, then restored the fix).

## 4. Tenant isolation audit — every table with a `seller_id` column

The concrete, source-verified list of every table whose rows belong to one specific seller tenant (i.e.
every table carrying a `seller_id` foreign key in the baseline schema — the mechanical definition of
"tenant-sensitive" for this audit):

| Table | Model | Scoping status found | Evidence |
|---|---|---|---|
| `products` | `Product` | **Centralized for `update()` only; 2 further IDORs found and fixed (Task 16)** — `ProductPolicy` (Phase 1) was wired into `Seller\ProductController::update()` alone, not the whole controller as this row previously implied: `destroy()` and `update_status()` had the identical unscoped-by-id bug, now also gated by the same policy; `show()` had the store_id-only weak scoping seen elsewhere in this table, fixed the same way. | `tests/Feature/Phase1/ProductPolicyTest.php`, `tests/Feature/Phase2/SellerPanelIsolationTest.php` |
| `order_items` | `OrderItems` | **Centralized** for single-record ops — `OrderItemsPolicy` (Phase 2 Task 5); list queries scoped ad-hoc elsewhere (`Seller\OrderController` etc., matches Phase 1's finding) | `tests/Feature/Phase2/PolicyTenantIsolationTest.php` |
| `product_faqs` | `ProductFaq` | **Confirmed IDOR, found and fixed this task** — `destroy()`/`edit()`/`update_status()`/`update()` in `Seller\ProductFaqController` looked up by id alone, reachable via both the web panel and the app API (`Seller\v1\ApiController::edit_product_faq()`/`delete_product_faq()`) with no ownership check | `tests/Feature/Phase2/ProductFaqOwnershipTest.php` |
| `combo_product_faqs` | `ComboProductFaq` | **Confirmed IDOR, found and fixed this task** — identical bug in `Seller\ComboProductFaqController` | `tests/Feature/Phase2/ProductFaqOwnershipTest.php` |
| `pickup_locations` | `PickupLocation` | **No gap found** — only `store()`/`list()` exist for the Seller panel; `store()` derives `seller_id` server-side from `Auth::user()`, never trusts request input; `list()` scopes by `seller_id` when present. No single-record update/destroy-by-id method exists to be an IDOR target. | Direct read of `Seller\PickupLocationController.php` |
| `media` | `Media` | **Fixed (Task 16)** — `Seller\MediaController::destroy($id)` deleted any media row (and its underlying file) by id with zero ownership check; now scoped by `seller_id`. | `tests/Feature/Phase2/SellerPanelIsolationTest.php` |
| `seller_store` | `SellerStore` | **Audited (Task 16), sound** — every controller call site checked (`AreaController`, both `UserController`s, `Seller\OrderController`, `Admin\SellerController`) scopes by `seller_id`/`user_id`; no unscoped `::find($id)` found anywhere. | Direct read of all 8 call sites found via `grep -rn "SellerStore::find\|SellerStore::where"` |
| `seller_commissions` | `SellerCommission` | **Audited (Task 16), sound** — no direct `::find(`/`::where(` single-record controller lookup exists at all (`grep -rn "SellerCommission::find\|SellerCommission::where" app/Http/Controllers/` — zero matches). | — |
| `order_charges` | `OrderCharges` | **Audited (Task 16), sound** — same result: no direct single-record lookup exists; created/read alongside order items rather than as an independent CRUD resource. | — |
| `combo_products` | `ComboProduct` | **3 confirmed IDORs found and fixed (Task 16)** — the Product-equivalent methods in `Seller\ComboProductController` had never been touched by `ProductPolicy` (which is `Product`-specific): `destroy()`/`update_status()` were unscoped by id exactly like the now-fixed `Product` methods, and `update()` was Critical - its actual update query was scoped only by id, and since the payload includes `seller_id` set to the caller's own, an attacker could have reassigned ownership of the target record to themselves, not just edited it. `edit()` also had the same store_id-only (not seller_id) weak scoping as `Product::show()`, fixed the same way. | `tests/Feature/Phase2/SellerPanelIsolationTest.php` |
| `favorites` | `Favorite` | **Audited (Task 16), sound** — `App\v1\ApiController`'s add/remove/list methods all derive `user_id` server-side from `Auth::id()`, never trust request input for it. | Direct read of `app/Http/Controllers/App/v1/ApiController.php:1899-2103` |
| `images` | — | **No such Eloquent model is actually used** — the only `Image::` call sites in the codebase (`Seller`/`Admin\MediaController`) are `Intervention\Image`'s facade (`Image::make()`), a naming coincidence with the schema's `images` table, not `App\Models\Image` CRUD exposure. Nothing to audit here. | `grep -n "\bImage::" app/Http/Controllers/{Seller,Admin}/MediaController.php` |

**What this table does and doesn't establish**: it is a complete enumeration of every `seller_id`-bearing
table (mechanically derived from the baseline schema, not a sample) with an accurate status for each based
on the controller code actually read for it. It is **not** a full per-method sweep of every controller
touching these tables — that exhaustive pass (the master prompt's "~200+ methods") is Task 8-9's explicit,
separate deliverable (`docs/PHASE_2_IDOR_AUDIT.md`), which should treat every row marked "not independently
audited this task" as a starting worklist item, and carry `media` forward as an already-confirmed finding to
fix (severity/component/reason/remediation to be recorded there per the master prompt's final-report
format).

## 5. Verification performed for Tasks 6–7

- `php -l` clean on `TenantContext.php`, `AppServiceProvider.php`, both `*ProductFaqController.php` files,
  and every modified Policy.
- Full Feature test suite: **77 passed (126 assertions)** — up from 70 immediately after Task 5 (5 new
  `TenantContextTest` tests, 1 new view-composer null-safety test, 7 new `ProductFaqOwnershipTest` tests),
  zero regressions.
- `php artisan route:list --json` — **1,066 routes**, unchanged (this task adds no routes).
- The view-composer fix's regression test was verified to actually catch the bug: reverted the fix, watched
  the test fail with the exact `ErrorException` the bug produces, then restored the fix and re-ran green.
