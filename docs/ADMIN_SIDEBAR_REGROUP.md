# Admin Sidebar Regroup (SaaS re-architecture, Phase 1)

First step of the large "SaaS re-architecture" brief (Supplier/Wholesaler role, Creator Marketplace, theme
system, domains, RTL overhaul, etc.) - the product owner confirmed this as the starting point given the
project's scope, per the audit reported before this change (`docs/IMPLEMENTATION_PROGRESS.md` and this
session's own findings on existing RBAC/tenancy/subscriptions/theme state).

## What changed

`resources/views/components/admin/side-bar.blade.php` - the admin panel's sidebar, previously ~30 flat
top-level `sidebar-title` sections and 79 nav links (some already had Bootstrap `collapse` sub-menus, most
didn't) - regrouped into 13 collapsible top-level groups, matching the taxonomy the product owner specified:

```
Dashboard
Platform        (Stores, Sellers, Customers, Delivery Boys)
Catalog         (Categories, Brand, Products, Combo Products, Tax, Attributes)
Orders & Operations (Orders, Order Items, Order Tracking, Stock/Combo Stock, Return Requests)
Finance         (Seller Wallet Transactions, Payment Requests)
Marketing       (Offers, Offer Sliders, Promo Codes, Slider, Featured Section, Affiliate, Blogs)
Subscriptions   (Subscription Plans - super_admin only)
Themes & Website (Media Manage - theme system itself doesn't exist yet, see below)
Communication   (Support Tickets, Chat, Notifications, Custom Message, FAQs)
Locations       (Zipcodes, City, Zones, Bulk Upload)
Languages       (Language, Manage Language, Bulk Translation Upload, Web Languages)
Reports         (Sales Reports)
System          (System Settings, Web Settings, System Users)
```

Every existing route, permission check (`$user->hasPermissionTo(...)`, `$user_role == 'super_admin'`), and
label key is unchanged - this is a pure re-nesting of the existing markup under new collapsible group
wrappers, not a rewrite. Groups auto-expand and highlight active when the current page is inside them
(computed once per group via the same `Request::is(...)` patterns each item already used individually).

## What did NOT change (confirmed by audit before this pass)

- **RBAC mechanism**: still `role_id` (legacy `App\Models\Role` constants) for coarse role gating +
  Spatie's `HasPermissions`/`hasPermissionTo()` for fine-grained per-item permission checks (Spatie's role
  half is unused - confirmed in `app/Models/Role.php`'s own comment). Not touched this pass.
- **Multi-tenancy**: `Seller` is already the confirmed tenant unit (`docs/PHASE_4_MULTI_TENANT_DECISION.md`),
  `app/Services/TenantContext.php` already exists. Not touched this pass.
- **Theme system**: still doesn't exist beyond a dead `themes` table (zero references in `app/`) and
  `stores.store_settings` JSON style keys. "Themes & Website" group currently only has Media Manage - it's a
  placeholder home for the real theme-management UI a later phase will build.
- **Supplier naming**: `App\Models\Supplier` already exists as a *seller-scoped procurement* concept
  (`app/Http/Controllers/Seller/SupplierController.php`, `suppliers` table keyed by `seller_id` - where a
  seller buys their own stock from). The requested platform-level "sell-into-the-marketplace" role is a
  different concept and will be named **Wholesaler** (confirmed with product owner) to avoid colliding with
  this existing model/table when that phase is built.

## Verification

Live Playwright click-through (not just route sweeps): admin login → dashboard renders with the new grouped
sidebar → clicking "Platform" expands it showing Stores/Sellers/Customers/Delivery Boys sub-sections →
navigating directly to `/admin/products` (a nested Catalog page) auto-expands both the "Catalog" group and
its "Products Manage" sub-dropdown with correct active-state highlighting, matching what a page-refresh in
production would show. No JS errors, no 500s.

`tests/Feature/RouteSweepTest.php` + `tests/Feature/Phase2/*` (117 tests, every one renders this sidebar
component since it's shared across the whole admin panel) still pass unchanged. Full suite: 660 passing (one
pre-existing, unrelated failure - see `docs/STOREFRONT_V1.md`'s commit history for context - not touched by
this change).

## Seller sidebar (same pass, follow-up commit)

`resources/views/components/seller/side-bar.blade.php` - the seller panel's sidebar (324 lines, 12 flat
sections) regrouped the same way, into: Dashboard, Sales (Orders/POS/Stock/Deliverability/Return Requests),
Catalog (Categories/Brands/Tax/Attributes/Products/Combo Products), Website (Media - same "no theme system
yet" caveat as admin's group), Marketing (Affiliate Program), Finance (Wallet/Withdrawals/Payment Gateways),
My Subscription, Communication (Chat), Locations, Languages (Bulk Translation Upload), Reports. Same
discipline: every route/label unchanged, only re-nested.

**Real bug found and fixed while live-testing this** (unrelated to the sidebar itself - discovered because
this was the first time this session actually loaded the seller dashboard *with real product data* rather
than an empty fixture): `Seller\StockController::get_stock_List()` - the AJAX call the seller dashboard's
own stock widget makes on every page load - crashed with "Attempt to read property category_id on array"
for any seller with a real product. `$product` from `ProductService::fetchProduct()`'s `'product'` key is
an array (confirmed by `createRow()`, which the same method calls right after and which reads `$product`
with array syntax throughout, and by `Admin\ManageStockController::get_stock_List()`'s identical call site,
which already used the correct array access) - but this method read it with object syntax
(`$product->category_id`, `$product->stock_type`, `$product->id`). `RouteSweepTest.php` already flagged
this exact route as broken (`KNOWN_BROKEN_ROUTES`, "Category 5 - needs deeper individual investigation")
but its no-product fixture can never reach this crash path; fixed here and covered by a new
`tests/Feature/Seller/StockListDashboardTest.php` with a real product fixture that does.

Verified live: seller login → dashboard's stock widget loads without error → Sales group expands correctly
→ navigating directly to `/seller/products` auto-expands Catalog and its Products Manage sub-dropdown.
Full suite: 661 passing (up from 660 - the new regression test), zero regressions.

## Delivery boy + Affiliate sidebars (same pass, follow-up commit)

`resources/views/components/delivery_boy/side-bar.blade.php` (96 lines) and
`resources/views/components/affiliate/side-bar.blade.php` (64 lines) regrouped the same way. Both were
already small and mostly flat, so this pass is intentionally light:

- **Delivery boy**: Dashboard (flat) + 2 groups - "Deliveries" (Orders Manage dropdown, Returned Orders),
  "Finance" (Cash Collection, Fund Transfer, Wallet Transaction).
- **Affiliate**: Dashboard (flat) + 2 groups - "Marketplace" (Available Products, Private Stores),
  "Earnings" (Commission History, Withdrawals).

Same discipline as admin/seller: every route/label unchanged, only re-nested; each group's active/expanded
state computed from the same `Request::is(...)` patterns the individual links already used.

Verified live: delivery_boy login (mobile `9990000003`) -> dashboard renders -> "Deliveries" group expands
correctly. affiliate login (uses the seller's own credentials, mobile `9990000001` - `affiliate.login`
accepts any active user, no separate affiliate account) -> dashboard renders -> "Marketplace" group expands
correctly. No JS errors, no 500s. Full suite: 661 passing, same one pre-existing unrelated failure.

## RTL sidebar layout fix (same pass, follow-up commit - real bug found via live use, not part of the regroup itself)

Reported live by the product owner while reviewing the regrouped sidebars in Arabic: sidebar icons stayed
on the left regardless of language direction, and the sidebar panel itself never moved to the right side of
the screen in RTL - only the text inside flipped.

Root cause (confirmed via computed-style inspection in a real browser, not guessed): the fixed sidebar
(`.navbar-vertical`, from the vendored Argon Dashboard theme's `public/css/theme.css`) is `position: fixed`
with no `left`/`right` set, so it always resolves to its LTR static position (pinned left) regardless of
`dir`. `#page-content`'s `margin-left: 15.625rem` never had an RTL counterpart either. Worse, every nav
link's icon (and its group-toggle chevron) is hardcoded `position: absolute; right: 1.5rem` in
`custom.css` - harmless in LTR (the label starts at the opposite, `padding-left`-reserved side and never
reaches that far right), but once flexbox's `direction: rtl` (inherited from the existing `dir="rtl"`
attribute already on the sidebar's own `<nav>`) pulls the label flush against that same right edge, the
icon lands directly on top of the label text - visually confirmed as garbled/overlapping group labels
("Platform", "Catalog", etc.) in a live screenshot before this fix.

Also: `resources/views/affiliate/layout.blade.php` never applied `dir="rtl"` anywhere at all (the other
three panels did, on the sidebar `<nav>` and the content `.container-fluid`, just not on a shared ancestor
the sidebar-positioning CSS could key off).

Fix (`public/assets/admin/custom/custom.css`, `resources/views/{admin,seller,delivery_boy,affiliate}/layout.blade.php`):
added the same `{{ session()->get('is_rtl') == 1 ? 'dir=rtl' : '' }}` attribute to the shared `#db-wrapper`
element in all four layouts (affiliate's first `dir` attribute of any kind), then added
`#db-wrapper[dir="rtl"]` overrides: sidebar moves to `right: 0` with its border flipped to the left edge,
`#page-content` gets `margin-right` instead of `margin-left`, nav-link icons/chevrons move from
`right: 1.5rem` to `left: 1.2rem` (landing inside the same padding gap the label already avoids, by
construction - not just visually tuned), the active-state accent border flips from right to left, and the
small sub-item bullet dot (`.nav-link-text::before`) flips from `left: 55px` to `right: 55px`.

Verified live (Playwright, real login + real Arabic language switch via each panel's own
`{panel}/settings/languages/change?lang=ar` route, not a mock): admin, seller, and delivery_boy dashboards
all render with the sidebar correctly on the right, icons sitting cleanly next to their (mirrored) labels
with zero overlap, and the "Platform"/"Catalog"/etc. groups no longer garbled. Affiliate has no language
switcher wired up yet (pre-existing gap, not touched here) so RTL there is currently unreachable through
the UI, but the same `dir="rtl"` plumbing is now in place for whenever that gap is closed. Full suite: 661
passing, same one pre-existing unrelated failure - zero regressions from either this or the delivery_boy/
affiliate regroup above.

### RTL icon order correction (follow-up, same pass)

The fix above moved the icon/chevron cluster from `right: 1.5rem` to `left: 1.2rem` for RTL, which stopped
the overlap but put both the icon and the chevron on the *wrong* side: since the label sits flush against
the sidebar's right edge (RTL flexbox's own automatic mirroring), the icon at the far left ended up landing
*after* the label in reading order instead of before it, and the icon and chevron still shared the same
single absolute anchor point as each other (no longer overlapping the label, but still overlapping *each
other*). Reported live by the product owner testing the corrected sidebar.

Real fix: stopped anchoring the icon/chevron with `position: absolute` for RTL entirely and let them
participate in the `.nav-link`'s own flex row instead. `direction: rtl` (already applied via the `dir`
attribute) automatically reverses flex item order, so the DOM order icon → label → chevron now renders
correctly as icon (rightmost, immediately before the label) → label → chevron (pushed to the far left via
its own `margin-inline-start: auto`, which resolves to the *logical* end of the row in either direction) -
no absolute positioning, no shared anchor point, no possible overlap by construction. The nav-link's own
one-sided `padding-left: 46px` (needed only to reserve room for the old absolutely-positioned label) is
replaced with a small symmetric `padding: 10px 16px` for RTL, since the icon now takes its own real space in
flow instead of floating over reserved padding.

Verified the same way as the original fix (live Playwright, real Arabic session) across admin/seller/
delivery_boy: each sidebar row now reads icon → label → chevron in that order, right-to-left, with clean
adjacent spacing and zero overlap anywhere.

## Next steps in this re-architecture (not started)

Per the product owner's own prioritization: this sidebar/RBAC foundation work is now done for all four
panels (admin, seller, delivery_boy, affiliate), plus the RTL layout bug found along the way. Next up (in
order): the Wholesaler marketplace, Creator Marketplace, and the rest of the large brief (theme system,
store domains, subscription/feature-flag enforcement, app-wide RTL verification beyond just the sidebar -
e.g. tables, forms, and modals were not audited here). Each is its own multi-session effort - not attempted
here.
