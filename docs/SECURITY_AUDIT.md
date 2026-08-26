# Security Audit — Phase 1 (Task 8)

**Scope**: a focused Phase 1 security review, not a full penetration test and not the RBAC redesign
Phase 2 owns. Everything below was verified against actual source and, where noted, actual runtime
behavior (tests, `php artisan tinker`) — not inferred from reading alone. This document doesn't repeat
findings already fully written up elsewhere; it cross-references them and adds what's new from this pass.

## 1. Confirmed IDOR vulnerabilities

### 1a. `delete_address` / `update_address` — FIXED in this phase

**Before this phase**: `Admin\AddressController::destroy()` did `Address::find($id)->delete()` with no
ownership check anywhere in the call chain; `store()`'s update path did
`updateDetails($addressData, ['id' => $request->input('id')], Address::class)`, also with no ownership
check. **Any authenticated customer could delete or modify any other customer's saved address by passing
its numeric id** — both are destructive/integrity-violating, not just information disclosure, and address
IDs are small sequential integers (trivially enumerable).

**Fix**: both methods now verify the target address's `user_id` matches the requesting user before making
any change; `destroy()` gained an optional `?int $requestingUserId` parameter (its one call site was
updated to pass it — grepped for other callers first, there are none, so this couldn't have broken an
existing legitimate use).

**Verified, not just written**: `tests/Feature/Phase1/AddressOwnershipTest.php` — 4 tests: an attacker
cannot delete or update another user's address; the legitimate owner still can, for both operations. All
passing. (The "owner can still update" test initially passed for the wrong reason — the update wasn't
running at all for either owner or attacker, because `AddressController::store()` reads input via the
global `request()` helper, not its `$request` parameter, and a manually-constructed test `Request` isn't
automatically bound to that helper. Caught by writing the negative test's counterpart, not left as a false
positive — see the test file's own comment.)

**Known residual gap in the fix**: `delete_address`'s caller (`App\v1\ApiController`) still returns
`"Address Deleted Successfully"` unconditionally, regardless of whether `destroy()` actually deleted
anything — this was already true before the fix (the original code never checked `destroy()`'s return
value either) and wasn't expanded in scope to change the response contract. The important property — that
another user's data cannot be touched — now holds; the response text being uninformative about a
silently-blocked attempt is a smaller, pre-existing issue.

### 1b. `Seller\OrderController::generatParcelInvoicePDF()` — documented, NOT fixed

`generatParcelInvoicePDF($id)` fetches a parcel and generates an invoice PDF from it
(`fetchDetails(Parcel::class, ['id' => $id])`) with **no check that the parcel belongs to the
authenticated seller** before rendering order details, product names, prices, delivery-boy info, and the
customer's mobile number into the response. `$seller_id` is computed and used later for *enriching* line
items, but the initial parcel/order lookup is unscoped. **Any authenticated seller can view another
seller's (and that seller's customer's) order and contact details by guessing/incrementing a parcel ID.**

**Not fixed in this phase.** Unlike the address case, this method has more downstream logic that assumes
`$parcels[0]` exists and multiple enrichment steps after it; a safe fix means deciding what should happen
on a mismatch (redirect, 403 JSON, empty PDF) in a way that doesn't break the legitimate multi-seller-order
PDF case this method also seems to handle (a parcel can span order items from multiple sellers — see the
`$seller_ids`/`$seller_user_ids` handling). That's a judgment call belonging to a proper security/RBAC pass
(Phase 2), not a Phase 1 database-foundation change made under time pressure to a method not otherwise
touched this phase.

### 1c. Pattern likely recurs elsewhere — not exhaustively swept

These two were found by spot-checking a handful of methods, not by auditing all ~200+ controller methods
across the three monolithic API controllers (`docs/TECHNICAL_DEBT.md`) — that volume of manual review is
disproportionate for "a focused Phase 1 security review." The `Seller\ProductController` ownership check
Phase 1 already centralized into `ProductPolicy` (`docs/PHASE_1_ARCHITECTURE.md` Task F) is the third
confirmed instance of the same underlying pattern: ownership scoping implemented ad-hoc, inconsistently, or
not at all, per controller method. **Recommendation for Phase 2**: a systematic IDOR sweep of all
seller/customer-scoped endpoints, informed by the tenant model Phase 1 already established (`seller_data`
is the tenant boundary — `docs/PHASE_1_ARCHITECTURE.md` Task G), is a named, explicit Phase 2 deliverable,
not an assumption that Phase 1's two fixes covered the problem.

## 2. Areas checked and found sound (verified, not assumed)

- **Mass assignment**: no model declares `$guarded = []`. 10 models (`Order`, `OrderItems`,
  `OrderCharges`, `SellerStore`, `Role`, others) declare neither `$fillable` nor `$guarded`, which means
  Laravel's own default (`$guarded = ['*']` — nothing mass-assignable) applies; these are maximally
  protected, not exposed. Where the app needs to write to those tables anyway, it uses `forceCreate()`
  (11 call sites, all grepped and checked) — every one of them builds its data array from hand-curated,
  server-side-constructed fields, never `$request->all()` or an unfiltered `$request->only([...])` with a
  broad field list. No `::create($request->all())` anti-pattern found anywhere in `app/`.
- **CORS** (`config/cors.php`): `allowed_origins => ['*']` with `supports_credentials => false` — this
  combination is safe (the dangerous case is wildcard origin *with* credentials, which browsers reject
  outright anyway); this API authenticates via Sanctum bearer tokens, not cookies, so `false` here is
  correct, not an oversight.
- **TrustHosts**: uses Laravel's safe default (`allSubdomainsOfApplicationUrl()`), not a wildcard.
- **TrustProxies**: `$proxies` is `null` (Laravel's safe default — no proxy headers trusted until
  explicitly configured for a real reverse-proxy deployment).
- **Config files** (`services.php`, `mail.php`, `filesystems.php`, `database.php`, `sanctum.php`): grepped
  for anything that looks like a hardcoded credential/key literal instead of an `env()` call — none found.
- **No secrets committed**: `.env` is git-ignored (confirmed — `git status`/`git add -A --dry-run` show it
  excluded) and was never staged. `.env.example` contains only placeholder values, consistent with the
  vanilla Laravel skeleton (`APP_KEY=` empty, `PUSHER_APP_KEY=` empty, etc.) — nothing that resembles a
  real secret.

## 3. Operational reminder (not a code defect)

`.env.example` ships `APP_DEBUG=true` / `APP_ENV=local` — Laravel's own standard local-development
defaults. This is not a vulnerability in this repository (it's a template, not the real production `.env`,
which was never provided or committed), but it's worth stating plainly for whoever deploys this: **production
must set `APP_DEBUG=false`**. With it `true`, an unhandled exception renders a full stack trace, including
environment variable values, to any visitor — a critical misconfiguration if it ever ships that way. This
belongs in `docs/DEPLOYMENT.md` when that's written (Phase 17), flagged here so it isn't lost.

## 4. Findings already documented elsewhere (cross-referenced, not repeated)

- **Tenant isolation / authorization**: `docs/PHASE_1_ARCHITECTURE.md` Task G (the `seller_data`-as-tenant
  decision) and Task F (the `ProductPolicy` fix). `docs/PHASE_1_DATA_INTEGRITY_REPORT.md` §5 covers the
  `AuthServiceProvider`/`RoleMiddleware`/`CheckPermissions` null-`role_id` crash risk (confirmed
  empirically) and the hardcoded `role_id === 3` delivery-boy check.
- **API authorization / validation coverage**: `docs/PHASE_1_ARCHITECTURE.md` Task F (no FormRequest/
  Policy/Repository layer existed before this phase; what was and wasn't introduced, and why).
- **Full technical-debt inventory** (PSR-4 case mismatches, model/schema mismatches, dual RBAC mechanism):
  `docs/TECHNICAL_DEBT.md`.

## 5. What Phase 1 did NOT do (explicitly out of scope, per Task 8's own instruction)

Did not redesign RBAC. Did not add centralized authorization middleware/policies beyond the one
(`ProductPolicy`) directly tied to Phase 1's own tenant-model work. Did not perform an exhaustive
endpoint-by-endpoint IDOR audit. Did not add rate limiting, audit logging, or 2FA — none of these were
identified as Phase 1 blockers, and adding them speculatively would be exactly the "abstractions for
appearance" this phase's own rules warn against. All are reasonable Phase 2/15 candidates, named here for
continuity rather than silently dropped.
