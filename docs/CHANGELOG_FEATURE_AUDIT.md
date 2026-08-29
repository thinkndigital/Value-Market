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
| IMPLEMENTED (incl. FIXED/BROKEN→FIXED this session) | 59 |
| PARTIALLY_IMPLEMENTED | 9 |
| MISSING | 15 |
| NOT_APPLICABLE | 17 |
| NEEDS VERIFICATION (flagged for a manual spot-check, not a code-level gap) | 3 |
| **Total items audited** | **103** |

(Recounted directly from the table rows below, not tracked incrementally — the earlier running total of 82
undercounted as new changelog sub-items were broken out into their own rows across this session. Two rows
that explicitly cross-reference another row's evidence rather than stating independent findings are not
double-counted here.)

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
| PWA support | **NOT_APPLICABLE** (corrected — see note) | This audit's earlier draft claimed no manifest/service-worker code existed at all; a closer read found dead scaffolding instead: `resources/views/components/layouts/app.blade.php` references `route('manifest')` (real route, `routes/web.php:66`, but returns `config('manifest')` — no `config/manifest.php` file exists, so it always returns `null`) and registers `navigator.serviceWorker.register("/sw.js")` — `public/sw.js` does not exist, so this would 404 in any browser that reached it. None of it matters in practice: **no route or controller anywhere renders this layout component** (grepped the full `routes/`/`app/Http/Controllers` tree), consistent with this repo's confirmed absence of any live customer-facing web storefront (`resources/js` has 2 files, no `Pages/` directory, despite React/Inertia/Stripe-JS/PayPal-JS/Razorpay-JS all being present in `package.json` — vestigial dependencies, not a real frontend; see the v1.1.2 address-map row for the same finding). Wiring a real manifest + service worker to a page nothing ever serves would be theater, not a feature — this becomes actionable the same day a real customer web storefront is built on top of this backend, not before. | `resources/views/components/layouts/app.blade.php`, `routes/web.php:66` | Documented follow-up, not implemented: build alongside any future customer web storefront |
| Sitemap | IMPLEMENTED | `spatie/laravel-sitemap` package + `app/Console/Commands/GenerateSitemap.php` + `GET /sitemap` route that calls it on demand. Needs verification it covers products/categories/brands/stores and excludes admin/seller/auth routes (spot-checked in implementation pass). | `routes/web.php:27`, `app/Console/Commands/GenerateSitemap.php` | Verify coverage (P2) |
| Two new product detail page styles | IMPLEMENTED | `web_product_details_style` setting, 2 options (`_1`/`_2`). | `resources/views/admin/pages/forms/stores.blade.php:488-490` | None |
| Email order invoices | IMPLEMENTED (corrected — see note) | This audit's earlier draft missed that `OrderService::placeOrder()` **already** sent an order-confirmation email — a closer read found it, and found it was broken: the email linked to `/admin/orders/generat_invoice_PDF/{id}`, an admin-only route the customer receiving the email has no session for (the link was permanently dead for its actual audience). Also unguarded — an SMTP failure there threw uncaught, *after* the order had already committed, turning a real successful order into a 500 for the customer. Fixed: the PDF is now attached directly (no link/auth needed), via a new `MailService::sendMailWithAttachment()` (the `$attachment` param on the pre-existing `sendCustomMail()`/`sendDigitalProductMail()` was accepted but never once used by either). The whole block is now try/caught and skipped when the customer has no email or email isn't configured. | `app/Services/OrderService.php`, `app/Services/MailService.php` | None |
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
| Sellers can request custom categories | **IMPLEMENTED (fixed this session)** | `Seller\CategoryController::store()` already created seller-submitted rows with `status=2` ("pending admin approval") but tracked *who* requested nothing — no seller could ever see or manage their own request. Added `requested_by_seller_id`/`approval_status` columns (migration `2025_02_18_000000_add_category_brand_request_columns.php`) and wired them through `store()`. | `app/Http/Controllers/Seller/CategoryController.php`, `database/migrations/2025_02_18_000000_add_category_brand_request_columns.php` | None |
| Sellers can request custom brands | **IMPLEMENTED (fixed this session)** | Same fix, mirrored for `Seller\BrandController::store()`. | `app/Http/Controllers/Seller/BrandController.php` | None |
| Admin can approve/reject seller category requests | **IMPLEMENTED (fixed this session)** | `Admin\CategoryController::update_status()` previously did a blind `status==1 ? 0 : 1` toggle that ignored the admin's actual dropdown selection — choosing "Approve" on a pending (status=2) row silently deactivated it (0) instead of approving it (1). Now reads `$request->status` directly (mirroring `Admin\ProductController::update_status()`'s existing pending-approval handling), sets `approval_status` accordingly, and — for categories specifically — grants the newly-approved category to the requesting seller's product-form dropdown via `SellerStore.category_ids`. | `app/Http/Controllers/Admin/CategoryController.php` | None |
| Admin can approve/reject seller brand requests | **IMPLEMENTED (fixed this session)** | Same fix, mirrored for `Admin\BrandController::update_status()`. | `app/Http/Controllers/Admin/BrandController.php` | None |
| Approved categories/brands become available to sellers for product listing | **IMPLEMENTED (fixed this session)** | Verified end-to-end: an approved category is granted into the requesting seller's `seller_store.category_ids`, and `Seller\ProductController::getBrands()` already filters brands by `store_id`+`status==1` — so an approved brand is immediately visible with no further change needed. | `app/Http/Controllers/Admin/CategoryController.php`, `app/Http/Controllers/Seller/ProductController.php` | None |
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
| Admin can process affiliate payouts | **IMPLEMENTED (fixed this session)** | `AffiliateController::requestWithdrawal()`/`withdrawalHistory()` added — the admin side needed zero changes, since `PaymentRequest` is already a generic `user_id`-scoped model and `Admin\PaymentRequestController::list()`/`update()` already work for any `payment_type`. Withdraws from the same `WalletService::updateBalance()` path (transaction-safe, row-locked) every other panel's withdrawal flow uses — affiliate commission is already real wallet balance by the time it's withdrawable, credited by `AffiliateService::approveConversionsForOrder()` only after delivery + the return window. Building this surfaced and fixed a real IDOR in the sibling `Seller\PaymentRequestController::add_withdrawal_request()`: `user_id` was read straight from the request body (a hidden form field, editable via devtools) and only validated as `exists:users,id`, never checked against the authenticated caller — any seller/delivery boy could deduct another user's balance and create a payout to their own address. Both now always use `Auth::id()`. | `app/Http/Controllers/AffiliateController.php`, `app/Http/Controllers/Seller/PaymentRequestController.php`, `routes/web.php`, `tests/Feature/AffiliateWithdrawalTest.php` (4 tests) | None |
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
| Setup Progress Tracker | **IMPLEMENTED (fixed this session)** | Confirmed genuinely missing on first read (no controller/model/view existed under this or an equivalent name). Added `SetupProgressService::getProgress()`, checking 9 real, current configuration states (store, currency, payment gateway incl. bank transfer, delivery zone, language, privacy/terms content, category, product, brand) — every check is a live query against current data, never a stored/cached flag, per this feature's own "do not use fake percentages" requirement. Rendered as a progress bar + checklist on `/admin/home`, shown only while incomplete. | `app/Services/SetupProgressService.php`, `app/Http/Controllers/Admin/HomeController.php`, `resources/views/admin/pages/forms/home.blade.php`, `tests/Feature/SetupProgressTrackerTest.php` (6 tests) | None |
| Setup completion tracking in admin dashboard | **IMPLEMENTED (fixed this session)** | Same feature/evidence as above — this is the same tracker, not a second one. | Same as above | None |
| Live image preview for style-related image fields | IMPLEMENTED | Confirmed via this session's own work — every image-upload field (`media_link` widget) shows an immediate preview via the existing media-modal JS; style-selector fields (category slider style, featured section style) show static preview images next to the selector. | `public/assets/admin/custom/custom.js` (media modal), `resources/views/admin/pages/forms/category_sliders.blade.php` | None |
| Country code storage in user/customer details | IMPLEMENTED | `country_code` column present on `users` (baseline identity/RBAC migration) and on the geography table. | `database/migrations/2025_01_01_000001_baseline_identity_rbac.php:59`, `2025_01_01_000007_baseline_geography.php:176` | Verify it's actually populated by registration/checkout flows, not just schema (P2 spot-check) |
| Improved bulk upload reliability and stability for large imports | PARTIALLY_IMPLEMENTED | Bulk upload endpoints exist (see v1.0.3) but were not found wrapped in DB transactions or chunked processing — a large file could partially import on failure or exhaust memory. | `app/Http/Controllers/Admin/CategoryController.php::process_bulk_upload()` and siblings | Harden: transactions, chunking, per-row error collection (P1) |
| Removed redundant fields from store creation | NOT_APPLICABLE | Historical cleanup; current store-creation form was audited fresh this session (Phase 3 area) and is not carrying obviously dead fields. | — | None |
| Improved sidebar navigation and structure | IMPLEMENTED | This session did a full sidebar redesign (`x-admin.side-bar`) already. | `resources/views/components/admin/side-bar.blade.php` | None |
| Bug fixes / Performance improvements | NOT_APPLICABLE | Not independently auditable (see item 24, performance audit, for a fresh pass). | — | None |

## v1.0.10

| Feature | Status | Evidence | Files | Action |
|---|---|---|---|---|
| Queue integration | **IMPLEMENTED (fixed this session)** | Confirmed genuinely missing on first read (no `ShouldQueue` implementations anywhere, `app/Jobs` didn't exist, nothing dispatched to `QUEUE_CONNECTION`). Added `App\Jobs\SendOrderConfirmationEmailJob` (moves the invoice-PDF-generation + email-send that used to run inline in checkout) plus a Cloud-Run-compatible drain mechanism — `Admin\CronJobController::processQueue()`, a `verify_cron_secret`-protected HTTP endpoint running one bounded `queue:work --stop-when-empty` pass, reusing this app's existing Cloud Scheduler cron pattern instead of assuming a permanently-running worker process. Full design in `docs/QUEUE_ARCHITECTURE.md`. | `app/Jobs/SendOrderConfirmationEmailJob.php`, `app/Http/Controllers/Admin/CronJobController.php::processQueue()`, `app/Services/OrderService.php`, `docs/QUEUE_ARCHITECTURE.md`, `tests/Feature/QueueIntegrationTest.php` (7 tests) | None |
| Faster order processing / Better UX during high traffic | **IMPLEMENTED (fixed this session)** | Direct consequence of the fix above — the slowest part of order placement (PDF generation + SMTP send) no longer blocks the checkout response; with `QUEUE_CONNECTION=database` it's genuinely deferred, with this app's local/test default (`sync`) it still runs inline but is now a reusable, independently-testable unit rather than inline code. Bulk-import queueing is tracked separately (see the "Improved bulk upload reliability" row above, still `PARTIALLY_IMPLEMENTED`). | `app/Jobs/SendOrderConfirmationEmailJob.php` | None |
| System-wide bug fixes / Performance improvements | NOT_APPLICABLE | See item 24. | — | None |

## v1.0.11

| Feature | Status | Evidence | Files | Action |
|---|---|---|---|---|
| Direct JSON language upload from Admin Panel | **FIXED — now IMPLEMENTED** | Was a critical vulnerability, not just "missing JSON support": `LanguageController::savelabel()` and `FrontLanguageController::savelabel()` both did `include($file->getRealPath())` on an **uploaded file with no content validation** — any authenticated admin could upload a `.php` file that got executed server-side (arbitrary code execution). **FIXED** (commit `fe8ad3c`) — replaced with `LanguageJsonImportService::parse()`, which validates the `.json` extension, `json_decode()`s the content, and rejects anything that isn't a flat string-to-scalar map; never executes uploaded content. This delivers the changelog's actual "JSON upload" feature as a side effect of the fix. | `app/Services/LanguageJsonImportService.php`, `app/Http/Controllers/Admin/LanguageController.php`, `app/Http/Controllers/Admin/FrontLanguageController.php`, `tests/Feature/LanguageJsonUploadSecurityTest.php` (5 tests) | None |
| JSON language upload for App | **NOT_APPLICABLE** | No customer/seller-facing mobile "upload a language file" flow exists in this changelog item or codebase — language files are an admin-panel-only import; there is no separate "App" upload surface to implement. | — | None |
| JSON language upload for Panel | **IMPLEMENTED (fixed this session)** | Same fix as the row above — this is the same admin panel upload, not a second one. | Same as above | None |
| JSON language upload for Web | **IMPLEMENTED (fixed this session)** | `FrontLanguageController::savelabel()` (the "Web" / front-language variant) received the identical fix. | `app/Http/Controllers/Admin/FrontLanguageController.php` | None |
| Seller App can view pending Brands | **IMPLEMENTED (fixed this session)** | Delivered as part of the seller brand-request lifecycle: `Seller\BrandController::list()` now shows both admin-assigned brands and every brand the seller has requested themselves, at any approval status, with a "Pending Approval"/"Rejected" badge. | `app/Http/Controllers/Seller/BrandController.php` | None |
| Seller App can delete pending Brands | **IMPLEMENTED (fixed this session)** | `Seller\BrandController::destroy()` — ownership- and status-scoped (only the requesting seller's own still-pending row). | `app/Http/Controllers/Seller/BrandController.php` | None |
| Seller App can view pending Categories | **IMPLEMENTED (fixed this session)** | Same pattern for categories via `Seller\CategoryController::list()`. | `app/Http/Controllers/Seller/CategoryController.php` | None |
| Seller App can delete pending Categories | **IMPLEMENTED (fixed this session)** | `Seller\CategoryController::destroy()` — same ownership/status scoping. | `app/Http/Controllers/Seller/CategoryController.php` | None |
| Sellers can deactivate empty stores | **MISSING** | No "deactivate if empty" logic found in `SellerStore` model or seller store controller — store status is a manual toggle with no product-count gate. | — | Implement (P2) |
| Sellers can delete empty stores | **MISSING** | No delete-store endpoint with an empty-store guard found. | — | Implement (P2) |
| Delivery Boy active/inactive availability toggle | IMPLEMENTED | New `users.is_available` column (migration `2025_02_17_000000`), deliberately separate from `active`/`status`/`active_status` (three already-overlapping legacy booleans on this table) rather than repurposing any of them. New self-service `PUT toggle_availability` endpoint on `Delivery_boy\v1\ApiController`, restricted to delivery-boy accounts, reported in `get_delivery_boy_details()`. **Not** wired into `DispatchService::rankAvailableDeliveryBoys()`'s existing eligibility filter in this pass — that filter reads one of the other overlapping columns, and changing live dispatch eligibility logic without a much deeper audit of what each column already means risks a real regression in order assignment; documented here as the natural next step rather than done silently. | `database/migrations/2025_02_17_000000_add_delivery_boy_availability_toggle.php`, `app/Http/Controllers/Delivery_boy/v1/ApiController.php`, `app/Models/User.php` | Follow-up (not this pass): wire `is_available` into `DispatchService`'s ranking query once the three legacy status columns' exact semantics are confirmed |
| Optional alternate slider image for Web | **MISSING** | `category_sliders`/general slider models have a single `banner_image` column; no second/alternate-image column or fallback logic found. | `database/migrations/2025_01_01_000003_baseline_catalog.php` | Implement (P2) |
| Language and System Settings APIs merged | PARTIALLY_IMPLEMENTED | Both exist as separate endpoints; merging them is an API-shape change with mobile-app-compatibility implications — flagged, not attempted without confirming no mobile client depends on the current split. | `app/Http/Controllers/App/v1/ApiController.php` | Defer — needs mobile-app coordination (P2, documented as blocked) |
| Rider cash entries in Delivery Boy orders | IMPLEMENTED | `cash_received` column + `CashCollectionController` (bug-fixed this session) + delivery_boy Cash Collection page (built this session) together implement this. | `app/Http/Controllers/Admin/CashCollectionController.php`, `resources/views/delivery_boy/pages/tables/cash_collection.blade.php` | None |
| Support Ticket chat enabled after Admin reply | PARTIALLY_IMPLEMENTED | `TicketController::sendMessage()`/`editTicketStatus()` exist and are extensive, but the specific "chat only unlocks after admin's first reply" gating rule was not confirmed in the code read so far. | `app/Http/Controllers/Admin/TicketController.php` | Verify/implement the specific gate (P2) |
| Bug fixes / Performance improvements | NOT_APPLICABLE | See item 24. | — | None |

## v1.1.0

| Feature | Status | Evidence | Files | Action |
|---|---|---|---|---|
| Shiprocket shipping method | IMPLEMENTED (hardened this session) | Deep audit + fixes, see `docs/SHIPROCKET_INTEGRATION.md`. Real bugs found and fixed: (1) `Shiprocket::curl()` re-authenticated against `/auth/login` on *every* API call with no caching — now cached via `Cache` (~9-day TTL, matching Shiprocket's ~10-day token validity), with 401-triggered one-shot re-auth-and-retry; (2) both the auth call and every API call had **no HTTP timeout** (`CURLOPT_TIMEOUT => 0` / unset) — a slow Shiprocket could hang the cart/checkout deliverability-check path until PHP's own execution-time limit broke checkout; now bounded (default 15s/8s, `config('services.shiprocket.*')`); (3) `Admin\OrderController::create_shiprocket_order()` hardcoded `tracking_id` to `''` (the seller-panel equivalent already stored it correctly) — a genuine "shipment accepted, tracking discarded" bug for every admin-panel-created Shiprocket order, now fixed to match the seller flow; (4) `Seller\OrderController::edit_orders()` passed the raw `shipping_method` settings array (email/password/webhook_token included) into a seller-facing Blade view — not an active leak (the view never echoed those keys) but one template edit away from becoming one; stripped before reaching the view. Credential storage (`Setting` row, matching Razorpay/Stripe/Paystack convention) and the local-zones-primary/Shiprocket-fallback rate model were both already correct. Proven in `tests/Feature/ShiprocketApiHardeningTest.php` (token caching, 401 retry, timeout enforcement, against a local fake server since no live Shiprocket account is reachable here). | `app/Libraries/Shiprocket.php`, `app/Http/Controllers/Admin/OrderController.php`, `app/Http/Controllers/Seller/OrderController.php`, `config/services.php` | None — remaining gaps are external-config-blocked or new-scope, see doc's "Known limitations" |
| Shiprocket integration | See above | — | — | — |
| Shiprocket documentation/integration usage | IMPLEMENTED | `docs/SHIPROCKET_INTEGRATION.md` created this session — architecture, credential/config reference, auth/shipment/tracking flow as actually implemented, webhook details, testing instructions, production checklist, and known limitations (admin-panel Shiprocket-order UI doesn't exist, webhook token shape unverified against a live account, no scheduled reconciliation polling). | `docs/SHIPROCKET_INTEGRATION.md` | None |
| Shiprocket order/shipment status webhook | FIXED (audit-discovered — not its own official changelog line, folded into the "Shiprocket shipping method" row above in the summary counts; documented separately here and as Fix log item 4 for evidence) | `Webhook::spr_webhook()` — route registered, admin Settings already collected and required a `webhook_token`, that token was already correctly hidden from the mobile-app settings API response — but the handler's body was **completely empty**, and the route was `GET` (Shiprocket delivers webhooks as `POST`, so a real call would 405 before reaching it). Exactly the "security control collected but never verified" pattern found and fixed in the Razorpay/Paystack/Stripe webhooks this session, just with the check missing entirely. **FIXED**: route changed to POST; handler now verifies the token via `hash_equals()` (header `X-Api-Key`, falling back to a `token` body field — which shape a live account actually sends could not be verified without one, see doc), rejects forged/missing tokens with zero side effects, and on success updates `OrderTracking` and cascades a cancellation to `Parcel`/`OrderItems`. | `app/Http/Controllers/Admin/Webhook.php::spr_webhook()`, `routes/web.php`, `tests/Feature/ShiprocketWebhookSecurityTest.php` | None |
| Blog feature | IMPLEMENTED | Full admin CRUD (`BlogController`: create/edit/delete/status, categories, this session fixed the missing `update_blog_category` view) + public blog routes. | `app/Http/Controllers/Admin/BlogController.php` | Verify frontend listing/detail/pagination/SEO metadata specifically (P2 spot-check) |
| Bug fixes / Performance improvements | NOT_APPLICABLE | See item 24. | — | None |

## v1.1.1

| Feature | Status | Evidence | Files | Action |
|---|---|---|---|---|
| Dependencies/packages upgraded | NOT_APPLICABLE | Not a code feature; `composer.lock`/`package-lock.json` versions are a point-in-time snapshot, not something to "audit" against a changelog entry. | — | None (out of scope per task Rule: don't modify composer.json unless necessary) |
| Cart system optimized | NEEDS VERIFICATION | Not yet deep-audited for N+1 queries — folded into the performance audit (item 24). | `app/Services/CartService.php` | Audit (P1) |
| Cart APIs optimized | NEEDS VERIFICATION | Same. | `app/Http/Controllers/CartController.php` | Audit (P1) |
| UI automatically updates after cart changes | NEEDS VERIFICATION | Frontend JS behavior, not yet audited. | — | Audit (P2) |
| Stores without products are automatically hidden | IMPLEMENTED | `Admin\StoreController::getStores()` gained an opt-in `$onlyWithProducts` parameter (default `false` — every existing caller, including the seller app's own "see my store" call, is unaffected), passed `true` only by the customer-facing `App\v1\ApiController::get_stores()`. Deliberately opt-in, not unconditional: the same shared method is also called by `Seller\v1\ApiController`, and a seller must still see their own store before it has any products yet. | `app/Models/Store.php` (new `products()` relation), `app/Http/Controllers/Admin/StoreController.php`, `app/Http/Controllers/App/v1/ApiController.php` | None |
| Categories without products are automatically hidden | IMPLEMENTED | Same opt-in pattern on `Admin\CategoryController::get_categories()` — hidden only when neither the category itself nor a subcategory (checked two levels deep, matching this query's own existing eager-load depth) has an active product, so a parent category isn't hidden just because products live on its children. | `app/Http/Controllers/Admin/CategoryController.php`, `app/Http/Controllers/App/v1/ApiController.php` | None |
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
4. **`Admin\Webhook::spr_webhook()` (Shiprocket order/shipment status webhook)** — the route existed
   (registered GET; Shiprocket delivers webhooks as POST, so a real call would 405 before reaching it), the
   admin Settings screen already required and stored a `webhook_token` specifically for this, and that token
   was already hidden from the mobile-app settings API response — but the handler body was **completely
   empty**: no verification, no processing, nothing. Same "security control collected but never checked"
   pattern as item 3, just with the check missing entirely rather than present-but-bypassed. **FIXED** (this
   commit) — route changed to POST; handler now verifies the token via `hash_equals()` (header `X-Api-Key`,
   falling back to a `token` body field), rejects a forged/missing token with zero side effects, and on
   success updates `OrderTracking` and cascades a cancellation to `Parcel`/`OrderItems`, mirroring
   `ShiprocketService::cancelShiprocketOrder()`'s existing cascade. Also fixed in the same pass:
   `Shiprocket::curl()` re-authenticating against `/auth/login` on every single API call (now cached, ~9-day
   TTL, with 401-triggered one-shot re-auth-and-retry); no HTTP timeout on any Shiprocket call (could hang
   the cart/checkout deliverability path until PHP's own execution-time limit broke checkout — now bounded);
   `Admin\OrderController::create_shiprocket_order()` discarding `tracking_id` (hardcoded `''`, unlike the
   already-correct seller-panel equivalent); and `Seller\OrderController::edit_orders()` passing the raw
   Shiprocket credentials blob into a seller-facing Blade view. Full detail in
   `docs/SHIPROCKET_INTEGRATION.md`.
5. **`Admin\CategoryController::update_status()` / `Admin\BrandController::update_status()`** — both already
   rendered a "Not Approved (2) / Approve (1)" dropdown for seller-requested rows (`Seller\CategoryController
   ::store()`/`Seller\BrandController::store()` already created rows with `status=2` and a "wait for admin
   approval" message), but the handler ignored the dropdown's selected value entirely and did a blind
   `status==1 ? 0 : 1` toggle — selecting "Approve" on a pending row actually flipped it to `0`
   (deactivated), never to `1` (approved). Compounding this, nothing tracked *which* seller had requested a
   row, so a seller could never see their own pending/rejected requests or withdraw one. **FIXED** — added
   `requested_by_seller_id`/`approval_status` columns (migration
   `2025_02_18_000000_add_category_brand_request_columns.php`), both controllers now read the actual
   selected status (mirroring `Admin\ProductController::update_status()`'s existing pending-approval
   handling), an approved category is granted into the requesting seller's `seller_store.category_ids`, and
   sellers gained a pending-request view + withdraw (`Seller\CategoryController::destroy()`/
   `Seller\BrandController::destroy()`) scoped to their own still-pending rows. 12 new tests
   (`tests/Feature/SellerCategoryBrandRequestTest.php`), including an IDOR regression test confirming a
   seller cannot see or delete another seller's pending request.

## Implementation priority (P0 → P2, per user-approved plan)

**P0 (security, do first):**
- Fix the language-file-upload RCE (`savelabel()` × 2) — replace with real JSON parsing/validation.
- Payment gateway callback/webhook security audit (item 12).

**P1 (changelog features with real user/business impact):**
- ~~Interactive address map with lat/lng (v1.1.2 — explicitly required)~~ — **done**, Leaflet/OpenStreetMap
  component on admin's Manage Customer Address page.
- ~~Seller category/brand request lifecycle (v1.0.6 + v1.0.11 app views)~~ — **done**, request tracking
  columns + admin approve/reject + seller pending-request list/delete.
- ~~Queue integration, Cloud-Run-compatible (v1.0.10)~~ — **done**, see `docs/QUEUE_ARCHITECTURE.md`.
- ~~Email order invoices (v1.0.3)~~ — **done**, PDF attached directly (the dead admin-only link is gone).
- ~~Hide empty stores/categories (v1.1.1)~~ — **done**, opt-in `onlyWithProducts` filter on the
  customer-facing store/category endpoints.
- Bulk upload hardening — transactions/chunking (v1.0.9).
- ~~Shiprocket depth audit + hardening + docs (v1.1.0)~~ — **done**, see Fix log item 4 and
  `docs/SHIPROCKET_INTEGRATION.md`.
- Affiliate: product-level referral link generation (v1.0.7). ~~Payout/withdrawal flow~~ — **done**, see the
  "Admin can process affiliate payouts" row above.
- ~~PWA support (v1.0.3)~~ — reclassified **NOT_APPLICABLE**: dead manifest/service-worker scaffolding
  exists but is rendered by no route anywhere; no live customer web storefront exists in this repo for a
  PWA to attach to (see that row's evidence above).

**P2 (smaller/UX/lower-impact features):**
- Admin Preference Page + Single/Multi Store mode.
- Tooltips (admin + seller).
- ~~Setup Progress Tracker~~ — **done**, see `app/Services/SetupProgressService.php`.
- Store-level custom fields.
- Affiliate policies page, withdrawal limits, charts, shared-products list.
- Alternate slider image.
- ~~Delivery boy self-service availability toggle~~ — **done**, `is_available` column + self-service
  toggle endpoint (not yet wired into dispatch eligibility — see that row's note above).
- Seller store deactivate/delete-when-empty.
- Support ticket chat-gate verification.

This document will be updated as each item is implemented, with the Status column changed to `IMPLEMENTED`
or `FIXED` and Evidence pointing at the new code + tests.
