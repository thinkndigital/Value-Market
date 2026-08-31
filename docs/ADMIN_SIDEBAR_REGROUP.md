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

## Next steps in this re-architecture (not started)

Per the product owner's own prioritization: this sidebar/RBAC foundation work first (admin done, seller
done), then delivery_boy/affiliate sidebars (smaller, lower priority - flagged but not yet requested), then
(in order) the Wholesaler marketplace, Creator Marketplace, and the rest of the large brief (theme system,
store domains, subscription/feature-flag enforcement, app-wide RTL verification). Each is its own
multi-session effort - not attempted here.
