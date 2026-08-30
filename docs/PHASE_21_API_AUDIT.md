# Phase 21 — API audit (32-phase SaaS brief)

First real audit pass over the mobile API surface: `routes/api.php` (customer app, backed by
`App\v1\ApiController` — 7,572 lines, 94 methods), `routes/seller_api.php` (seller app, backed by
`Seller\v1\ApiController` — 4,997 lines, 85 methods), and `routes/delivery_boy_api.php` (delivery-boy app,
backed by `Delivery_boy\v1\ApiController` — 1,458 lines), per `docs/TECHNICAL_DEBT.md`'s framing of these
three monolithic controllers as known debt. Route/contract/pagination/versioning audit was previously
flagged as "not done" in `docs/IMPLEMENTATION_PROGRESS.md`.

## Scope of this pass: GET routes, real HTTP kernel, real fixtures

Same methodology as this session's earlier Phase 2 admin/seller/delivery_boy sweeps: hit every real route
through the real HTTP kernel with real seeded fixtures and (since this API layer uses Sanctum bearer tokens,
not session cookies) a real `$user->createToken(...)->plainTextToken`, not a mock. Covers all 109 GET
routes across the three files:

- `tests/Feature/Phase21/CustomerApiRouteSweepTest.php` — 48 routes (`routes/api.php`)
- `tests/Feature/Phase21/SellerApiRouteSweepTest.php` — 47 routes (`routes/seller_api.php`)
- `tests/Feature/Phase21/DeliveryBoyApiRouteSweepTest.php` — 15 routes (`routes/delivery_boy_api.php`)

**Not attempted in this pass**: POST/PUT/DELETE routes (registration, login, cart, place-order, payment
gateway flows, seller product/order mutation, delivery-boy status updates — roughly the other half of the
API surface). These need constructed request bodies per endpoint rather than a query-string GET, materially
more work per route, and are exactly the kind of thing this session's product owner has asked to work
through incrementally rather than all at once — a natural batch 2 for this phase, not started here.

## Batch 2: customer POST/PUT/DELETE routes (`routes/api.php`)

Same methodology, extended to Sanctum-authenticated mutating requests (bearer token passed per-call, not via
a persistent default header — see `CustomerApiMutationRouteSweepTest.php`'s docblock for why that distinction
matters for the intentionally-unauthenticated routes in the same sweep). Covers all 47 non-GET routes in
`routes/api.php` (`tests/Feature/Phase21/CustomerApiMutationRouteSweepTest.php`), except the 5 real
external-service/webhook routes excluded for the same reason as the GET batch's exclusions (`api/ipn`,
`api/phonepe_app`, `api/razorpay_create_order`, `api/check_shiprocket_serviceability`, `api/test-email`).

Found and fixed 9 real bugs:

| Route / area | Root cause |
|---|---|
| `manage_cart` (shared cart-fetch helper, `CartController.php`) | Leftover duplicate tax-recalculation lines right after the tax total was already computed correctly, silently overwriting it with a second, wrong `array_sum` over an unrelated array. |
| `send_bank_transfer_proof` | Undefined `$uploaded_images` variable used outside the scope it was conditionally set in. |
| `register_user` | Unconditional `UserFcm` insert crashed when no `fcm_id` was supplied (NOT NULL column) — wrapped in `$request->filled('fcm_id')`. |
| `verify_user`, `verify_otp`, `resend_otp` | Three more instances of this session's established "fresh-install crash class": unguarded `$settings['authentication_method']` reads against a `Setting` row that doesn't always have that key. |
| `update_order_item_status` → `ProductRatingController`/`ComboProductRatingController` seller-rating updates | `Seller::where('user_id', ...)->update(['rating' => ...])` — wrong table (rating/no_of_ratings live on the `seller_store` pivot, not `seller_data`) and wrong WHERE column (`seller_id` on `Product`/`ComboProduct` is already `sellers.id`, not `sellers.user_id`). Fixed in both controllers' `delete_rating()`/`set_rating()` methods to use `$seller->stores()->updateExistingPivot(...)`. |
| `manage_cart` | Missing `?? null` guard on `$settings['maximum_item_allowed_in_cart']` — another fresh-install-crash-class instance. |
| Namespace case bug | `use App\Http\Controllers\admin\OrderController;` (lowercase `admin`) silently resolved to nothing usable on case-sensitive filesystems/autoloaders, breaking `update_order_status`. Fixed to `Admin\OrderController`. |
| `place_order` | `$city_id = isset($city_id) && !empty($city_id) ? $city_id[0]->id : ''` — `$city_id` is an Eloquent Collection; `empty()` on an object is always `false`, so an empty city lookup still tried `$city_id[0]->id` and crashed with "Undefined array key 0". The adjacent `$zipcode_id` line just above it already used the correct `!$zipcode_id->isEmpty()` check — mirrored that. |
| `update_order_status` | Three compounding bugs: (1) passed `$request->order_id` (an `orders.id`) to `OrderService::process_refund()` with `$type = 'order_items'`, which looks the id up in `order_items` — wrong table, matching nothing or an unrelated row; every other whole-order caller in the codebase passes `'orders'` for exactly this reason, fixed to match. (2) `process_refund()`'s `order_items` branch read `$active_status[1][0]` with no `isset()` guard, unlike the equivalent check in its own `orders` branch a few hundred lines down — crashes on any item with only one status-history entry; guarded to match. (3) `Order::find(...)->orderItems()->first(...)` returns a single model (or `null`), not a collection — `$data[0]->order_type` was reading Eloquent's `ArrayAccess` for a non-existent `"0"` attribute (always `null`) and crashing; fixed to read `$data->order_type` directly with a `null` guard. |

No routes remain broken in this batch — full sweep is green.

## Result: the GET surface is healthy

Across all 109 GET routes, only 2 were broken — both in `routes/api.php`, both simple dead-route slots
(`BadMethodCallException` — no such method exists at all, not a typo of a working one), neither touching
business logic:

| Route | Root cause |
|---|---|
| `api/test` | No `test()`/health-check method anywhere in `App\v1\ApiController`. Could plausibly be intended as a trivial "is the API up" check, but its original contract is unknown — this session has no mobile-app client source to confirm what it was ever meant to return, so inventing a response would be guessing at a feature, not fixing a confirmed bug. |
| `api/get_phonepe_token` | No such method. The only PhonePe-related method in the controller is `phonepe_app()` (`POST`, inside the authenticated group, already wired and returns real data) — this looks like leftover routing from an earlier PhonePe integration approach superseded by `phonepe_app()`. |

Both documented in each test's `KNOWN_BROKEN_ROUTES`/`SKIP_ROUTES` const with root cause (same discipline as
every other dead-route finding this session), not fixed — no mobile client source is available in this repo
to confirm either is actually called, and no working equivalent exists to redirect either to.

Five further routes were excluded outright rather than deferred — real external-service dependencies unsuited
to a route sweep with local fixtures, same reasoning used for the Shiprocket/PDF-generation routes in Phase
2's seller/admin sweeps: `seller_api/download_invoice`, `download_label`, `download_parcel_invoice` (real
Shiprocket-created parcel + real PDF rendering), `get_shiprocket_order`, `shiprocket_order_tracking` (a live
outbound call to Shiprocket's API), and `api/paypal_transaction_webview`, `paystack_webview`,
`handle_paystack_callback`, `get_paypal_link`, `app_payment_status` (real external payment-gateway sessions).

No other findings — every other GET route across all three controllers rendered a real, valid response on
the first pass against realistic fixtures (seller + store, customer, category, brand, product + variant,
combo product, order + order item, tax, pickup location, attribute, city/zipcode). Given this session's own
earlier Phase 2 discipline of catching real, previously-unknown 500s on nearly every panel swept
(area/sellers/products/orders all had genuine bugs), a clean result here is itself informative: this API
layer's read surface, despite the file-size debt, is in materially better functional shape than the
admin/seller web panels were before Phase 2's fixes.

## Environment note (not a code bug)

Verifying this locally required running two pending migrations (`2025_02_19_000000_create_seller_payment_gateways`,
`2025_02_20_000000_create_subscription_plans`) that this session's local dev DB had never picked up —
confirmed via `php artisan migrate:status`, fixed with `php artisan migrate`. Purely a local-environment gap
(production has run these migrations, per this session's own earlier deploy history), not something these
tests needed to work around.

## What's still open for Phase 21

- **POST/PUT/DELETE routes for `routes/seller_api.php` and `routes/delivery_boy_api.php`** (~50 routes
  combined) — seller product/order/inventory mutation, delivery-boy status and cash-collection updates. Not
  started (batch 2 above covered only the customer-facing 47 in `routes/api.php`).
- **Contract consistency** (response shape, error-code conventions, pagination parameters) across the ~280
  methods in these three controllers — not audited; would need to be done by reading representative methods
  from each controller family (list endpoints, mutation endpoints, payment endpoints) rather than every one.
- **Versioning** — whether "v1" in the namespace implies a real versioning story (a v2 planned/needed) or is
  just historical naming — not investigated.
- **The two dead routes above** — each needs its own product decision (implement, or remove the dead route)
  once someone can confirm whether either is actually called by a shipped mobile client.

## Regression coverage

`tests/Feature/Phase21/{CustomerApiRouteSweepTest,SellerApiRouteSweepTest,DeliveryBoyApiRouteSweepTest}.php`
are permanent regression coverage for the 109 GET routes swept in batch 1.
`tests/Feature/Phase21/CustomerApiMutationRouteSweepTest.php` is permanent regression coverage for the 47
customer POST/PUT/DELETE routes swept in batch 2. Full suite: 653 passing, zero failures, zero regressions.
