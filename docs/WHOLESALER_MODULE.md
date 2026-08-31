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

## v2: a real purchase-order workflow (follow-up pass)

The product owner asked for the wholesaler's own panel to have POS/inventory/sales/CRM/orders/"stores and
merchants" - real B2B commerce operations, not just a self-service catalog. Two scoping questions got asked
and answered before building this: (1) should "orders"/"POS" mean a real purchase-order system (seller
requests a quantity, wholesaler confirms/fulfills, a real order record exists) instead of the v1 direct
one-click import - **yes, confirmed**; (2) should "CRM/customers" mean the wholesaler's *seller* clients
(not end consumers) - **yes, confirmed**. Both directly replace/extend v1's design below.

### What changed from v1

v1's `Seller\WholesalerMarketplaceController::import()` (one click, immediately creates the seller's
`Product`) is **gone**. In its place: a seller places a `WholesaleOrder` (quantity + their chosen resale
price), the wholesaler reviews it through a real lifecycle - pending → accepted → shipped → delivered (or
rejected/cancelled) - and **only the "delivered" transition actually creates or restocks the seller's
`Product`** (`App\Services\WholesaleOrderService::fulfill()`, extracted from v1's import logic almost
unchanged - same product-creation shape, just triggered by fulfillment instead of by the seller directly).
A second order against a listing the seller has already received tops up the existing product's stock
instead of creating a duplicate row (idempotent by `wholesaler_product_id` + `seller_id`, and each order's
own `fulfilled_product_id` column makes fulfillment idempotent per-order too - retrying a request can never
double-create a product or double-add stock).

### New table: `wholesale_orders` (`2025_02_22_000000_create_wholesale_orders.php`)

One row per order: `wholesaler_id`/`wholesaler_product_id`/`seller_id`/`store_id`, `quantity`, `unit_price`
(the wholesale price *at order time*, not a live reference - price changes later don't retroactively change
a placed order), `total_amount`, `retail_price` (the seller's chosen resale price, captured up front so
fulfillment doesn't need the seller present), `status`, `payment_status` (manually marked - no payment
gateway integration, matching this module's existing "settlement needs a business decision" note below),
and `fulfilled_product_id`.

### Seller side (`Seller\WholesalerMarketplaceController`)

- Marketplace browse table's action button is now "Place Order" (was "Import") - opens a modal for quantity
  (respecting the listing's `min_order_qty`) and retail price, shows a live running total.
- New **My Orders** page (`seller/wholesaler_marketplace/orders`) - every order this seller has placed, its
  status, and a Cancel action (only while still pending).

### Wholesaler side (four new controllers, four new sidebar links)

- **Orders** (`Wholesaler\OrderController`) - the incoming queue, filterable by status, with
  accept/reject/mark-shipped/mark-delivered actions (each gated to the *legal* prior status - e.g. you can't
  ship a still-pending order - `422` otherwise) and a "Mark Paid" toggle. Also the **POS ask**: a "Create
  Order" quick-entry page where the wholesaler logs a phone/in-person order on a seller's behalf, picking
  from existing `seller_store` rows - created **pre-accepted** (status = accepted), since the wholesaler
  creating it themselves already implies agreement, matching how a seller's own POS skips their storefront
  checkout flow the same way.
- **Stock** (`Wholesaler\StockController`) - the "مخزون" ask: a dedicated view of the wholesaler's own
  catalog sorted by lowest stock first, with a quick add/subtract adjustment modal (clamped at 0) instead of
  opening the full product edit form for a routine restock.
- **Sales** (`Wholesaler\ReportController`) - the "مبيعات" ask: revenue/order counts/unpaid-amount cards
  plus top-5 products and top-5 buyers, all derived from `wholesale_orders` (`status = delivered` only,
  matching "sales" = orders actually fulfilled, not just placed) - this is the only real transaction ledger
  this module has (see "still deferred" below).
- **My Buyers** (`Wholesaler\ClientController`) - the "CRM وعملاء" ask, scoped correctly per the product
  owner's own confirmation (a wholesaler's clients are sellers, not end consumers): one row per seller
  who's ordered, with order count/total spent/last order date, grouped straight off `wholesale_orders`.
  Deliberately **not** built on the existing `customer_notes`/`customer_tags` tables (Phase 11 CRM) - those
  are keyed on `customer_user_id` (an end-consumer concept) and don't fit a seller-as-client relationship;
  reusing them would have been a schema misuse, not a genuine avoid-duplication win. If per-seller notes/tags
  are wanted later, that's a small, additive follow-up on top of this same buyers list.

### Still deferred (unchanged from v1, now more clearly bounded by having a real order table)

`payment_status` is a manual flag - no payment gateway integration, no automatic settlement/payout to the
wholesaler when an order is marked paid. This remains the same open business-model question v1's doc flagged
("commission-on-sale vs. wholesale-price-is-the-whole-transaction vs. something else"), just now sitting on
top of a real order ledger instead of nothing - whichever model gets chosen, `wholesale_orders` is the table
it would post entries against.

### Verification

`tests/Feature/Wholesaler/WholesaleOrderLifecycleTest.php` (10 tests): accept → ship → deliver creates the
product with the seller's chosen retail price and ordered quantity; a repeat order for an already-fulfilled
listing tops up stock instead of duplicating the product; fulfilling the same order twice is a no-op; a
still-pending order can't be shipped (422); a wholesaler can't transition another wholesaler's order (404);
a seller can cancel their own pending order; the POS-style Create Order starts pre-accepted; stock
adjustment clamps at 0; the sales report and buyers list both reflect a delivered order (and the sales
report explicitly excludes a still-pending one). `WholesalerModuleTest`'s old import tests were replaced
with a "seller can place a wholesale order" test (asserting the seller's catalog is *not* touched until
fulfillment, the key behavior change from v1). `MigrationBaselineTest`'s table count updated (126 -> 127).

Also live-QA'd end-to-end via Playwright + direct verification: a seller placed a real order through the
UI, the wholesaler's own "Accept" button fired the real AJAX transition (confirmed via a DB read, since this
sandbox's single-threaded dev server made the *remaining* ship/deliver browser clicks unreliable to await -
not a sign of an application bug, given the identical logic is what the 10 lifecycle tests exercise
directly), and the Sales/My Buyers pages were screenshotted showing correct real numbers ($45 revenue, 1
delivered order, "Demo Seller" listed as a buyer) after fulfillment ran.

Full suite: 680 passing (one pre-existing, unrelated failure - `AdminHomePerformanceTest`), zero
regressions.

## Phase 6 (master architecture prompt): Wholesale Pricing

The 81-section "VALUE MARKET — COMPLETE MASTER ARCHITECTURE & RESTRUCTURING PROMPT" reframes this whole
module as the platform's **Supplier** role (section 18) - confirmed with the product owner: the code stays
`wholesaler_*` (tables/models/routes/permissions unchanged, already in production), only user-facing labels
now read "Supplier" (see `docs/SIDEBAR_ENGINE.md`'s "Supplier vs Wholesaler naming" section for the full
decision, including the unrelated `App\Models\Supplier` procurement-vendor model being renamed to
`ProcurementVendor` to remove the name collision).

Section 18's "Wholesale" group asks for Wholesale Pricing / MOQ / Bulk Pricing / Seller Pricing /
Seller-Specific Pricing / Quantity Discounts. MOQ already existed (`wholesaler_products.min_order_qty`,
enforced since v2's order-placement validation) - what was missing was *how much per unit*, which this pass
adds:

- **`wholesaler_product_price_tiers`** (`database/migrations/2025_02_23_000000_create_wholesaler_product_price_tiers.php`)
  - one table covers every case in the brief: `seller_id = null` is a generic quantity-break price open to
  every seller; `seller_id` set is a negotiated price for that one seller only.
- **`WholesalerProduct::priceFor(int $sellerId, int $quantity): float`** - picks the tier with the highest
  `min_quantity` the requested quantity still satisfies, preferring a seller-specific tier over a generic
  one at the same threshold, falling back to the listing's flat `wholesale_price` when nothing matches.
- **`Wholesaler\PricingController`** (new) - a wholesaler manages tiers on their own listings only
  (`index`/`tiersList`/`store`/`destroy`, all ownership-checked the same way `ProductController` already
  is); only sellers who have actually ordered from this wholesaler are offered for a seller-specific tier
  (reuses `WholesaleOrder` the same way `ClientController`'s "My Buyers" does), rather than a free-text
  seller id field.
- **`Seller\WholesalerMarketplaceController::previewPrice()`** (new) - a live AJAX price preview as the
  seller changes quantity in the order modal; `list()` and `placeOrder()` now resolve the real price via
  `priceFor()` instead of the flat `wholesale_price` - the client never computes or is trusted for the
  price, matching how every other priced action in this app works.
- Sidebar: a new "Wholesale Pricing" item in the Wholesaler panel (`config/sidebar.php`).

### Verification

`tests/Feature/Wholesaler/WholesalePricingTest.php` (7 tests): flat-price fallback with no tiers, correct
tier selection across multiple quantity thresholds, a seller-specific tier winning over a generic one at
the same quantity (and NOT applying to a different seller), an order placed at a tiered quantity storing
the resolved price (not the flat one), the price-preview endpoint reflecting a seller-specific tier, and
ownership enforcement on both adding and deleting a tier (a different wholesaler gets 404, not the tier).
Also live-QA'd via Playwright: added a tier as the demo wholesaler, watched it appear in the tiers table;
opened the seller's order modal and confirmed the "Unit Price / Total Amount" line updates from a real AJAX
call as quantity changes (not a client-side calculation), matching the network request visible in the debug
bar. Full suite: 687 passing (the same one pre-existing, date-dependent `AdminHomePerformanceTest` failure
- confirmed via `git stash` unrelated to any change in this session), zero regressions.
`MigrationBaselineTest`'s table count updated (127 -> 128).

## Next steps in this re-architecture

Per the master architecture prompt's own phase order (confirmed with the product owner): Unified Dynamic
Sidebar Engine (done, `docs/SIDEBAR_ENGINE.md`) -> Admin/Seller navigation audit (done - the existing
structure already matches the target spec; the real gaps found are backend features, not nav wiring - see
that doc) -> **Supplier architecture (in progress - pricing tiers this pass; MOQ enforcement, the order
lifecycle, and "My Buyers" already existed from v1/v2 above; still open: a Wholesaler-owned POS terminal,
Explore/Request/Approve seller relationship workflow, a wholesaler's own delivery boys, marketing tools, and
a full finance ledger beyond the sales report - each its own scoped follow-up)** -> Affiliate architecture
-> Creator Module inside Affiliate -> Theme System -> Domain/Subdomain -> Subscription/Feature Controls ->
app-wide RTL audit -> Responsive UI -> Performance -> regression testing. Each remaining phase is its own
multi-session effort.
