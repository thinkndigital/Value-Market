# Route name deduplication

`docs/PHASE_17_FULL_QA_PRODUCTION_READINESS.md` §2 and `docs/DEPLOYMENT.md` §3 flagged this as the
remaining blocker on `php artisan route:cache`: ~50 groups of duplicate route names (68, confirmed by an
exact re-count this pass) across `routes/admin_routes.php`, `routes/seller_routes.php`,
`routes/delivery_boy_routes.php`, and `routes/web.php`. Laravel's named-route registry is process-wide, so
two routes registered under the same name anywhere in the app collide even across unrelated route files;
uncached, `route('name')` silently resolves to whichever route was registered *last* (a latent,
independent bug - some `route()` calls may already have been resolving to the wrong route before this
pass, though this codebase's tests didn't cover most of the affected UI links). `route:cache` refuses to
build at all when a name collides, since its serialized route table must resolve every name to exactly one
route.

All 68 are resolved. `php artisan route:cache` now succeeds; `php artisan route:clear` was run afterward so
this dev/test session isn't left running on a cached route table.

## Two patterns

**Pattern A - legacy route left in place after a `Route::resource()` migration.** Most of admin_routes.php's
CRUD sections were migrated to `Route::resource()` at some point, but the pre-existing legacy routes
(`/edit/{id}`, `/update/{id}`, `/destroy/{id}`, `/create`, using non-RESTful GET-based single-purpose URIs)
were left in the file instead of removed, and both ended up registered under the same route name - the
resource's own default action name (`{resource}.{action}`) collides with the legacy route's explicit
`->name()` call, or, in a handful of cases, two resource-based actions collide directly. For every one of
these, the actual controller method reachability was checked (not assumed) before deciding what to do:

- **grep confirmed** whether `route('name', ...)` is called anywhere (`app/`, `resources/views/`, and any JS
  under `resources/js`/`public/` that constructs URLs via a route-name helper - none does; `public/assets/
  admin/custom/custom.js`'s delete/edit flows build URLs from a literal `data-url`/`data-delete-url`
  attribute the controller already rendered via `route()`, not from a JS-side route name).
- **the target controller was checked for the actual method** the competing route points to. Several
  controllers (`Admin\BlogController`, `Admin\AttributeController`, `Seller\AttributeController`,
  `Admin\ReturnRequestController`, `Seller\ReturnRequestController`, `Admin\OrderController`,
  `Delivery_boy\OrderController`, `Seller\OrderController`) never implement `store()`/`edit()`/`update()`/
  `destroy()` at all - only the legacy, differently-named methods (`storeBlog`, `destroyBlog`, ...) exist,
  or the action was never implemented on either side. `Route::resource()` still auto-registers a route
  for these actions; calling one would 500 with "method does not exist". Those are unambiguously dead
  routes, not a coin-flip about which of two working routes should win.
- Where **both** the legacy route and the resource's colliding action point to the *same, real* controller
  method (e.g. `brands.destroy`: legacy GET `brands/destroy/{id}` and resource DELETE
  `brands/{brand}` both call `BrandController::destroy()`), the duplication is a literal redundant
  registration with no behavioral difference - the resource's copy was excepted out
  (`->except(['show', 'edit', 'destroy'])` etc.), the legacy route (and its name) kept untouched.

Applied this way, **29 of the 68** names were resolved purely by adding the colliding resource action(s)
to the resource's `->except([...])` list (removing the redundant/dead resource-generated route, always the
*resource's* copy, never a route with a real, reachable, distinctly-named caller) with the winning route's
name left completely untouched - so the handful of call sites that already reference these names
(`route('blogs.destroy', ...)`, `route('taxes.destroy', ...)`, `route('ticket_types.edit', ...)`, etc., all
found via the grep sweep above) needed **zero changes**. A few more names (`categories.update`,
`combo_product_attributes.update`/`.destroy`) are a one-sided version of the same fix: the legacy route's
name was left alone and only the resource's colliding copy was excepted or renamed out of the way, while
the *other* panel's copy of that name (Pattern B, below) still needed a genuine rename. One genuinely dead
legacy route was deleted outright rather than excepted-and-kept: `attributes/destroy/{id}` in
admin_routes.php (named
`attributes.destroy`, calling a `destroy()` method that doesn't exist on `Admin\AttributeController`, with
zero callers and no admin UI that links to it at all - the admin attributes page only has a status toggle,
no edit/delete UI). The admin and seller resource-generated `attributes.destroy`/`.create`/`.update`
routes were kept and renamed (see Pattern B) rather than also deleted, since they're the standard
RESTful shape a future implementation would expect.

Two `return_request` resources (admin and seller) had their entire `create`/`store`/`edit`/`update`/
`destroy` action set excepted rather than individually patched: neither `ReturnRequestController` (admin
or seller) implements those four actions at all - only `index`, `list`, and a separately-and-already-
uniquely-named `update` (POST, no route parameter, driven by a request-body id) exist and are used. The
resource scaffolding for the other four actions was 100% dead on both sides.

**Pattern B - the same feature legitimately duplicated across panels.** Real, distinct routes (same
controller class shared between panels, as with the Chatify `MessagesController`, or panel-specific
controller subclasses implementing the same feature) that happen to share an unprefixed
`Route::resource()`-default name because only `index` (and sometimes `edit`) was ever given an explicit
panel-prefixed name in the `->names([...])` override - `create`/`store`/`update`/`destroy` silently kept
Laravel's bare default name, which collided the moment a second panel registered the same resource. Fixed
by extending each resource's `->names([...])` override to explicitly prefix every action that still used
the bare default, matching the `admin.<feature>.<action>` / `seller.<feature>.<action>` /
`delivery_boy.<feature>.<action>` convention already used elsewhere in these files (e.g.
`admin.sellers.get_seller_deliverable_type`, whose seller-panel duplicate is now
`seller.sellers.get_seller_deliverable_type`). Covers: `attributes.*`, `chat.*`, `combo_product_attributes.*`,
`combo_product_faqs.*`, `combo_products.*`, `product_faqs.*`, `products.*`, `orders.store/create/update`,
`tax.edit`/`tax.update` (admin's own explicit routes renamed to `admin.tax.*`, seller's resource defaults
to `seller.tax.*`), and `admin.sellers.get_seller_deliverable_type`.

`orders.destroy` was a hybrid: admin's and delivery_boy's resource-default DELETE actions were simply
renamed (`admin.orders.destroy`, `delivery_boy.orders.destroy`); seller's case combined both patterns - a
legacy GET route (`seller/orders/destroy/{id}`) was the one actually referenced
(`route('orders.destroy', $item->id)` in `Seller\OrderController::list()`, building the delete link exactly
like the admin-panel `.delete-data` pattern above), so it was renamed to `seller.orders.destroy` and its
one call site updated to match; the shadowed resource-default DELETE action (same, non-existent,
`destroy()` method - `Seller\OrderController` has no `destroy()` method at all, a pre-existing bug
independent of this pass, left as-is) was excepted out as redundant.

### The 4-panel shared-name cases: `changeLang`, `savelabel`, `set-language`, `set_store`

These looked, going in, like exactly the case the task called out as risky - a name potentially called
from a shared header/language-switcher partial included in every panel's layout, where a blind rename
would break three of the four panels. Checked before touching anything:

- `resources/views/components/{admin,seller,delivery_boy}/header.blade.php` are **three separate files**,
  not one shared partial - `changeLang` in each is a CSS class on an `<a>` tag, not a route name.
- The actual navigation happens in `public/assets/admin/custom/custom.js`'s delegated `.changeLang` click
  handler, which builds the URL as `appUrl + from + "/settings/languages/change"` (`from` is a
  per-panel JS variable) - a plain string concatenation, never `route()`.
- The `savelabel` forms (`resources/views/admin/pages/forms/language.blade.php` and `web_language.blade.php`)
  post to a hardcoded literal path (`/admin/settings/languages/savelabel`, `languages/savelabel`), not
  `route()`.
- `set_store` (two identical closures, admin and seller) has no template or JS reference of any kind found
  by name.

A repo-wide grep for `route('changeLang'`, `route('savelabel'`, `route('set-language'`,
`route('set_store'` (single- and double-quoted, `.php`/`.blade.php`/`.js`) confirmed **zero** call sites for
all four names before any change was made. So no shared-partial indirection was needed - each of the (up
to) four panel copies was just given its own prefixed name (`admin.changeLang` / `front.changeLang` /
`seller.changeLang` / `delivery_boy.changeLang`, and the equivalent for `savelabel`; `admin.set_store` /
`seller.set_store`; `seller.set-language` / `delivery_boy.set-language`, admin's two `set-language` routes
were already uniquely named `admin.set-language`/`front.set-language` and untouched). The URIs themselves
were not touched - only the `->name(...)` string - so this is a pure, zero-risk rename verified by grep,
not by assumption.

(`admin_routes.php` registers **two** language-management features under the admin panel -
`Admin\LanguageController` for the admin-panel's own UI language and `Admin\FrontLanguageController` for
the customer-facing storefront's language - which is why `changeLang`/`savelabel` had *two* admin-side
registrations colliding with each other in addition to the seller/delivery_boy ones. `FrontLanguageController`
already had a `front.set-language` sibling name, so its `changeLang`/`savelabel` copies were renamed to
match that existing `front.*` convention rather than `admin.*`.)

## `seller_terms_and_conditions.view`

Not a resource collision at all: two independently-written, genuinely distinct features share one name -
`routes/web.php`'s public-facing "view seller terms and conditions" page (outside any auth group, used by
prospective sellers/customers) and `routes/admin_routes.php`'s admin-panel management view of the same
policy (inside the `auth`+`role` group, gated by `permissions:edit admin_policies`). Zero `route()` callers
for either. The public route's name was left alone (it matches the naming convention of its sibling public
policy routes in the same file - `terms_and_conditions.view`, `privacy_policy.view`,
`seller_privacy_policy.view`, etc., all unprefixed `<feature>.view`); the admin-panel route was renamed to
`admin.seller_terms_and_conditions.view`, consistent with the `admin.*` prefix already used throughout
that file's settings section.

## Verification performed

- `grep -rn "route('<name>'" --include=*.php --include=*.blade.php --include=*.js .` (and the double-quote
  variant) for every one of the 68 names, before deciding the fix, across the whole repo (not just the
  obviously-relevant panel).
- `php -l` on every touched file after each batch of related renames.
- `php artisan route:list` re-run after each batch to catch registration errors early.
- A second full duplicate-name scan (`route:list --json` + a name-grouping script) after all 68 were
  addressed: **0 duplicate names remain**.
- `php artisan route:cache` succeeds; `php artisan route:clear` run afterward.
- `php artisan test`: 381 passed (694 assertions) - at or above the pre-existing baseline, no new failures.
- A final grep for every *renamed-away* old bare name (not the ones kept on the legacy route and merely
  un-shadowed) across the whole repo: zero stragglers.
- Spot-checked the trickiest cases by reading the actual before/after code path, not just trusting a
  passing test suite that doesn't cover most of these UI links: the `changeLang`/`savelabel`/`set-language`/
  `set_store` JS/hardcoded-URL mechanism (above), and the `attributes.destroy` legacy-route deletion
  (confirmed no admin UI links to it and `AttributeController::destroy()` doesn't exist, so deleting it
  removes only unreachable dead code).

## What this did not do

This pass fixes route *names*, not the handful of pre-existing dead/broken actions it surfaced along the
way (`Seller\OrderController` and `Delivery_boy\OrderController` have no `destroy()` method despite a
route pointing at one; the whole `attributes` and `return_request` create/edit/update/destroy CRUD surface
is unimplemented on both admin and seller controllers). Those are real, separate findings, left as-is and
flagged here rather than fixed blind in the same pass - none of them are new, none of them are made worse
by the renames, and none of them were reachable before this pass either.
