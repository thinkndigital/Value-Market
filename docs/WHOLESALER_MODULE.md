# Wholesaler Module v1 (SaaS re-architecture brief)

First major new-code phase of the SaaS re-architecture after the sidebar/RBAC foundation pass
(`docs/ADMIN_SIDEBAR_REGROUP.md`) - the "Supplier Marketplace" role from the product owner's brief. Built
from scratch: new role, new tables, new panel, plus the seller- and admin-side integration points around it.

## Naming

`App\Models\Supplier` already exists as a **seller-scoped procurement** concept
(`app/Http/Controllers/Seller/SupplierController.php`, `suppliers` table keyed by `seller_id` - where a
seller buys their own stock from). The new **platform-level, sell-into-the-marketplace** role is a different
concept and is named **Wholesaler** (confirmed with the product owner) to avoid colliding with the existing
model/table.

## Architecture decision: reuse `products`, don't duplicate it

A wholesaler maintains its own catalog (`wholesaler_products` - name/category/wholesale price/min order qty/
stock/status, admin-moderated). A seller "imports" a listing they want to stock. Importing does **not**
copy or alias the wholesaler's row - it creates a brand-new row in the *existing* `products` table (the same
table a seller's own hand-added products live in), linked back via a new `wholesaler_product_id` column for
traceability, with the seller choosing their own retail price and starting stock (never the wholesaler's
`wholesale_price` - that's shown only as a reference cost). This means every existing product/stock/order/
storefront/POS code path in this app just works with an imported product unchanged - nothing about "is this
imported" had to be threaded through order placement, search, the storefront, etc. This was the explicit
instruction in the product owner's brief ("do not duplicate tables... audit first, then refactor safely").

## What was built

- **Migration** (`database/migrations/2025_02_21_000000_create_wholesaler_module.php`): `wholesalers`
  (one row per wholesaler account - business_name/status/commission_rate placeholder/disk),
  `wholesaler_products` (the wholesaler's own catalog - status 0=pending admin approval/1=active/2=rejected),
  and `products.wholesaler_product_id` (nullable, indexed, no DB-level FK - matches this app's existing
  schema style). Seeds `roles.id = 7` => `wholesaler`, continuing the legacy role-table pattern
  `App\Models\Role`'s own constants already document.
- **`Role::WHOLESALER` constant** + `User::isWholesaler()` helper, matching every other role's existing
  pattern exactly.
- **`Wholesaler` panel** (`app/Http/Controllers/Wholesaler/*`, `resources/views/wholesaler/*`,
  `resources/views/components/wholesaler/*`, `routes/wholesaler_routes.php`): a self-contained auth
  controller (register/login/logout - deliberately its own controller rather than added to the already-large,
  heavily-depended-on `Admin\UserController`, so this new module can't regress existing panels' logins),
  a dashboard (product counts, sellers importing), and CRUD for the wholesaler's own catalog. Every new
  listing (or edit that changes name/price/category/image) resets to `status = 0` and re-enters the
  approval queue. Layout/sidebar/header/footer follow the exact same shared CSS/JS bundle and collapsible-
  sidebar pattern the admin/seller/delivery_boy/affiliate passes already established this session
  (including the RTL fix from that same pass - `#db-wrapper`'s `dir="rtl"` attribute).
- **Admin moderation** (`Admin\WholesalerController`, new "Wholesalers" subsection inside the admin
  sidebar's existing "Platform" group): account list with activate/suspend, and a separate per-listing
  approval queue (`admin/wholesalers/products_queue`) filterable by status - approving a listing is what
  actually makes it visible to sellers.
- **Seller-side browse/import** (`Seller\WholesalerMarketplaceController`, new "Wholesaler Marketplace"
  subsection inside the seller sidebar's existing "Catalog" group): a bootstrap-table listing every
  admin-approved wholesaler product, with an "Import" action that opens a small modal for the seller's own
  retail price + starting stock, then creates the `Product` + `Product_variants` rows described above.
  Blocks a seller from importing the same wholesaler listing twice.

## Real bug found and fixed while building this (live QA, not guessed)

`App\Services\CustomPathGenerator` (this app's registered Spatie MediaLibrary path generator) stores every
uploaded file under a `{collection_name}/` subfolder on disk - `MediaService::getMediaImageUrl()` builds its
existence check from the exact string saved in the DB column, so that string must include the subfolder.
The first live-QA pass (real image upload through the wholesaler product form) stored just `/{filename}`
(copying the pattern used elsewhere in this app, e.g. `Admin\SellerController::store()`'s seller-logo
upload) - which resolves to the *wrong* disk path and silently falls back to the generic "no image"
placeholder. `Wholesaler\ProductController::store()` now stores `$uploaded->getFullUrl()` instead - Spatie's
own fully-qualified URL for the file it just stored, correct for both the `public` and `s3` disks without
having to hand-build a path at all, the same approach `GeneratesDemoImages::uploadDemoImage()` (used by
every `demo:create-*` command) already relies on for exactly this reason. Confirmed correct via a direct
`curl` of the resulting URL (200, real file). Not fixed elsewhere in the app (out of scope for this pass -
flagging that the same latent bug likely affects other panels' own hand-built-path upload flows, e.g. seller
onboarding documents, as a follow-up worth a dedicated look).

Also fixed defensively while writing regression tests: `Seller\WholesalerMarketplaceController::import()`
copied `wholesaler_products.image` (nullable at the DB level) straight into the new `products.image` column,
which is `NOT NULL` - guarded with a `?: ''` fallback so a listing without an image (never reachable through
the UI today, since a new listing's image is required, but not impossible to reach otherwise) can't 500 an
import.

## Explicitly out of scope for this pass

- **Settlement/payout**: `wholesalers.commission_rate` and `wholesaler_products.affiliate_commission_rate`
  are stored but not acted on anywhere - no ledger entries, no automatic payout to a wholesaler when a
  seller sells an imported product. This needs a business-model decision (commission-on-sale vs. wholesale-
  price-is-the-whole-transaction vs. something else) before it's built, matching this session's existing
  "flag the business decision, don't guess" discipline (see `docs/IMPLEMENTATION_PROGRESS.md`'s Phase 11 row
  for the same open question on subscriptions).
- **Affiliate-enabled wholesaler products** ("supplier affiliate" flow from the brief): `affiliate_enabled`
  is stored as a flag on `wholesaler_products` but the existing affiliate system (built around `products`/
  `seller_id`) isn't wired to it yet - an affiliate can only generate links for products a seller has
  actually imported today, not directly against a wholesaler's own listing.
- **Wholesaler KYC/documents**: registration is name/mobile/email/password/business_name/address only - no
  document upload, no admin approval gate on the *account* itself (only on each product listing). Sellers
  go through a much heavier onboarding flow (`Admin\SellerController::store()`) with bank details, address
  proof, etc. - deliberately not mirrored here to keep this pass scoped; worth revisiting if wholesalers
  need payout details later (see settlement point above).
- **Wholesaler language switcher wiring for the affiliate panel** is unrelated - not touched.
- Bulk import (CSV) of a wholesaler's catalog, product images beyond a single one, variants/combo products
  on the wholesaler side - all single-image, single-variant "simple product" for v1, matching this app's
  own `stock_type = '0'` convention.

## Verification

Live Playwright run (real browser, real DB, no mocking) end-to-end: register a new wholesaler -> log in ->
add a product with a real image upload (starts pending) -> log in as admin -> approve it in the queue ->
log in as an existing seller -> browse the marketplace -> import with a real retail price/stock -> confirmed
via direct DB inspection that a correct `Product` + `Product_variants` row was created, linked back via
`wholesaler_product_id`. Screenshots confirm the sidebar links, tables, and modals all render correctly in
both panels.

New `tests/Feature/Wholesaler/WholesalerModuleTest.php` (10 tests): registration, login (active vs.
suspended), RBAC (a seller can't reach `wholesaler/*` routes), a wholesaler can't touch another wholesaler's
product, a new product starts pending, the seller marketplace excludes non-approved listings, import creates
the correct `Product`/`Product_variants` rows, double-import is blocked, and admin approval flips a
listing's status. `tests/Feature/Phase1/MigrationBaselineTest.php`'s table-count assertion updated
(124 -> 126) for the two new tables - a real, expected count change, not a regression.

Full suite: 671 passing (one pre-existing, unrelated failure - `AdminHomePerformanceTest`, real leftover
demo/QA accounts predating this session's work - see `docs/ADMIN_SIDEBAR_REGROUP.md`'s commit history for
the same note), zero regressions from this change.

## Demo account (same pass, follow-up commit)

Product owner asked for a real wholesaler account they could log into themselves, matching the demo-account
pattern already established for the other three roles. New `app/Console/Commands/CreateDemoWholesaler.php`
(`php artisan demo:create-wholesaler --mobile=... --password=...`), mirroring
`CreateDemoDeliveryBoy`/`CreateDemoSeller`'s exact structure: creates an active `User` + `Wholesaler` row
(no admin-approval gate on the account itself, matching this module's own design - see "Wholesaler KYC/
documents" above), plus two already-approved (`status = 1`) sample `wholesaler_products` rows with real
placeholder images, so the account is immediately useful for demoing both halves of the module - not just an
empty dashboard. Also wired into `demo:seed-all` (mobile `9990000004`), so the existing one-shot "create
every demo account" command now creates this one too. Verified live: the command runs cleanly, both sample
products' images resolve (200, confirmed via `curl`), and `RouteSweepTest`'s own `demo:seed-all` call (used
to seed its own fixtures) still passes with the new account folded in.

## Landing page (same pass, follow-up commit)

`resources/views/customer/home.blade.php`'s existing "Join Our Platform" section (Seller/Delivery Partner/
Affiliate/Admin cards, built earlier this session) now has a fifth "For Wholesalers" card with both a
"Become a Wholesaler" (`wholesaler.register`) and "Wholesaler Login" (`wholesaler.login`) CTA, matching the
Seller card's pattern exactly (the only other role with a real public self-registration route). The grid's
column class changed from `col-md-3` (4 even columns) to `col-md-4` (3 per row, since 5 cards no longer
divide evenly into 4) - a 3+2 layout, applied to all five cards for a consistent look.

## Sidebar RTL correction (same pass, follow-up commit)

Unrelated to the Wholesaler module itself, but reported by the product owner while reviewing this pass live:
a follow-up correction to the RTL sidebar fix from `docs/ADMIN_SIDEBAR_REGROUP.md` (icon/chevron order and a
remaining icon-vs-chevron overlap) - see that doc's own "RTL icon order correction" section for the full
root cause and fix.

## Next steps in this re-architecture

Per the established order: Wholesaler module (this pass) -> Creator Marketplace (entirely new, most complex
remaining piece) -> theme system -> store domains -> subscription/feature-flag enforcement -> app-wide RTL
verification beyond the sidebar. Each is its own multi-session effort.
