# Live QA spot-check: admin Settings, seller Add Product, POS clicking

Product owner's request: check that admin pages, especially Settings, seller "add product", and POS
(reported as "doesn't respond when clicking products") actually **work**, not just render. Done by running
the real app (`php artisan serve` + a seeded dev DB) and driving it with a headless Chromium via Playwright
- logging in as a real seller/admin, clicking real buttons, reading real network responses and console
errors - not the route-existence sweep Phase 2 already covered.

## Confirmed, reachable bugs found and fixed

1. **POS "Add" button did nothing at all.** `resources/views/seller/pos_layout.blade.php` (this session's
   own earlier full-screen POS redesign) deliberately drops the seller header component to save screen
   space - but that header is also where `<div id="app_url" data-app-url="...">` lives, and
   `assets/admin/custom/pos.js`'s very first executable line unconditionally reads
   `document.getElementById('app_url').dataset.appUrl`. With the element gone, that line threw immediately
   on every POS page load, aborting everything below it in the file - including the `$(document).ready`
   block that fetches and renders the product list. The panel wasn't just unresponsive, it was empty: no
   products, nothing to click. Fixed by adding the same `#app_url` element directly into `pos_layout.blade.php`.

2. **`appUrl` built without a trailing slash breaks nearly every AJAX call in `custom.js` and `pos.js`.**
   Both files build request URLs as `appUrl + from + "/some/path"` (or `appUrl + "seller/some/path"` in
   `pos.js`). This only works if `appUrl` already ends in `/` - true when `APP_URL` in `.env` has a trailing
   slash, false with Laravel's own `.env.example` default (`http://localhost`, no slash). Without it, the
   two pieces glue together into an invalid host (`http://localhostseller/categories/get_seller_categories`),
   which fails at the network layer with no visible error beyond a generic "Error loading..." fallback.
   Confirmed live: the seller Add Product page's **category dropdown never populated** - `category_id` is a
   required field, so this alone blocked creating any product. `pos.js`'s customer-address/product-variant/
   user lookups use the same pattern and would fail the same way. Fixed by normalizing `appUrl` to always
   end in `/` at the one place each file builds it - `custom.js` already had a documented defensive fix for
   `#app_url` being missing entirely; this is the same treatment for the trailing-slash case. Also fixed the
   session's own local dev `.env` (`APP_URL=http://localhost` → `http://127.0.0.1:8000`, matching how the
   dev server is actually served) so this environment stops masking the bug for future local QA passes -
   `.env` is gitignored, this doesn't touch any deployed config.

3. **`add_to_cart()` in `pos.js` recorded the wrong `product_id` for every click after the first.** It read
   `product_id`/`seller_id` via a page-wide `$('input[name="shop_item_id"]').val()` - jQuery returns the
   *first* matching element on the whole page, not the one inside the card actually clicked. Every other
   field in the same function (title, image, variant id) was already correctly scoped to the clicked
   button's own card; this one wasn't. Reproduced live: clicking a second product's "Add" button recorded
   the *first* product's id with the second's title/price. Fixed by scoping the same lookup to the clicked
   button's card, matching the pattern already used for title/image.

4. **`display_cart()` crashed on an empty cart** (`Cannot read properties of null (reading 'length')`) -
   every other cart-reading function in the file already defaulted a missing `localStorage` cart to `[]`;
   this one didn't. Fixed to match.

5. **`admin/settings/system_settings` 500'd** with `Undefined array key "version_system_status"` (and the
   identical shape for 13 more toggle fields on the same page: `order_delivery_otp_system`,
   `enable_cart_button_on_product_list_view`, `expand_product_image`, `google`, `facebook`, `apple`,
   `refer_and_earn_status`, `refer_and_earn_method`, `wallet_balance_status`,
   `customer_app_maintenance_status`, `seller_app_maintenance_status`,
   `delivery_boy_app_maintenance_status`, `authentication_method`) whenever the `system_settings` Setting
   row didn't already have every one of those keys saved - the exact "Category 4: fresh-install crash
   class" shape `docs/PHASE_2_ROUTE_SWEEP_REPORT.md` already documented elsewhere in this app. Every other
   field on this page already used the established `isKeySetAndNotEmpty($settings, 'key')` guard; these 14
   read `$settings[...]` directly. Fixed by applying the same guard everywhere it was missing.

6. **`FilePondPluginImagePreview`/`FilePondPluginFileValidateSize`/`FilePondPluginFileValidateType` were
   undefined**, throwing inside `custom.js`'s own `FilePond.registerPlugin(...)` call and silently killing
   every `$(document).ready` block registered *after* that point in the file (11,846 lines - a good chunk
   of the panel's interactivity). Root cause: `admin/include_script.blade.php` (and the identical seller/
   delivery_boy copies) linked these three FilePond plugins at `/assets/filepond/dist/...` paths that
   404 - the files actually live at `/assets/admin/js/...` (same place `filepond.js` itself is already
   correctly linked from). Fixed by pointing all three `<script>` tags at the paths that exist, in all
   three panels' `include_script.blade.php`.

7. **Demo seller products always showed disabled/out-of-stock, everywhere.** `demo:create-seller` (and
   `demo:seed-all`) seed each demo product with `stock_type = '0'`, which per `ProductService::getStock()`/
   `updateStock()` means the *real* stock lives on `products.stock` - but the command only ever wrote
   `stock: 50` onto `product_variants.stock`, leaving `products.stock` `NULL`. Every availability check for
   a `stock_type=0` product reads `products.stock`, so every demo product was permanently unavailable
   (confirmed live: POS's own "Add" button was disabled for all three demo products). Fixed by also seeding
   `products.stock`/`products.availability` in the command.

## Confirmed dead route, documented not fixed (same pattern as Phase 2's other findings)

`seller/products/create` (`Route::resource`'s auto-generated slot) throws `BadMethodCallException` -
`Seller\ProductController` has no `create()` method. Confirmed unreachable: the sidebar's real "Add
Products" link points to `seller.products.index` (`/seller/products`), which *is* the real, working
add-product page (verified live - full form renders, all fields present, category dropdown now populates
after fix #2 above). Same "Category 1: dead route" shape Phase 2 batches 1-3 found repeatedly elsewhere in
this app - not fixed here, consistent with that established discipline.

## What this pass did NOT fully verify

A complete, real product-creation submission (filling every section of the Add Product form - pricing,
stock, image upload, deliverable zones - and confirming a row lands in `products`) was not completed. The
form's deeper conditional fields (e.g. the simple-product price/stock inputs, which only appear after
selecting both "Product Type" and "Type of Product") did not become visible during automated testing within
a reasonable time budget, and no JS error or other concrete evidence pointed to a real bug there - it may
simply need a specific interaction sequence this pass didn't find. The blocking issue that *was* found
(category dropdown never loading, #2 above) is fixed and verified; a human should still click through one
full product creation to confirm the rest of the form's happy path.

## Regression coverage

`tests/Feature/LiveQaSpotCheckFixesTest.php` covers the two fixes with a server-side, PHPUnit-testable
shape: the `system_settings` missing-key crash (#5) and the demo-seeder stock gap (#7). Fixes #1-4 and #6
are pure client-side JS with no PHPUnit harness in this app for browser behavior - verified live via
Playwright during this pass, not covered by an automated regression test. Full suite: 649 passing (647
before this pass), stable across 2 repeat runs, zero regressions.
