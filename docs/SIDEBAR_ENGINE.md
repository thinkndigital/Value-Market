# Unified Dynamic Sidebar Engine

Phase 3 of the "VALUE MARKET — COMPLETE MASTER ARCHITECTURE & RESTRUCTURING PROMPT" (81-section
SaaS platform restructuring brief). This replaces the five hand-written per-role sidebar Blade
files (admin/seller/delivery_boy/affiliate/wholesaler, most recently regrouped in
`docs/ADMIN_SIDEBAR_REGROUP.md`) with one config-driven engine, per the brief's own instructions:

> config/sidebar.php → SidebarService → SidebarBuilder → Permission Resolver → Subscription
> Resolver → Feature Resolver → Tenant Resolver → Sidebar Component → Dynamic Sidebar UI

## What changed

- **`config/sidebar.php`** — the nav tree for all 5 roles, ported item-for-item from the previous
  Blade files (same routes, same `Request::is()` active-state patterns, same Spatie permission
  checks, same icons/labels). No item was invented or dropped in the port; the only content change
  is the Wholesaler-related labels (see "Supplier vs Wholesaler" below).
- **`App\Services\SidebarService`** — resolves a role's config tree for the current user:
  permission check (`$user->hasPermissionTo()`, with a `super_admin_only` flag for the handful of
  items that were previously gated on `$user_role == 'super_admin'` directly), a feature-flag hook
  (`config('sidebar_features.<flag>')`, defaults open), and a `Route::has()` check that silently
  drops any item whose route doesn't exist yet. Results are cached per `(role, user, locale)` for 5
  minutes; active/expanded state is computed fresh on every request since it depends on the current
  URL.
- **`resources/views/components/sidebar/tree.blade.php`** — one recursive Blade component that
  renders the resolved tree (`<x-sidebar.tree :items="$sidebarTree" />`). Every role's
  `side-bar.blade.php` keeps its own outer chrome (logo, search box, RTL `dir` attribute) but now
  builds `$sidebarTree = app(SidebarService::class)->build(auth()->user(), '<role>')` and includes
  the tree component instead of a wall of hand-written `<li>` markup. CSS classes are unchanged
  (`nav-item`, `nav-link`, `sidebar-group-toggle`, `sidebar-subtitle`, `collapse`/`show`), so the
  RTL icon-order fix already in `public/assets/admin/custom/custom.css` still applies untouched.

### What the engine does *not* do yet

- **Subscription Resolver**: a node accepts `'subscription_feature' => '...'` in the schema, but
  nothing reads it. This app has no generic "does this seller's plan include feature X" mechanism
  yet (`docs/PHASE_11_SUBSCRIPTIONS.md` only enforces `max_products` and `commission_rate`) —
  wiring a real feature-per-plan check is Phase 11 of the master prompt, not this pass. Building it
  here first would mean inventing subscription semantics ahead of the phase that's supposed to
  define them.
- **Tenant Resolver**: sidebar *items* aren't tenant-specific in this app (every seller sees the
  same nav shape; the data underneath each page is already tenant-scoped by existing
  policies/`TenantContext`), so this is a pass-through today, not a real filter.
- Forward-looking items already sit in the config for **Creator Marketplace** (admin/seller
  Marketing groups, and a whole `affiliate.creator.*` group) even though those routes don't exist
  yet — `Route::has()` hides them automatically until Phase 8 builds the routes, so no further
  sidebar edit will be needed then.

## Supplier vs Wholesaler naming

The master prompt's section 18 describes a "Supplier" role (dashboard, POS, products, orders from
sellers, wholesale pricing/MOQ) that is the same thing this app just shipped as the **Wholesaler**
module (`docs/WHOLESALER_MODULE.md`) — already in production with a demo account and real orders.
Confirmed with the product owner: keep `wholesaler_*` tables/models/routes/permissions in code
exactly as they are; only the **UI labels** read "Supplier" going forward. This pass updated that
wording everywhere it touches the sidebar (admin's Platform group, seller's Catalog group); the
Wholesaler panel's own login/register copy and page titles still say "Wholesaler" and are left for
Phase 6 ("Supplier architecture") of the master prompt, which is explicitly scoped to build out the
rest of section 18's spec (MOQ, seller-specific pricing, a Sellers-as-customers view, etc.) on top
of what already exists here.

Separately, the pre-existing `App\Models\Supplier` (a seller's own internal "who I buy stock from"
list for the Phase 5 procurement flow — purchase orders, goods received notes; no login, no
dashboard) was renamed to **`App\Models\ProcurementVendor`** to remove the name collision. This is
a class rename only: the `suppliers` table and its columns (including `purchase_orders.supplier_id`)
are untouched — `ProcurementVendor::$table = 'suppliers'`.

## Adding a new sidebar item

Add a node to the relevant role array in `config/sidebar.php` (see the schema comment at the top of
that file) and it appears automatically — no Blade edit needed. A node with a `route` that doesn't
exist yet is safe to add ahead of time; it just won't render until the route does.
