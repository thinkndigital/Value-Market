# Phase 2 — Mass Assignment Audit (Task 12)

## 1. The finding: `Model::unguard()` is called globally, on every request

`app/Providers/AppServiceProvider.php:58`, inside `boot()` (which runs on every single request, not just
console/seeding):

```php
Model::unguard();
```

No matching `Model::reguard()` anywhere in the codebase (confirmed: `grep -rn "Model::unguard\|Model::reguard"
app/` returns exactly this one line). This is a **static, process-wide flag** checked by
`Illuminate\Database\Eloquent\Model::isFillable()`:

```php
public function isFillable($key)
{
    if (static::$unguarded) {
        return true;
    }
    ...
}
```

When `$unguarded` is `true`, **every model's `$fillable`/`$guarded` declaration is ignored entirely** -
`Model::create($array)`, `$model->fill($array)`, and `$model->update($array)` all accept **every column**,
regardless of what the model itself declares. This is unconditional and global: it is not scoped to a
request, a controller, or a specific model.

## 2. Why Phase 1's "found sound" conclusion needs revision

`docs/SECURITY_AUDIT.md` §2 concluded mass assignment was safe based on:
- No model declares `$guarded = []`.
- 10 models declare neither `$fillable` nor `$guarded` (Laravel's own default, `$guarded = ['*']`, would
  apply - "maximally protected").
- No `::create($request->all())` anti-pattern found in `app/`.

Every one of those checks is about what a model **declares**, or a specific literal call pattern. None of
them account for `Model::unguard()` being active - which makes the *declaration* irrelevant. The 10 "maximally
protected" models are not protected at all while unguarded; they simply happen not to have been hit by an
unfiltered write pattern **yet**. This is a real gap in that audit's methodology, not a contradiction of
its literal findings - the specific patterns it checked for genuinely don't exist; the audit just didn't
have visibility into the global switch that makes those patterns the *only* thing standing between "safe"
and "not safe" moot.

## 3. Why this was not fixed in this task (documented, not fixed)

Re-verified this task, directly (not re-quoting Phase 1's grep): still no literal `::create($request->all())`
anywhere in `app/`. Every direct-write call site checked (`updateDetails()`/`deleteDetails()` helpers,
`ReturnRequest::create($requestData)` in `OrderService.php`, the several `Controller::update($request, ...)`
delegations checked this task) builds its data array from hand-curated, explicitly-named fields rather than
passing request data through wholesale - consistent with what Phase 1 found. **In the specific call sites
this task actually read, the practical exploitability is low.**

But `Model::unguard()` is not a "low severity because nothing bad happens today" finding - it is a **removed
defense-in-depth layer across the entire application**, permanently. The actual risk is every write path
this task (and Phase 1) *did not* read: this app has ~200+ methods across three ~14,000-line API controllers
alone (`App\v1\ApiController`, `Seller\v1\ApiController`, `Delivery_boy\v1\ApiController` -
`docs/PHASE_2_IDOR_AUDIT.md` §5), plus every admin/seller CRUD controller. Auditing every one of them to
confirm none passes a broader-than-intended field list to `create()`/`update()`/`fill()` - the only way to
safely remove the global unguard without risking a live regression - is a project of its own scale, not a
sub-task of this pass. Two already-fixed findings this same phase (`update_product_status()`,
`docs/PHASE_2_IDOR_AUDIT.md` §5b #2) show the exact shape of bug this defense-in-depth layer would normally
catch even when the *ownership* check is present but a *field* is missing from an allowlist - removing
`Model::unguard()` blind, without that full audit, risks either breaking legitimate writes that currently
rely on it (if any model's `$fillable` is stale/incomplete relative to what the app actually needs to write)
or leaving the same exposure in place under a false sense of it being fixed.

This mirrors the exact reasoning already used to defer `Seller\PosController::update_user_address()`
(`docs/PHASE_2_IDOR_AUDIT.md` §2d) rather than guess at a fix under this phase's explicit "the existing
application must remain functional throughout" constraint.

## 4. Recommended remediation (for the dedicated future pass this deserves)

1. Grep every `::create(`, `->fill(`, `::update(`, `->update(` call site across `app/` for anything whose
   argument is not a literal, hand-built array (i.e., anything that traces back to `$request->all()`,
   `$request->only([...])`, or a `validated()` array without re-checking every field name against what
   should actually be settable).
2. For each model actually written to, declare an explicit `$fillable` matching only what legitimate write
   paths need - not `$guarded = []`.
3. Only once every write path is confirmed to work under real `$fillable` declarations, remove
   `Model::unguard()` from `AppServiceProvider::boot()` (delete the line; no `Model::reguard()` call is
   needed since removing the unguard call is sufficient - Laravel's default is guarded).
4. Run the full test suite plus a manual pass through the admin/seller panels' create/update forms (products,
   orders, sellers, system users, stores, etc.) before merging, since this change has application-wide blast
   radius by definition.
5. Do **not** attempt this as a "quick" fix bundled with unrelated work - if it breaks something, the
   breakage will be silent (a write that used to work now drops a field) rather than a crash, which makes it
   far more dangerous to rush than the crash-shaped bugs this phase has otherwise been fixing.

## 5. Verification performed

- `grep -rn "Model::unguard\|Model::reguard" app/` - exactly one call site, confirmed unconditional (inside
  `boot()`, not gated by `app()->runningInConsole()` or any environment check).
- `grep -rn "::create(\$request->all()\|->fill(\$request->all()\|->update(\$request->all())" app/` - zero
  matches, confirming Phase 1's specific finding still holds.
- Spot-checked every `::create($request` / `->update($request` / `->fill($request` call site found via grep
  (7 matches, `app/Http/Controllers/Seller/v1/ApiController.php` x5, `Delivery_boy/v1/ApiController.php` x1,
  `OrderService.php` x2) - all delegate to sub-controllers or build hand-curated arrays, none pass request
  data through unfiltered.
- Confirmed `role_id` (the single most dangerous mass-assignable field in this app) is hardcoded, not
  request-driven, everywhere a `User` is created via the flows checked this task (`Admin\UserController`,
  `Admin\SellerController`) - the one place it *was* request-driven with no restriction
  (`UserPermissionController::store()`) was a real, separate privilege-escalation bug, found and fixed this
  same task (see the "fix: prevent privilege escalation via system-user creation" commit) - notably, that
  bug existed **despite** `$fillable` being irrelevant here, because the code set `$user->role_id =
  $request->input('role')` directly rather than through mass assignment; removing `Model::unguard()` alone
  would **not** have caught or fixed it, which is itself evidence that this global flag is not the only (or
  even primary) mass-assignment-shaped risk in this codebase - direct property assignment from request input
  bypasses `$fillable` in exactly the same way.
