# Shiprocket Integration

**Audit date:** 2026-08-29 (part of the v1.1.0 changelog audit pass — see `docs/CHANGELOG_FEATURE_AUDIT.md`).

This document describes the Shiprocket shipping integration **as it actually exists in this codebase** after
this session's audit and fixes — not an idealized version of it. Where something is genuinely missing or
needs a live Shiprocket account to finish/verify, that is stated plainly rather than implied to be done.

---

## 1. Architecture overview

Two layers:

| Layer | File | Responsibility |
|---|---|---|
| HTTP client | `app/Libraries/Shiprocket.php` | Raw Shiprocket API calls (auth, create order, track, cancel, AWB, label, invoice, pickup, serviceability). Owns credentials, the bearer token cache, and curl-level error/timeout handling. |
| Business logic | `app/Services/ShiprocketService.php` | Wraps the client with this app's own concerns: reading/writing `OrderTracking` rows, mapping Shiprocket's status codes, cascading a Shiprocket cancellation to the local `Parcel`/`OrderItems` records. |

Both are called directly from several controllers (`Admin\OrderController`, `Seller\OrderController`,
`Admin\PickupLocationController`, `App\v1\ApiController`, `Seller\v1\ApiController`) and from two services
that run during cart/checkout (`CartService`, `DeliveryService`) to check deliverability and rates.

Tracking data lives in a dedicated `order_trackings` table (`App\Models\OrderTracking`), **not** bolted onto
the `orders`/`parcels` tables — one row per Shiprocket shipment, holding `shiprocket_order_id`, `shipment_id`,
`awb_code`, `tracking_id` (the composite order id we sent Shiprocket, echoed back as `channel_order_id`),
`parcel_id`, pickup/label/invoice URLs, and status fields.

## 2. Credential storage

Shiprocket authenticates with **email + password** (not an API key) against `/auth/login`. Credentials are
stored the same way this app already stores every other third-party gateway credential (Razorpay/Stripe/
Paystack): as a JSON blob in the `settings` table under `variable = 'shipping_method'`, edited via
**Admin → Settings → Shipping Settings** (`SettingController::shippingSettings()` /
`storeShippingSettings()`, `resources/views/admin/pages/forms/shipping_settings.blade.php`).

They were **never hardcoded** — this was already correct before this audit. The blob also holds
`local_shipping_method` / `shiprocket_shipping_method` (which delivery model(s) are active — see §6),
`webhook_token` (see §5), and the standard-shipping free-delivery threshold.

**Found and fixed this session:** `Seller\OrderController::edit_orders()` passed the *entire* raw
`shipping_method` settings array (email, password, webhook token included) into the seller-facing
`edit_orders` Blade view via `compact()`. The view happens not to echo any of those keys today, so this was
not an active leak, but it was one template edit away from becoming one — a seller viewing any order's edit
page could have had the platform's Shiprocket account password rendered straight into their HTML. Fixed by
stripping `email`/`password`/`webhook_token` before the array reaches the view, matching the pattern
`App\v1\ApiController::get_settings()` already used for the mobile-app settings endpoint.

## 3. Auth / token handling

**Before this session:** `Shiprocket::curl()` called `generate_token()` — a full `/auth/login` round trip —
on **every single API call**, with no caching. Shiprocket tokens are valid ~10 days; a cart page with three
sellers across two pickup locations could fire a dozen `/auth/login` calls just to compute delivery charges.
Both `generate_token()` and `curl()` also had **no timeout set** (`CURLOPT_TIMEOUT => 0` = unlimited, or not
set at all), meaning a slow/unresponsive Shiprocket could hang the request until PHP's own
`max_execution_time` killed it — on the cart/checkout deliverability path, that could break checkout entirely
for every customer, not just degrade Shiprocket-specific features.

**Fixed this session:**

- The token is cached via Laravel's `Cache` facade, keyed `shiprocket_auth_token_{md5(email)}` (bound to the
  configured account so a credential change can never reuse a stale token), for
  `config('services.shiprocket.token_ttl_minutes')` (default 9 days — safely inside Shiprocket's ~10-day
  validity window).
- A `401`/`403` response clears the cached token and retries **exactly once** with a freshly issued one,
  so an expired/revoked token surfaces as a successful retry rather than a spurious failure.
- Both the auth call and every subsequent API call now set `CURLOPT_TIMEOUT` /
  `CURLOPT_CONNECTTIMEOUT` (defaults 15s / 8s, configurable — see §7).
- A curl-level failure (network error, timeout) or a non-JSON response is caught and returned as a
  consistent `['error' => true, 'status' => false, 'message' => ...]` array — callers throughout this
  codebase already used `isset($res['...'])` guards, so this is a strict improvement (a predictable shape
  instead of a bare `false`/`null` that happened to be tolerated).
- The `/auth/login` request body is now built with `json_encode()` instead of manual string concatenation
  (the old code would have produced malformed JSON if the password ever contained a `"`).
- The token/credentials are **never logged**. Every `Log::` call added in this hardening pass logs only the
  transport error message and the request path — never headers (which carry the bearer token) or the
  request body (which carries customer PII on shipment-creation calls).

Proven in `tests/Feature/ShiprocketApiHardeningTest.php` against a small local fake Shiprocket server
(`tests/Fixtures/shiprocket_fake_server.php`), since a real Shiprocket account cannot be reached from this
environment: three calls in a row authenticate once, not three times; a stale cached token is rejected once
and silently refreshed; a slow response is aborted at the configured timeout instead of hanging.

## 4. Shipment creation

Triggered from **Admin → Orders → [order] → Create Shiprocket Order** or the seller-panel equivalent
(`Admin\OrderController::create_shiprocket_order()`, `Seller\OrderController::create_shiprocket_order()`,
and the mobile-app equivalents in `Seller\v1\ApiController`). The payload sent to
`POST orders/create/adhoc` is built from real data, not placeholders:

- Billing name/email/phone from `User`/`Order`, address/pincode/city/state/country from `Address`+`City`.
- Line items (`name`, `sku`, `units`, `selling_price`, `discount`, `tax`) from the actual `OrderItems` rows
  selected for this parcel.
- `payment_method`: `COD` vs `Prepaid`, derived from the order's real payment method.
- `length`/`breadth`/`height`/`weight` from the admin/seller-entered parcel dimensions (there's no per-
  product-variant dimension field in this schema to pull from automatically — dimensions are entered at
  parcel-creation time, weight is summed from variant weights elsewhere in the deliverability-check path).
- `pickup_location`: the `PickupLocation` record's name, which Shiprocket already knows about (see §8).

On success (`status_code == 1`), the response's `order_id` (Shiprocket's own id), `shipment_id`, and
`channel_order_id` (Shiprocket echoing back the composite order id we sent) are stored on a new
`OrderTracking` row — this part already worked correctly before this audit for the **seller** flow.

**Found and fixed this session:** the **admin** flow (`Admin\OrderController::create_shiprocket_order()`)
hardcoded `'tracking_id' => ''` instead of storing `$response['channel_order_id']` the way the seller flow
already did — meaning `tracking_id`-keyed lookups (the on-demand "Update Status" pull, `ParcelService`
searches by `tracking_id`) could never find an order Shiprocket accepted via the admin panel. Fixed to match
the seller flow. `parcel_id` is now also stored on the tracking row when the caller provides it — see
"Known limitations" below for why the admin panel doesn't send one today.

## 5. Tracking / status sync

Two independent mechanisms exist:

**a) On-demand pull** (pre-existing, unchanged): an admin/seller clicks "Update Status" on an order, which
calls `ShiprocketService::updateShiprocketOrderStatus($tracking_id)` → Shiprocket's `courier/track` endpoint
→ maps the numeric status code via `config('eshop_pro.shiprocket_status_codes')` → updates the
`OrderTracking` row and cascades a `cancelled` status to the `Parcel`/`OrderItems` rows if applicable.
Routes: `POST admin/orders/get_order_tracking` (admin UI), `POST seller/orders/update_shiprocket_order_status`
(seller UI), `PUT seller_api/update_shiprocket_order_status` (mobile app).

**b) Webhook (push)** — **this was the single most significant gap found in this audit.** The route
(`GET admin/webhook/spr_webhook`, `Webhook::spr_webhook()`) and the admin Settings field for it
(`webhook_token`, required whenever `shiprocket_shipping_method` is enabled) already existed — but the
handler's body was **completely empty**, and the route was registered as `GET` (Shiprocket delivers webhooks
as `POST`, so a real call would have hit Laravel routing as a 405 before ever reaching the handler anyway).
The token was being collected from the admin, marked required, even correctly hidden from the mobile-app
settings API response — but nothing ever checked an incoming request against it, because there was no
handler logic to check anything with. This is the exact "security control collected but never verified"
pattern found and fixed in the Razorpay/Paystack/Stripe webhooks earlier this session, just with the
verification code missing entirely rather than present-but-bypassed.

**Fixed this session:**

- Route changed to `POST admin/webhook/spr_webhook` (`admin.spr_webhook`).
- `Webhook::spr_webhook()` now verifies the incoming request against the configured `webhook_token` using a
  timing-safe `hash_equals()` comparison (same discipline as the other three gateway webhooks), checking the
  `X-Api-Key` header first and falling back to a `token` field in the JSON body. **Which of these two shapes
  a real Shiprocket account actually sends can only be confirmed against a live Shiprocket panel** — this
  sandbox has no live account to test against. Both are checked so either matches; if your live account uses
  a different header name, adjust `Webhook::spr_webhook()` accordingly (see "Production setup" below).
- On successful verification, the webhook payload's `order_id` (Shiprocket's id, matched against
  `OrderTracking.shiprocket_order_id`) or `awb` (fallback match) locates the local tracking row, updates its
  `awb_code`/`others` (raw status string), and — if the status indicates cancellation — sets `is_canceled`
  and cascades to `Parcel`/`OrderItems`, mirroring `ShiprocketService::cancelShiprocketOrder()`'s existing
  cascade logic.
- A webhook for a shipment with no local match, or a malformed payload, is logged and acknowledged (`200`)
  rather than erroring — so Shiprocket doesn't retry-storm a webhook this app has no use for.
- Every branch is wrapped so a webhook processing error can never surface as an uncaught exception.

Proven in `tests/Feature/ShiprocketWebhookSecurityTest.php`: a request with no token, or the wrong token, is
rejected (`400`) and makes zero changes; a correctly-authenticated request updates tracking and cascades a
cancellation; an unmatched order id is a safe no-op.

## 6. Delivery-charge / rate model

This app has **two independent delivery-pricing systems**, not one:

1. **Local zone-based delivery** (`local_shipping_method` in `shipping_method` settings) — the
   deliverability-zones system audited earlier this session (`Zone`, `deliverable_zones` on products,
   city/zipcode-wise seller deliverability). This is the primary, default model and does **not** call
   Shiprocket at all.
2. **Shiprocket "standard shipping"** (`shiprocket_shipping_method`) — used as a fallback specifically for
   products/carts the local zone system can't serve (`DeliveryService::checkProductDeliverable()` /
   `checkCartProductsDeliverable()`: only reached `if (!$tmpRow['is_deliverable'] ...)`). When reached, this
   **does** call Shiprocket's live `courier/serviceability` endpoint for a real rate and ETA — it is not a
   fixed/hardcoded number. `ShiprocketService::checkParcelsDeliverability()` (used by `CartService`,
   `CartController`, `App\v1\ApiController`) does the same for both COD and prepaid rates during cart/
   checkout.

Both modes can be enabled together; a product/order is only routed to the Shiprocket rate lookup when local
zone coverage doesn't already serve it. This matches the changelog's own phrasing ("shipping rates if
supported by current architecture") — this audit did not force a rate-shopping redesign onto an app whose
checkout already prices delivery through zones by default.

## 7. Configuration reference

**`shipping_method` Setting** (Admin → Settings → Shipping Settings) — JSON blob, edited via the admin UI,
never via `.env`:

| Key | Meaning |
|---|---|
| `local_shipping_method` | `1`/`0` — enable the zone-based delivery model. |
| `shiprocket_shipping_method` | `1`/`0` — enable the Shiprocket fallback model. |
| `email` | Shiprocket account email (the one you log into shiprocket.co with). |
| `password` | Shiprocket account password. |
| `webhook_token` | A secret string **you choose** — enter the same value here and in the Shiprocket panel's webhook configuration (see below). Required whenever Shiprocket shipping is enabled. |
| `standard_shipping_free_delivery`, `minimum_free_delivery_order_amount` | Free-delivery threshold for the standard-shipping path. |

**`config/services.php` → `shiprocket`** (new this session — operational knobs that don't belong in the
database):

| Key | Env var | Default | Purpose |
|---|---|---|---|
| `base_url` | `SHIPROCKET_BASE_URL` | `https://apiv2.shiprocket.in/v1/external/` | Overridable so tests never hit the real API. Leave unset in production. |
| `timeout` | `SHIPROCKET_TIMEOUT` | `15` (seconds) | Total request timeout. |
| `connect_timeout` | `SHIPROCKET_CONNECT_TIMEOUT` | `8` (seconds) | TCP connect timeout. |
| `token_ttl_minutes` | `SHIPROCKET_TOKEN_TTL_MINUTES` | `12960` (9 days) | How long a fetched bearer token is cached before being re-requested. |

None of these need to be set for production — the defaults are sane. They exist primarily so tests (and, if
ever needed, an unusually slow network path) can override them without touching application code.

## 8. Production setup checklist

1. Create/log into a Shiprocket account at shiprocket.co and add at least one **Pickup Location** matching
   what you'll select in this app's Admin → Pickup Locations screen
   (`Admin\PickupLocationController::store()` already calls Shiprocket's
   `settings/company/addpickup` to register it there directly — no manual duplication needed).
2. In Admin → Settings → Shipping Settings, enable **Shiprocket Shipping Method**, enter the Shiprocket
   account **email** and **password**, and a **webhook token** — any secret string you generate (e.g.
   `openssl rand -hex 32`). This app never generates or displays one for you.
3. In the Shiprocket panel's webhook configuration (Settings → API → Webhook, in Shiprocket's own UI, subject
   to change), point the webhook URL at `https://<your-domain>/admin/webhook/spr_webhook` and enter the
   **same secret string** you set as `webhook_token` above.
4. **Verify which shape Shiprocket sends the token in** (an `X-Api-Key` header vs. a `token` field in the
   JSON body — Shiprocket's own documentation/panel is authoritative, not this document) and confirm
   `Webhook::spr_webhook()` checks the right one for your account. Both are currently checked, but only your
   live account can confirm which (if not both) actually applies.
5. Send a test webhook from the Shiprocket panel and confirm it logs `Shiprocket Webhook | Processed ...` (or
   the appropriate rejection/no-op line) in `storage/logs/laravel.log`.
6. Create a real order end-to-end in a staging environment and confirm: Create Shiprocket Order succeeds,
   `order_trackings.shiprocket_order_id`/`shipment_id`/`tracking_id` are all populated, AWB
   generation/pickup request/label/invoice all work from the admin or seller order screen, and cancelling
   from either this app or the Shiprocket panel is reflected in the other within a few seconds (webhook) or
   after a manual "Update Status" click (pull).
7. Set `SHIPROCKET_TIMEOUT`/`SHIPROCKET_CONNECT_TIMEOUT` env vars only if your production network path to
   Shiprocket genuinely needs different values than the defaults above — most deployments won't.

## 9. Testing this integration

No live Shiprocket sandbox account is available in this environment, so it could not be end-to-end verified
against the real API (Rule: say so plainly rather than pretend). What **is** verified, automatically, on
every test run:

- `tests/Feature/ShiprocketWebhookSecurityTest.php` — webhook token verification (forged/missing token
  rejected with zero side effects; correct token via header or body updates tracking; cancellation cascades
  to parcel/order items; unmatched shipment is a safe no-op; the route no longer accepts `GET`).
- `tests/Feature/ShiprocketApiHardeningTest.php` — token caching, 401-triggered re-auth-and-retry, and
  timeout enforcement, run against a local fake Shiprocket server
  (`tests/Fixtures/shiprocket_fake_server.php`) since the real API isn't reachable here.

To verify against a **real** Shiprocket sandbox/test account once you have one: follow the production setup
checklist above against Shiprocket's test credentials, and watch `storage/logs/laravel.log` for the
`Shiprocket auth request failed` / `Shiprocket API request failed` / `Shiprocket Webhook | ...` lines this
hardening pass added — they're the fastest way to see exactly what Shiprocket is rejecting and why.

## 10. Known limitations (not implemented / blocked on external config)

- **No automated polling job.** Status sync is either the webhook (push, now working) or a manual
  "Update Status" click (pull) — there is no scheduled `Kernel::schedule()` entry polling Shiprocket
  periodically. If the webhook were to fail to arrive (network blip, Shiprocket-side outage), a shipment
  could sit stale until someone clicks "Update Status" manually. Adding a scheduled reconciliation job is a
  reasonable follow-up but is a **new capability**, not a bug fix, so it was left out of this pass's scope.
- **Admin panel's "Create Shiprocket Order" has no wired UI screen.** The route, controller method, and
  Shiprocket payload-building logic are all real and correct (see §4) — but no admin Blade view actually
  submits to it (only the seller panel's order-edit page has the corresponding form/modal). It's reachable
  today only by calling the endpoint directly. Because of this, the admin flow also has no source for
  `parcel_id` (the seller flow's form includes a hidden `parcel_id` field the admin form doesn't have), so an
  admin-panel-created Shiprocket order's `OrderTracking.parcel_id` stays `NULL` even after this session's
  `tracking_id` fix — cancellation/status cascades that key off `parcel_id` won't find it for such orders.
  Building the missing admin UI (mirroring the seller one) would close this; that's frontend work beyond a
  backend correctness/security pass and was left out of scope here.
- **Webhook token shape is unverified against a live account** (§5/§8 item 4) — both plausible shapes
  (`X-Api-Key` header, `token` body field) are checked, but only a real Shiprocket account can confirm which
  one(s) your integration actually needs.
- **`courier/track?order_id=` parameter semantics are unverified.** `ShiprocketService::updateShiprocketOrderStatus()`
  (the pre-existing pull path) passes this app's own `tracking_id` (the composite channel order id) as the
  `order_id` query parameter. Whether Shiprocket's tracking endpoint expects its own numeric order id or the
  channel order id here could not be confirmed without a live account; this pre-existing behavior was left
  unchanged rather than guessed at and possibly broken further. The new webhook (§5b) does not depend on this
  endpoint at all, so it's unaffected either way.
- **No per-product-variant dimension fields** to source shipment length/breadth/height from automatically —
  they're entered manually per parcel at Shiprocket-order-creation time, same as before this audit. Adding
  dimension fields to `product_variants` would be a schema change beyond this pass's scope.
