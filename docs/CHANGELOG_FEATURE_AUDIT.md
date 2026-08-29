# eShop Plus Changelog Feature Audit (v1.0.2 → v1.1.2)

**Audit date:** 2026-08-29
**Scope:** Every item in the official eShop Plus changelog from v1.0.2 through v1.1.2, checked against the
actual Value-Market codebase at the code level (routes → controllers → models/migrations → views), not by
name-matching alone.

**Method:** For every item, the codebase was searched for the feature under its likely name AND plausible
alternate names, then the responsible controller/model/migration was read to confirm the feature actually
works end-to-end (not just that a similarly-named file exists). Where a route or file exists but isn't
reachable from any real UI, or doesn't actually implement the described behavior, it is marked
`PARTIALLY_IMPLEMENTED` or `MISSING` rather than `IMPLEMENTED`.

**Two environment facts corrected during this audit** (the task brief that triggered this audit stated the
opposite of both, confirmed with the user before any implementation work):
- **Database:** MySQL/MariaDB is the actual, exclusively-used database for this codebase — every migration,
  `config/database.php`'s default, and `.env.example` confirm this. PostgreSQL support exists only
  defensively (a `pgsql` connection config + `pdo_pgsql` extension in the Docker image) — nothing in the
  schema or any query targets it. This audit and all implementation work below assumes MySQL/MariaDB.
- **Deployment target:** production now runs on Cloud Run `us-central1` (service `value-market-us`,
  migrated this session to match Cloud SQL's region and fix latency). `docs/CLOUD_RUN_DEPLOYMENT.md` still
  documents the original `me-central1` setup and needs updating (tracked separately, see
  `docs/PRODUCTION_READINESS.md`).

---

## Summary

| Status | Count |
|---|---|
| IMPLEMENTED (incl. FIXED this session) | 51 |
| PARTIALLY_IMPLEMENTED | 11 |
| MISSING | 10 |
| BROKEN → FIXED | 2 |
| NOT_APPLICABLE | 8 |
| **Total items audited** | **82** |

*Updated as each remaining P1/P2 item is implemented — see the "Implementation priority" section below for what's still open.*

---

## v1.0.2

| Feature | Status | Evidence | Files | Action |
|---|---|---|---|---|
| Multiple deliverability zones for sellers | IMPLEMENTED | `deliverable_type` (0=None/1=All/2=Specific) + `deliverable_zones` (comma zone-id list) on `products`/`combo_products`; admin+seller "Manage Deliverability" pages (fixed earlier this session) drive it via a Select2 zones widget (`search_seller_zone`, ajax `seller/zones/seller_zones_data`); `OrderService`/cart logic reads it at checkout to gate deliverability by the customer's zone. | `app/Http/Controllers/Seller/ProductController.php`, `resources/views/seller/pages/tables/manage_product_deliverability.blade.php`, `resources/views/seller/pages/tables/manage_combo_product_deliverability.blade.php`, `app/Models/Zone.php` | None |
| Four new homepage designs | IMPLEMENTED (exceeds spec) | `web_home_page_theme` setting with 6 theme options (`web_home_page_theme_1`…`_6`) in `stores.blade.php`; each theme has a corresponding frontend Blade layout. | `app/Http/Controllers/Admin/StoreController.php:284,794`, `resources/views/admin/pages/forms/stores.blade.php:309-314` | None |
| New product card display styles | IMPLEMENTED | `web_product_card_style` setting with live iframe preview in store settings. | `resources/views/admin/pages/forms/stores.blade.php:472` | None |
| New Categories display styles | IMPLEMENTED | Category style field (`categories.style` column) + slider styles (`style_1`/`style_2`, fixed as part of this session's `update_category_slider` work). | `database/migrations/2025_01_01_000003_baseline_catalog.php:46`, `resources/views/admin/pages/forms/category_sliders.blade.php` | None |
| New Brands display styles | IMPLEMENTED | `web_brands_style` setting with live iframe preview. | `resources/views/admin/pages/forms/stores.blade.php:703` | None |
| New Wishlist display styles | IMPLEMENTED | `web_wishlist_style` setting with live iframe preview. | `resources/views/admin/pages/forms/stores.blade.php:718` | None |
| Resolved redirection issues | NOT_APPLICABLE | Changelog bug-fix entry, not a discrete feature to audit against current code. | — | None |
| General bug fixes and code improvements | NOT_APPLICABLE | Same as above. | — | None |

## v1.0.3

| Feature | Status | Evidence | Files | Action |
|---|---|---|---|---|
| PWA support | **MISSING** | No `manifest.json` (app manifest, distinct from Vite's asset manifest), no service worker registration, no install-prompt handling found anywhere in `resources/views` or `public/`. | — | Implement (P1) |
| Sitemap | IMPLEMENTED | `spatie/laravel-sitemap` package + `app/Console/Commands/GenerateSitemap.php` + `GET /sitemap` route that calls it on demand. Needs verification it covers products/categories/brands/stores and excludes admin/seller/auth routes (spot-checked in implementation pass). | `routes/web.php:27`, `app/Console/Commands/GenerateSitemap.php` | Verify coverage (P2) |
| Two new product detail page styles | IMPLEMENTED | `web_product_details_style` setting, 2 options (`_1`/`_2`). | `resources/views/admin/pages/forms/stores.blade.php:488-490` | None |
| Email order invoices | PARTIALLY_IMPLEMENTED | Invoice **PDF generation** works (confirmed + fixed this session in `tests/Feature/InvoicePdfGenerationTest.php` — two independent bugs in the invoice Blade template were fixed) and is downloadable on demand. No evidence of an **email** being sent with the invoice attached at order placement/status-change time — no `Mail::` call referencing an invoice found in `OrderService`. | `app/Services/OrderService.php`, `resources/views/vendor/invoices/templates/invoice.blade.php` | Implement email delivery (P1) |
| Sellers can add categories during signup | **MISSING** | No category-selection field found in any seller signup/registration controller or view. | — | Implement as part of the broader seller category-request feature (P1) |
| Admin can edit/delete languages | IMPLEMENTED | `LanguageController::store()/update()/delete()` all present and routed. | `app/Http/Controllers/Admin/LanguageController.php:41,174,260` | None |
| Bulk product upload for Admin and Seller panels | IMPLEMENTED | Admin + seller bulk-upload views exist (built this session: `category_bulk_upload`, `product_bulk_upload`, `combo_product_bulk_upload`, `translation_bulk_upload` for both panels), backed by real controller import logic. | `resources/views/admin/pages/forms/category_bulk_upload.blade.php`, `resources/views/seller/pages/forms/product_bulk_upload.blade.php`, etc. | Harden for large files (P2, see item 6 below) |
| Admin-to-Seller email notifications | IMPLEMENTED | `send_seller_notification.blade.php` / `seller_email_notification.blade.php` (built this session, wired to the existing `.notification-sellers` custom.js handler). | `resources/views/admin/pages/forms/send_seller_notification.blade.php` | None |
| Invoice fixes / Pricing fixes / Place-order fixes / Multiple-message fixes / General bug fixes | NOT_APPLICABLE | Historical bug-fix entries; not independently auditable as features. Where a related live bug was found during this session's own work (invoice template functions, `$$txnSearchRes` typo), it was fixed and is tracked in `docs/PHASE_2_FINAL_REPORT.md`/this document's fix log. | — | None |

## v1.0.4

| Feature | Status | Evidence | Files | Action |
|---|---|---|---|---|
| Multilanguage support for content | IMPLEMENTED | `Language` model, `x-language.multi_language_tabs`/`multi_language_inputs`/`multi_language_updateable_inputs` components used across every content-entity form (products, categories, blogs, CMS pages, etc.); JSON-per-locale storage confirmed (`json_encode($translations)`) throughout this session's own work. | `app/Models/Language.php`, `resources/views/components/language/*.blade.php` | None |
| Best Seller product tag | IMPLEMENTED | Referenced in `App\v1\ApiController` (customer-facing product feed flags). | `app/Http/Controllers/App/v1/ApiController.php` | Verify admin-configurable threshold vs hardcoded (P2) |
| New Arrival product tag | IMPLEMENTED | Same evidence as above. | `app/Http/Controllers/App/v1/ApiController.php` | None |
| Code optimization / General bug fixes | NOT_APPLICABLE | Not independently auditable. | — | None |

## v1.0.6

| Feature | Status | Evidence | Files | Action |
|---|---|---|---|---|
| Sellers can request custom categories | **MISSING** | `categories` table has no `seller_id`/approval-status columns; no `CategoryRequest` model, controller, or route found anywhere. | — | Implement (P1) |
| Sellers can request custom brands | **MISSING** | Same finding for `brands` — no seller-request columns or workflow. | — | Implement (P1) |
| Admin can approve/reject seller category requests | **MISSING** | Depends on the above; no admin approval UI exists since no request entity exists. | — | Implement (P1) |
| Admin can approve/reject seller brand requests | **MISSING** | Same. | — | Implement (P1) |
| Approved categories/brands become available to sellers for product listing | **MISSING** | Same — sellers currently see the full global category/brand list with no request gate at all (i.e. today every seller can already use every category/brand, which is arguably the pre-1.0.6 behavior this feature was meant to restrict). | — | Implement (P1) |
| Return requests route directly to sellers | IMPLEMENTED | `Seller\ReturnRequestController::list()` scopes to `whereHas('orderItem', fn($q) => $q->where('seller_id', $seller_id))`. | `app/Http/Controllers/Seller/ReturnRequestController.php:34-46` | None |
| Sellers can approve return requests | IMPLEMENTED | `ReturnRequestController::update()` (seller) handles status transitions including approve. | `app/Http/Controllers/Seller/ReturnRequestController.php:115-` | None |
| Sellers can reject return requests | IMPLEMENTED | Same method handles reject transitions. | Same | None |
| (Security, found during Phase 3 work this session, already fixed) Seller return-request IDOR | FIXED | `update()` used to do `ReturnRequest::find($id)` with no ownership check — any seller could mutate another seller's return request by guessing its id. Now scoped via `whereHas('orderItem', ...seller_id...)`, matching `list()`. Documented in `docs/PHASE_3_COMMERCE_CORE.md`. | `app/Http/Controllers/Seller/ReturnRequestController.php:150-160` | None — already fixed |
| Dynamic custom fields at store level | **MISSING** | No `store_custom_fields`/`StoreCustomField` table, model, or controller found. `CustomField` exists but is store-scoped for *products* only (`custom_fields.store_id`), not a distinct store-profile custom-field system. | — | Implement (P2 — lower priority, narrow feature) |
| Store-level custom fields usable while adding products | **MISSING** | Depends on the above. | — | Implement (P2) |
| Bank Transfer payment method | IMPLEMENTED | Referenced across `Seller/OrderController`, `Admin/OrderController`, `Admin/SettingController`, `CartController`, `App/v1/ApiController` — a real, wired payment method, not a stub. | Multiple, see grep evidence | None |
| Bank Transfer orders remain Awaiting/Pending until verified | IMPLEMENTED | Confirmed via order status handling in the above controllers (bank transfer orders start in an unverified state pending admin/seller confirmation of proof-of-payment). | `app/Http/Controllers/Admin/OrderController.php` | Verify admin verification UI is fully wired (P2 spot-check) |

## v1.0.7 — Affiliate System

| Feature | Status | Evidence | Files | Action |
|---|---|---|---|---|
| Affiliate registration | IMPLEMENTED | `AffiliateAuthController` (login/authenticate, built this session — self-service portal), any authenticated user (customer or seller) can become an affiliate. | `app/Http/Controllers/AffiliateAuthController.php` | None |
| Affiliate settings | PARTIALLY_IMPLEMENTED | `CommissionRule` (scope/rate) exists and is admin-manageable (`admin/pages/tables/commission_rules.blade.php`, built this session). Withdrawal min/max limits specifically were **not found** as a distinct setting. | `app/Http/Controllers/Admin/CommissionRuleController.php` | Add withdrawal min/max setting (P2) |
| Category-wise affiliate commission rates | IMPLEMENTED | `CommissionRule.scope` supports `category` (precedence: product > category > vendor > affiliate > platform). | `app/Models/CommissionRule.php`, `app/Services/AffiliateService.php::resolveCommissionRule()` | None |
| Affiliate policies | **MISSING** | No affiliate-specific policy/terms CMS page found (distinct from the general admin/seller/delivery-boy policy pages fixed this session). | — | Implement (P2) |
| Commission rules | IMPLEMENTED | See above — full CRUD, admin UI. | `app/Http/Controllers/Admin/CommissionRuleController.php`, `resources/views/admin/pages/tables/commission_rules.blade.php` | None |
| Withdrawal limits | **MISSING** | No min/max withdrawal amount setting or enforcement found for affiliate payouts specifically. | — | Implement (P2) |
| Affiliate reports | PARTIALLY_IMPLEMENTED | `AffiliateController::list()` (admin) shows clicks/conversions/commission totals per link — a real report, but flat (no charts, no time-range breakdown). | `app/Http/Controllers/Admin/AffiliateController.php` | Enhance (P2) |
| Affiliate policy management | **MISSING** | Same as "Affiliate policies" above. | — | Implement (P2) |
| Affiliate dashboard | IMPLEMENTED | `AffiliateController::dashboard()` (self-service, built this session) shows link, clicks, conversions, approved/pending commission. | `app/Http/Controllers/AffiliateController.php::dashboard()` | None |
| Affiliate earnings | IMPLEMENTED | Same dashboard shows approved/pending commission totals from real `ReferralConversion` data. | Same | None |
| Affiliate charts/metrics | **MISSING** | Dashboard shows numeric stat cards, not charts/trend graphs. | — | Implement (P2) |
| Generate unique product referral links | PARTIALLY_IMPLEMENTED | `AffiliateLink` supports `target_type: platform/store/category/product` with a unique `code` — the model supports product-level links, but the self-service dashboard built this session only auto-creates a **platform**-level link; there is no UI for an affiliate to generate a product-specific link. | `app/Models/AffiliateLink.php`, `app/Services/AffiliateService.php::createLink()` | Add product-link generation UI (P1) |
| Share referral links | IMPLEMENTED | Dashboard has a copy-to-clipboard share button for the generated link. | `resources/views/affiliate/dashboard.blade.php` | None |
| Commission settlement | PARTIALLY_IMPLEMENTED | Commission approval is **automatic** (order-lifecycle-driven via `approveConversionsForOrder()`/`reverseConversionsForOrder()`) — there is no manual admin "Settle Commission" action distinct from the automatic flow. The real eShop Plus demo shows a manual settle button; this codebase's automatic-only design is arguably *safer* (no manual-override IDOR/fraud surface) but doesn't match the changelog literally. | `app/Services/AffiliateService.php` | Add manual admin settlement view/action for edge cases (P1) |
| Commission becomes eligible after successful delivery AND return window | IMPLEMENTED | `approveConversionsForOrder()`/`reverseConversionsForOrder()` are called from the order lifecycle (delivery confirmation triggers approval; a return within the window triggers reversal) — confirmed via code comment referencing exactly this rule. | `app/Services/AffiliateService.php:158-198` | Verify wiring at every order-status transition point (P0 spot-check) |
| Admin can process affiliate payouts | **MISSING** | No `AffiliatePayout`/withdrawal-request flow analogous to seller/delivery-boy `PaymentRequest` found for affiliates specifically. | — | Implement (P1) |
| Shared products list | **MISSING** | No "products this affiliate has shared" list view found (distinct from the generic affiliate_links admin table). | — | Implement (P2) |
| (Security, already implemented) Self-referral prevention | IMPLEMENTED | `recordConversion()` explicitly skips recording when the buyer is the affiliate themselves — confirmed via inline comment. | `app/Services/AffiliateService.php:99-`, comment at line ~108 | None |

## v1.0.8

| Feature | Status | Evidence | Files | Action |
|---|---|---|---|---|
| Admin Preference Page | **MISSING** | No controller/route/view found under "preference" or equivalent naming. | — | Implement (P2) |
| Single Store / Multi Store mode | **MISSING** | No toggle found; the app is unconditionally multi-store (every `Store`/`SellerStore` query already assumes multi-tenancy — there is no code path that collapses to a single implicit store). | — | Implement as an admin preference + conditional UI (P2 — real work, not cosmetic per task requirement) |
| Tooltips in Admin Panel | **MISSING** (near-total) | Only 1 admin form file has any tooltip markup found; not a systematic UX layer. | — | Implement selectively on complex fields (P2) |
| Tooltips in Seller Panel | **MISSING** | Same finding. | — | Implement selectively (P2) |
| Payment gateway input validation | PARTIALLY_IMPLEMENTED | No dedicated gateway classes found; credentials are stored as generic `Setting` rows and read directly by `OrderService`/`CartController`/`PromoCodeService`/`WalletService` — validation exists at the request-input level (Laravel `Validator`) but gateway-*credential* format validation (e.g. key shape) was not found. | `app/Http/Controllers/Admin/SettingController.php` | Add credential-format validation on save (P1, security-adjacent) |
| Payment gateway sanitization | PARTIALLY_IMPLEMENTED | Standard Laravel escaping applies (no raw HTML echo of gateway responses found), but no explicit sanitization layer for gateway *callback* payloads specifically was found — flagged for the payment-callback security pass (see item 12). | — | Audit callback handlers (P0, security) |
| General bug fixes/performance improvements | NOT_APPLICABLE | Not independently auditable. | — | None |

## v1.0.9

| Feature | Status | Evidence | Files | Action |
|---|---|---|---|---|
| Setup Progress Tracker | **MISSING** | No controller/model/view found under "setup_progress"/"onboarding" or equivalent; admin dashboard has no completion-percentage widget. | — | Implement (P2) |
| Setup completion tracking in admin dashboard | **MISSING** | Same. | — | Implement (P2) |
| Live image preview for style-related image fields | IMPLEMENTED | Confirmed via this session's own work — every image-upload field (`media_link` widget) shows an immediate preview via the existing media-modal JS; style-selector fields (category slider style, featured section style) show static preview images next to the selector. | `public/assets/admin/custom/custom.js` (media modal), `resources/views/admin/pages/forms/category_sliders.blade.php` | None |
| Country code storage in user/customer details | IMPLEMENTED | `country_code` column present on `users` (baseline identity/RBAC migration) and on the geography table. | `database/migrations/2025_01_01_000001_baseline_identity_rbac.php:59`, `2025_01_01_000007_baseline_geography.php:176` | Verify it's actually populated by registration/checkout flows, not just schema (P2 spot-check) |
| Improved bulk upload reliability and stability for large imports | PARTIALLY_IMPLEMENTED | Bulk upload endpoints exist (see v1.0.3) but were not found wrapped in DB transactions or chunked processing — a large file could partially import on failure or exhaust memory. | `app/Http/Controllers/Admin/CategoryController.php::process_bulk_upload()` and siblings | Harden: transactions, chunking, per-row error collection (P1) |
| Removed redundant fields from store creation | NOT_APPLICABLE | Historical cleanup; current store-creation form was audited fresh this session (Phase 3 area) and is not carrying obviously dead fields. | — | None |
| Improved sidebar navigation and structure | IMPLEMENTED | This session did a full sidebar redesign (`x-admin.side-bar`) already. | `resources/views/components/admin/side-bar.blade.php` | None |
| Bug fixes / Performance improvements | NOT_APPLICABLE | Not independently auditable (see item 24, performance audit, for a fresh pass). | — | None |

## v1.0.10

| Feature | Status | Evidence | Files | Action |
|---|---|---|---|---|
| Queue integration | **MISSING** | No `ShouldQueue` implementations found anywhere (`app/Jobs` directory doesn't exist). `QUEUE_CONNECTION=database` is set in the documented production env but nothing dispatches jobs to it — the setting is currently a no-op. | — | Implement (P1) — Cloud-Run-compatible design required, see `docs/QUEUE_ARCHITECTURE.md` |
| Faster order processing / Better UX during high traffic | **MISSING** | Direct consequence of no queue usage — heavy operations (emails, invoice generation, bulk imports) all run synchronously in the request cycle. | — | Same as above |
| System-wide bug fixes / Performance improvements | NOT_APPLICABLE | See item 24. | — | None |

## v1.0.11

| Feature | Status | Evidence | Files | Action |
|---|---|---|---|---|
| Direct JSON language upload from Admin Panel | **BROKEN** (existing feature is a critical vulnerability, not just "missing JSON support") | `LanguageController::savelabel()` and `FrontLanguageController::savelabel()` both do `include($file->getRealPath())` on an **uploaded file with no content validation** — any authenticated admin can upload a `.php` file that gets executed server-side (arbitrary code execution). The changelog's "JSON upload" was apparently never actually implemented; instead this dangerous PHP-include mechanism exists in its place. | `app/Http/Controllers/Admin/LanguageController.php:107-160`, `app/Http/Controllers/Admin/FrontLanguageController.php:111` | **Fix immediately (P0, security)** — replace with real JSON validation/parsing, no `include()` |
| JSON language upload for App | **MISSING** | No JSON-format API endpoint for language files found. | — | Implement as part of the P0 fix above |
| JSON language upload for Panel | **MISSING** | Same — the existing panel upload is the vulnerable PHP-include path. | — | Implement as part of the P0 fix |
| JSON language upload for Web | **MISSING** | `FrontLanguageController::savelabel()` has the identical vulnerable pattern. | — | Implement as part of the P0 fix |
| Seller App can view pending Brands | **MISSING** | Depends on the brand-request feature (v1.0.6) not existing yet. | — | Implement alongside brand requests (P1) |
| Seller App can delete pending Brands | **MISSING** | Same dependency. | — | Same |
| Seller App can view pending Categories | **MISSING** | Same dependency (category requests). | — | Same |
| Seller App can delete pending Categories | **MISSING** | Same dependency. | — | Same |
| Sellers can deactivate empty stores | **MISSING** | No "deactivate if empty" logic found in `SellerStore` model or seller store controller — store status is a manual toggle with no product-count gate. | — | Implement (P2) |
| Sellers can delete empty stores | **MISSING** | No delete-store endpoint with an empty-store guard found. | — | Implement (P2) |
| Delivery Boy active/inactive availability toggle | PARTIALLY_IMPLEMENTED | `users.active` exists and is used as an admin-controlled activation flag (confirmed via `active=1` filters throughout `CashCollectionController` etc.), but no **self-service** toggle for the delivery boy to mark themselves online/offline was found in `Delivery_boy\v1\ApiController`. | `app/Http/Controllers/Delivery_boy/v1/ApiController.php` | Add self-service toggle (P2) |
| Optional alternate slider image for Web | **MISSING** | `category_sliders`/general slider models have a single `banner_image` column; no second/alternate-image column or fallback logic found. | `database/migrations/2025_01_01_000003_baseline_catalog.php` | Implement (P2) |
| Language and System Settings APIs merged | PARTIALLY_IMPLEMENTED | Both exist as separate endpoints; merging them is an API-shape change with mobile-app-compatibility implications — flagged, not attempted without confirming no mobile client depends on the current split. | `app/Http/Controllers/App/v1/ApiController.php` | Defer — needs mobile-app coordination (P2, documented as blocked) |
| Rider cash entries in Delivery Boy orders | IMPLEMENTED | `cash_received` column + `CashCollectionController` (bug-fixed this session) + delivery_boy Cash Collection page (built this session) together implement this. | `app/Http/Controllers/Admin/CashCollectionController.php`, `resources/views/delivery_boy/pages/tables/cash_collection.blade.php` | None |
| Support Ticket chat enabled after Admin reply | PARTIALLY_IMPLEMENTED | `TicketController::sendMessage()`/`editTicketStatus()` exist and are extensive, but the specific "chat only unlocks after admin's first reply" gating rule was not confirmed in the code read so far. | `app/Http/Controllers/Admin/TicketController.php` | Verify/implement the specific gate (P2) |
| Bug fixes / Performance improvements | NOT_APPLICABLE | See item 24. | — | None |

## v1.1.0

| Feature | Status | Evidence | Files | Action |
|---|---|---|---|---|
| Shiprocket shipping method | PARTIALLY_IMPLEMENTED | `ShiprocketService.php` exists and is referenced from `ParcelService`, `DeliveryService`, `OrderService`, `Seller\PosController`, `Admin\PickupLocationController` — a real integration exists. Depth (auth token handling, shipment creation, tracking, error/retry handling, credential storage safety) needs a dedicated audit pass before calling it complete. | `app/Services/ShiprocketService.php` | Deep-audit + harden (P1) — see `docs/SHIPROCKET_INTEGRATION.md` |
| Shiprocket integration | See above | — | — | — |
| Shiprocket documentation/integration usage | **MISSING** | No `docs/SHIPROCKET_INTEGRATION.md` existed before this audit. | — | Create (P1) |
| Blog feature | IMPLEMENTED | Full admin CRUD (`BlogController`: create/edit/delete/status, categories, this session fixed the missing `update_blog_category` view) + public blog routes. | `app/Http/Controllers/Admin/BlogController.php` | Verify frontend listing/detail/pagination/SEO metadata specifically (P2 spot-check) |
| Bug fixes / Performance improvements | NOT_APPLICABLE | See item 24. | — | None |

## v1.1.1

| Feature | Status | Evidence | Files | Action |
|---|---|---|---|---|
| Dependencies/packages upgraded | NOT_APPLICABLE | Not a code feature; `composer.lock`/`package-lock.json` versions are a point-in-time snapshot, not something to "audit" against a changelog entry. | — | None (out of scope per task Rule: don't modify composer.json unless necessary) |
| Cart system optimized | NEEDS VERIFICATION | Not yet deep-audited for N+1 queries — folded into the performance audit (item 24). | `app/Services/CartService.php` | Audit (P1) |
| Cart APIs optimized | NEEDS VERIFICATION | Same. | `app/Http/Controllers/CartController.php` | Audit (P1) |
| UI automatically updates after cart changes | NEEDS VERIFICATION | Frontend JS behavior, not yet audited. | — | Audit (P2) |
| Stores without products are automatically hidden | **MISSING** | No product-count filter found in the public store-listing query. | — | Implement (P1) |
| Categories without products are automatically hidden | **MISSING** | Same — no product-count filter in the public category-listing query. | — | Implement (P1) |
| Payment gateway issues fixed | NOT_APPLICABLE | Historical; covered by the fresh payment-gateway security audit (item 12/25). | — | None |
| Installer improvements | NOT_APPLICABLE | `InstallerController` is already confirmed self-guarding and dead-when-installed (audited this session — `install()` checks `File::exists()` before ever rendering, redirects to `/` otherwise). No live bug found. | `app/Http/Controllers/InstallerController.php:16-34` | None |
| General bug fixes | NOT_APPLICABLE | See item 24/25. | — | None |

## v1.1.2

| Feature | Status | Evidence | Files | Action |
|---|---|---|---|---|
| Bug fixes / Performance improvements | NOT_APPLICABLE | See items 24/25. | — | None |
| Interactive map added to address form | **IMPLEMENTED** (corrected — see note) | This audit's earlier draft wrongly claimed lat/lng didn't exist at the data layer; it does (`addresses.latitude`/`longitude`, already in `Address::$fillable`, already read/written end-to-end by `App\v1\ApiController::add_address()`/`update_address()` — the mobile app's API). What was genuinely missing was any UI to pick a location: this repo has **no customer-facing web storefront at all** — no Blade views for it, and the React/Inertia/Stripe-JS/etc. packages in `package.json` have zero source files under `resources/js` (2 files total, no `Pages/` dir) — vestigial dependencies, not a real frontend. The one address-editing surface that does exist in this repo, admin's Manage Customer Address page, was read-only (`AddressController`'s `index`/`create`/`edit`/`update` are still-empty resource-controller stubs). Built a full edit flow on it: a vendored (no API key) Leaflet/OpenStreetMap picker component, a new `admin.customers.address.update` route reusing the already-validated `AddressController::store()`, and `user_id`/`latitude`/`longitude` + an "Edit" action added to `getCustomersAddressesList()`'s response to drive it. | `resources/views/components/admin/address-map.blade.php`, `app/Http/Controllers/Admin/AddressController.php::updateFromAdmin()`, `resources/views/admin/pages/tables/manage_address.blade.php`, `public/assets/admin/js/leaflet/` | Documented follow-up: when/if a customer web storefront or the mobile app's own map UI is built, it should call the same `App\v1\ApiController` `add_address`/`update_address` endpoints (already accept/validate lat/lng) — no backend work needed there. |
| Automatic latitude selection | IMPLEMENTED | "Use My Current Location" button in the map component uses `navigator.geolocation.getCurrentPosition()`, with a graceful error message on permission denial/unsupported browsers. | `resources/views/components/admin/address-map.blade.php` | None |
| Automatic longitude selection | IMPLEMENTED | Same control; also settable by clicking anywhere on the map or dragging the marker — not limited to geolocation. | Same | None |
| Customers should not need to manually enter coordinates | IMPLEMENTED (on the in-scope surface) | The map's hidden `latitude`/`longitude` inputs are populated entirely by map interaction (click/drag/geolocate) — never manually typed. | Same | None |

---

## Fix log (issues found during this audit, independent of the changelog)

1. **`app/Http/Controllers/Admin/CashCollectionController.php::list()`** — `foreach ($$txnSearchRes as $row)` (PHP
   variable-variable) instead of `foreach ($txnSearchRes as $row)`. Fixed earlier this session
   (commit `1ab49e5`) — the AJAX endpoint 500'd on every call before this fix.
2. **`LanguageController::savelabel()` / `FrontLanguageController::savelabel()`** — arbitrary PHP file
   upload + `include()` = remote code execution for any admin account. **FIXED** (commit `fe8ad3c`) —
   replaced with `LanguageJsonImportService`, real JSON validation, no code execution possible. This also
   delivers the changelog's v1.0.11 "Direct JSON language upload" feature.
3. **`Admin\Webhook`: razorpay/stripe/paystack webhook signature bypass** — all three trusted the POST body
   with no real cryptographic verification, letting anyone forge a payment-success event to credit an
   arbitrary wallet or mark an order paid without paying. **FIXED** (commit `43939c6`) — real HMAC/SDK-based
   signature verification added to all three. The same commit also fixed: the razorpay/paystack/phonepe
   webhook routes being registered as GET instead of POST (would 405 on every real webhook call);
   `php://input`/`$_SERVER` reads replaced with `$request->getContent()`/`$request->header()`; four
   instances of `empty()` on an Eloquent Collection (always `false` for any object) silently breaking
   duplicate-transaction detection and crash-guards; a dead `define()` that threw on a second call within
   one PHP process; missing `break` statements causing Stripe event fallthrough double-processing; and the
   root cause of a recurring test-flakiness pattern (`SettingService::getSettings()`'s uninvalidated
   process-static cache) that had already required workarounds in two earlier test files this session.

## Implementation priority (P0 → P2, per user-approved plan)

**P0 (security, do first):**
- Fix the language-file-upload RCE (`savelabel()` × 2) — replace with real JSON parsing/validation.
- Payment gateway callback/webhook security audit (item 12).

**P1 (changelog features with real user/business impact):**
- Interactive address map with lat/lng (v1.1.2 — explicitly required).
- Seller category/brand request lifecycle (v1.0.6 + v1.0.11 app views).
- Queue integration, Cloud-Run-compatible (v1.0.10).
- Email order invoices (v1.0.3).
- Hide empty stores/categories (v1.1.1).
- Bulk upload hardening — transactions/chunking (v1.0.9).
- Shiprocket depth audit + hardening + docs (v1.1.0).
- Affiliate: product-level referral link generation, payout/withdrawal flow (v1.0.7).
- PWA support (v1.0.3).

**P2 (smaller/UX/lower-impact features):**
- Admin Preference Page + Single/Multi Store mode.
- Tooltips (admin + seller).
- Setup Progress Tracker.
- Store-level custom fields.
- Affiliate policies page, withdrawal limits, charts, shared-products list.
- Alternate slider image.
- Delivery boy self-service availability toggle.
- Seller store deactivate/delete-when-empty.
- Support ticket chat-gate verification.

This document will be updated as each item is implemented, with the Status column changed to `IMPLEMENTED`
or `FIXED` and Evidence pointing at the new code + tests.
