# Phase 2 — Route & Page Sweep Report

Automated, evidence-based execution of the 32-phase brief's Phase 2 ("Complete Route & Page Inventory").
Manually clicking every page across 4 panels isn't practical in one pass; instead `tests/Feature/
RouteSweepTest.php` programmatically hits every parameter-less GET route as a real logged-in user of the
right role and checks it doesn't 500 — the same "route/controller/view all exist and it actually renders"
bug class `AdminMissingViewsTest`/`SellerAndDeliveryBoyMissingViewsTest` already found and fixed for 11
specific pages, generalized here to every no-param route instead of only ones already stumbled onto by
hand.

## Scope and honesty about what this does/doesn't prove

**Swept:** 181 admin + 86 seller + 18 delivery_boy + 13 affiliate no-param GET routes = 298 total, all real
routes from `route:list`, hit through the actual HTTP kernel (middleware, auth, everything) as a real user
of that role.

**Not swept, explicitly (not silently skipped):**
- **Routes requiring a URL parameter** (75 admin / 27 seller / 4 delivery_boy / 1 affiliate) — each needs a
  real, valid id for its specific model (a product id, an order id, a store id...); a generic substitution
  like `1` would be misleading (works for some, meaninglessly 404s or 500s for others depending on what
  exists). Auditing these needs per-route seeded fixtures, not a blanket sweep — real remaining work, not
  done here.
- **Everything past "does it render":** forms actually submitting, AJAX interactions, search/filter/
  pagination correctness, RTL rendering, button click-throughs. A passing sweep entry means the page doesn't
  fatal on load — it is not a claim that the whole page works end-to-end.

## Result

Initial sweep: **48 admin, 23 seller, 4 delivery_boy, 0 affiliate** routes returned a real server error
(status ≥500), each with a captured exception class/message/file/line (not just a status code). After
triage and fixes below, all four sweep tests pass. Every route still known-broken is enumerated in
`RouteSweepTest::KNOWN_BROKEN_ROUTES` with the category it falls into (so the test only fails on *new*
breakage, not this list) — this document is the detail behind each entry.

## Category 1 — Dead routes (36 routes, admin+seller+delivery_boy combined)

`BadMethodCallException: Method X::create does not exist` — the route is registered, but its controller was
never given a `create()` method. Grep-confirmed **zero references** to any of these route names in any
Blade view or JS file: nothing links to them. In this app's actual UI, adding a category/product/tax/etc.
happens via a form/modal on the resource's own index page, not a separate `/create` page — these are
vestigial route registrations (likely leftover `Route::resource()`-style scaffolding never pruned after the
real implementation went a different direction), not features anyone can reach by clicking around normally.

**Real-world risk:** low (unreachable via navigation) but not zero — a bookmarked/guessed/typed URL 500s
instead of 404ing. **Recommended fix, needs a decision:** either delete the 36 dead route registrations
(cleanest — matches what's actually built) or add a redirect-to-index `create()` stub to each (keeps the
URL alive, in case something external links to it). Not fixed in this pass — a bulk mechanical change across
36 routes/controllers, low urgency, deferred for your call on which direction.

## Category 2 — AJAX-only endpoints hit bare (16 routes)

E.g. `products/fetch_attribute_values_by_id` (needs `id`), `products/get_variants_by_id` (needs
`variant_ids`), `*/get_seller_deliverable_type`/`*/zones_data` (need `seller_id`), `categories/
update_category_order` (needs a reorder payload). These are always called by JS with the required params in
real usage — a bare GET 500ing is a sweep artifact, not a page users can reach broken. **Real-world risk:**
essentially none. Worth a future pass adding input validation (return a clean 4xx instead of an
`ErrorException` on missing keys) as general hardening, not urgent.

## Category 3 — Controller method genuinely missing (7 routes, real incomplete-feature gaps)

Unlike Category 1, these routes' *names* strongly suggest intended, reachable functionality, and there's no
"it happens elsewhere via a modal" explanation:
- `/admin/privacy_policy/seller_privacy_policy_page`, `/admin/terms_and_condition/
  seller_terms_and_condition_page` — seller-specific policy pages, method never implemented on
  `SettingController`.
- `/admin/settings/time_slot_settings`, `/admin/settings/time_slot/list` — delivery time-slot config,
  method never implemented.
- `/admin/settings/manage_web_language`, `/seller/settings/language`, `/delivery_boy/settings/language` —
  language-management UI referenced by route name but the controller method backing it doesn't exist
  (note: this is distinct from the bulk-upload language system, which is real and working — see
  `docs/COMPLETE_SYSTEM_MAP.md` §12).

**Recommended:** each needs its own scoped implementation (or a decision that the feature isn't wanted, in
which case remove the route like Category 1). Not attempted in this pass — genuine feature work, not a bug
fix, and each is independent enough to prioritize separately.

## Category 4 — Fresh-install crash class (5 routes) — same bug pattern as today's storage_types fix

`shipping_policy_page`, `return_policy_page`, `settings/system_settings`, `settings/email_settings`, and
`delivery_boy/login` all crash with "Trying to access array offset on null" / "Undefined array key" when
the specific `Setting` row (or key within it) they read has never been saved. **This is exactly the same bug
class found and fixed for `storage_types` earlier this session** (`app/Console/Commands/Concerns/
GeneratesDemoImages.php`): a genuinely fresh install, before an admin ever opens Settings and saves once,
would 500 on these pages. **Not yet confirmed against production** — production's `Setting` rows already
exist (this app has been running), so this may not currently bite anyone; it would bite a *new* install of
this codebase, or anyone who manually clears a `settings` row. Recommended fix: safe `??`/`isset()` defaults
in each Blade view (matching the fix pattern already applied to `GeneratesDemoImages.php`), or a seed
migration for these Setting rows (matching the `2025_02_02_000000_seed_default_storage_type.php` pattern).
Not fixed in this pass — flagging for prioritization since it's a real class of bug, not guessing at
severity.

## Category 5 — Needs individual investigation (7 routes)

`admin/media/image`, `seller/media/image` (dynamic image proxy, likely needs a real `?image=` param — not
yet root-caused), and `manage_stock/list` / `manage_combo_stock/list` / `manage_stock/get_stock_list` /
`seller/orders/list` (`Undefined array key "total"` / `"category_id"` — these already default their `limit`
param correctly, so it's a different root cause than Category 6 below; possibly depends on `session
('store_id')` being set by a real login flow that this sweep's bare `actingAs()` skips — needs a dedicated
look, not conflated with the confirmed-and-fixed pagination bug). Left un-triaged rather than guessed at.

## Category 6 — Fixed this pass: shared pagination crash (4 routes)

`admin/products/list`, `seller/products/list`, `admin/combo_products/list`, `seller/combo_products/list` all
threw `SQLSTATE[42000]: ... syntax error ... near ''` — literally invalid SQL (`ORDER BY id DESC OFFSET 0`
with no preceding `LIMIT`). Root cause: `$limit = request("limit");` with no default, and Eloquent's
`Builder::limit()` treats `null` as passing its `$value >= 0` guard (PHP's loose comparison), so it still
sets a limit clause instead of skipping it — producing SQL the grammar can't compile. **Real-world risk was
already low** (bootstrap-table, which drives every one of these table pages, always sends a `limit` query
param — this only bites a direct/API call that omits it) but the fix was trivial and precedented: 3 sibling
methods in the very same files already default `limit` to 10 correctly (`Admin\ProductController.php`
alone has 3 other methods doing `(int) $request->input('limit', 10)`). **Fixed**: all 4 occurrences now use
the same established default. Verified via the sweep test (all 4 routes now pass) plus the full suite (574
passing, zero regressions).

## Summary table

| Category | Count | Fixed this pass | Real-world risk |
|---|---|---|---|
| 1 — Dead `/create` routes | 36 | No — needs delete-vs-implement decision | Low (unreachable) |
| 2 — AJAX-only, needs params | 16 | No — low priority hardening | ~None |
| 3 — Method genuinely missing | 7 | No — real feature work | Medium if these features are wanted now |
| 4 — Fresh-install Settings crash | 5 | No — flagged, same class as today's storage_types fix | Real on a new install |
| 5 — Needs investigation | 7 | No — not yet root-caused | Unknown |
| 6 — Shared pagination bug | 4 | **Yes** | Was low, now zero |

## What's next

`tests/Feature/RouteSweepTest.php` is now permanent regression coverage — any route that starts 500ing that
isn't already in `KNOWN_BROKEN_ROUTES` will fail the suite immediately, catching future instances of exactly
this bug class before they ship. Parameter-required routes (Phase 2's other 107 routes) remain unaudited —
real next-step scope, needs per-route seed fixtures rather than a blanket sweep.

## Param-route sweep, batch 1 (32-phase SaaS brief follow-up)

Continuing the deferred param-route scope above, one batch at a time (real per-route fixtures, not a blanket
substitution) — product owner's direction: work through it incrementally rather than all at once.
`tests/Feature/Phase2/ParamRouteSweepBatch1Test.php` covers the admin panel's simpler single-model CRUD
resources: category, brand, blog, blog_category, tax, zone, city, currency, promo_code, storage_type, faq,
zipcode, area, pickup_location, custom_message, ticket_type, seller — 39 routes (edit/destroy/update_status/
view, each with a real seeded fixture, destroy routes ordered last per resource so nothing gets deleted out
from under a later check). Two real, previously-unknown bugs found and fixed:

1. **`admin/area/edit/{id}` → `BadMethodCallException`.** The route (`admin.area.edit`) was wired to
   `AreaController::areaEdit()`, which never existed. Fixed with the same one-line `editData()` call
   `cityEdit()`/`zipcodesEdit()` already use for the identical shape.

   **A fuller, corrected finding while investigating this**: the *entire* Area CRUD backend is missing, not
   just `edit` — `areaList`/`storeArea`/`areaDestroy`/`displayArea` (backing `admin.area.list`/`.store`/
   `.destroy`/`admin.display_area`) don't exist either, and `resources/views/admin/pages/forms/areas.blade.php`
   (a real Add-Area form + an empty results table) has no sidebar link and no edit/delete action wired in its
   markup at all. This corrects Category 2's original "AJAX-only, needs params, ~none risk" categorization
   for `/admin/area`/`/admin/area/list` — it's actually Category 3 (unfinished feature), just one with
   unusually low reachability (no navigation path to it at all, not even a broken one). Only `areaEdit()`
   was fixed here (a safe, precedented, non-UI-inventing completion); building out `areaList`/`storeArea`/
   `areaDestroy`/`displayArea` — and deciding whether "Area" should exist at all given City+Zipcode already
   cover the same real business need — needs its own scoped product decision, not a route-sweep-batch fix.

2. **`admin/sellers/edit/{id}` → `ErrorException: Trying to access array offset on null`.**
   `Admin\SellerController::edit()` computed `$selected_zipcode_text` correctly (null-guarded) on one line,
   then immediately overwrote it on the next by unconditionally doing `$selected_zipcode_text[0]->zipcode` —
   crashing on any seller whose store has no zipcode set. Fixed by merging the guard into the single
   computation instead of a redundant, unguarded second pass.

Both proven by dedicated regression tests (`tests/Feature/Phase2/AreaAndSellerEditBugsTest.php`), not just
the sweep itself. Full suite: 645 passing (642 before this batch), zero regressions.

**Remaining for later batches**: seller/delivery_boy/affiliate param routes (32/4/1), and the admin routes
needing richer, multi-model fixtures (products, combo products, orders, sellers-as-seller-not-admin,
attributes, currency exchange-rate/language-locale routes, system users/permissions, sliders/offers/
category-sliders, manage-stock) — deliberately not attempted in batch 1, which stuck to single-model
resources to keep this pass reviewable.
