# Phase 2 Final Report — Security Hardening

**Status: complete.** Every number below was counted directly from git history, test output, and the actual
source in this repo — not estimated. This report synthesizes the whole phase; the individual task docs
(`PHASE_2_RBAC_ARCHITECTURE.md`, `PHASE_2_RBAC_AUDIT.md`, `PHASE_2_MULTITENANCY.md`, `PHASE_2_IDOR_AUDIT.md`,
`PHASE_2_MASS_ASSIGNMENT_AUDIT.md`) carry the full evidence and reasoning for each finding; this report is
the index and the numbers.

## Exact numbers

| Metric | Count |
|---|---|
| Commits this phase | 19 |
| Confirmed vulnerabilities fixed (one per distinct method/route) | 28 |
| — Critical | 8 |
| — High | 16 |
| — Medium/Low | 4 |
| Confirmed vulnerabilities documented, not fixed (with reason + remediation plan) | 2 |
| New Phase 2 test files | 14 |
| New Phase 2 tests | 101 |
| Total test suite (Phase 1 + Phase 2) | 148 passing, 0 failing |
| Total test suite at Phase 2 start | 47 (Phase 1 only) |
| Production-breaking bugs fixed (unrelated to security, found while restoring a real deploy) | 6 |
| Files changed (app/routes/config/tests/docs, excludes the vendor asset restoration) | 30 |

## Critical vulnerabilities fixed

| # | Finding | Component | Fix commit |
|---|---|---|---|
| 1 | `admin.stores.index` was reachable with **no authentication at all** — a duplicate unguarded route shadowed the properly `auth`+`role`-gated one, the same class of bug already fixed once for an invoice-PDF IDOR before this phase | `routes/web.php`, `routes/admin_routes.php` | `057471b` |
| 2 | `Seller\v1\ApiController::delete_order()` — any seller could permanently delete any other seller's order by id, no check at all | `app/Http/Controllers/Seller/v1/ApiController.php` | `9a23abb` |
| 3 | `Admin\MediaController::upload()` accepted any file type — a `.php` upload was directly executable via its public URL (remote code execution) | `app/Http/Controllers/Admin/MediaController.php` | `84ad1f2` |
| 4 | `Seller\MediaController::upload()` — the same RCE, reachable by any self-registered seller account (much lower trust than admin staff) | `app/Http/Controllers/Seller/MediaController.php` | `84ad1f2` |
| 5 | `UserPermissionController::store()` — any editor-role account with `create system_user` could grant themselves/an accomplice the Super Admin role, no existing super admin required | `app/Http/Controllers/Admin/UserPermissionController.php` | `92cfde4` |
| 6 | `Seller\ComboProductController::update()` — unscoped by seller, and would have reassigned ownership of the target record to the attacker (not just edited it) | `app/Http/Controllers/Seller/ComboProductController.php` | `1322653` |
| 7 | `UserPermissionController::destroy()` — no restriction on target user at all; could delete any user in the database, including Super Admins | `app/Http/Controllers/Admin/UserPermissionController.php` | `7af8ca2` |
| 8 | `UserPermissionController::delete_selected_data()` — the bulk-delete equivalent of #7, and its route had **no permission gate at all** (unlike its own `destroy()` sibling) | `app/Http/Controllers/Admin/UserPermissionController.php`, `routes/admin_routes.php` | `7af8ca2` |

## High-severity vulnerabilities fixed

Seller/delivery-boy panel IDORs (each: an attacker of the same role type could act on another's record by
guessing an id, no ownership check):

- `Seller\v1\ApiController`: `delete_product()`, `update_product_status()`, `update_product_deliverability()`,
  `update_combo_product_deliverability()`, `delete_combo_product()`, `delete_order_parcel()` (6 findings,
  `9a23abb`)
- `Delivery_boy\v1\ApiController::update_returned_order_item_status()` (`9a23abb`)
- `Seller\ProductController`: `destroy()`, `update_status()` — the web-panel equivalents of the above, never
  covered by `ProductPolicy` despite that policy existing (`1322653`)
- `Seller\ComboProductController`: `destroy()`, `update_status()` (`1322653`)
- `Seller\MediaController::destroy()` — delete any media file by id (`1322653`)
- `Delivery_boy\OrderController::edit()` — `$delivery_boy_id` computed but never passed to the scoping query;
  any delivery boy could view any other's assigned parcel (`b96adf1`)
- `App\v1\ApiController`: `delete_product_rating()`, `delete_combo_product_rating()` — any customer could
  delete any other customer's review (`a4a0f8f`)

Response security:

- Six hand-built user-response arrays (customer + delivery-boy login, registration/social-login, profile
  update) leaked the account's password hash and forgotten-password/activation/remember-me tokens directly
  in the JSON response, on every login (`94d27d9`)

## Medium/low-severity vulnerabilities fixed

| # | Finding | Fix commit |
|---|---|---|
| 1 | `admin/cronjob/settleCashbackDiscount` — unauthenticated, triggers a wallet-cashback settlement run | `8deff1d` |
| 2 | `admin/cronjob/sendCartReminders` — unauthenticated, burns the site's paid Gemini/OpenRouter API quota | `8deff1d` |
| 3 | `Seller\ComboProductController::edit()` — scoped by `store_id` only, not `seller_id`, letting one seller view another's combo-product listing within a shared store | `1322653` |
| 4 | `Seller\ProductController::show()` — the same `store_id`-only weak scoping, for the equivalent Product view | `1322653` |

Not vulnerabilities, but production-breaking bugs found and fixed alongside this phase's security work while
restoring a real deploy (counted separately in "Production-breaking bugs fixed" above, not in the 28):
`public/assets` (the entire theme's JS/CSS bundle) missing from the repo entirely (`8171bc9`); an always-true
`empty()`-on-a-Collection guard crashing the shared header on every page once a fresh install had no matching
language row (`7cff4d0`); a redirect loop between the store-setup and purchase-code-registration pages on a
fresh install (`2012710`); a stale Phase 1 test assertion (`038716f`).

## Documented, not fixed this phase (with remediation plan)

| Finding | Severity | Why not fixed now | Doc |
|---|---|---|---|
| `Model::unguard()` called globally (every request) in `AppServiceProvider::boot()` — defeats every model's `$fillable`/`$guarded` declaration application-wide | Critical (systemic) | Safely removing it requires auditing every write path across ~200+ controller methods; a wrong removal fails silently (a dropped field) rather than loudly. Practical exploitability confirmed low today (no `::create($request->all())` anti-pattern exists), but the defense-in-depth layer is gone. | `PHASE_2_MASS_ASSIGNMENT_AUDIT.md` |
| `Seller\PosController::update_user_address()` — overwrites any Address row's contact/location fields by id, no ownership check | Critical (write) | Correct fix needs to verify what customer-identifying data is actually available in the POS frontend flow at update time — risked guessing at a fix that breaks a working workflow | `PHASE_2_IDOR_AUDIT.md` §4 |

(The 8 findings originally catalogued in `PHASE_2_IDOR_AUDIT.md` §5b as "not yet fixed" were all fixed this
phase — see the High-severity table above, commit `9a23abb` — and are no longer open.)

## Verification performed

- Full test suite run repeatedly across every commit this phase, not just at the end: **148/148 passing**,
  zero regressions introduced at any point.
- `php -l` clean on every touched PHP file.
- `php artisan route:list` — clean, no errors, after every routing change.
- Fresh `php artisan migrate:fresh` + idempotent re-run verified against a real MySQL/MariaDB instance (not
  SQLite), matching this app's actual production driver.
- Every fix proven in both directions where applicable: the attacker is denied AND the legitimate
  owner/actor is not blocked by the fix (avoiding the common mistake of an overly broad guard that breaks
  real usage).
- The two most severe findings (media-upload RCE, system-user privilege escalation) were independently
  re-derived from source reading, not assumed from a prior pass — each traced from route → controller →
  actual query/file-write before being called a confirmed finding.

## What Phase 2 did not do (explicitly, scope boundaries)

Did not remove `Model::unguard()` (documented instead — see above). Did not redesign
`Seller\PosController::update_user_address()`'s ownership model (documented instead). Did not build a
general-purpose audit-logging system — `auditLog()` is wired into the Super Admin privilege-boundary events
found this phase only, not every write in the app. Did not perform an exhaustive line-by-line review of
every one of the ~200+ methods across the three monolithic API controllers — each pass targeted the
highest-confidence, highest-impact category first (documents, deletions, privilege grants, response bodies)
and named what it didn't cover rather than implying full coverage. Did not rewrite the dual `role_id`/Spatie
permission mechanism into one system (`IMPLEMENTATION_ROADMAP.md`'s Phase 15 still lists this as open) — this
phase closed the *access-control bugs* in the existing dual mechanism, not the mechanism's own duplication.
