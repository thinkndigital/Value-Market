# Phase 6 (32-phase SaaS brief) — Merchant-Specific Payment Gateways

Decision from the user (docs/IMPLEMENTATION_PROGRESS.md, "Next-step decision needed"): per-merchant
credentials, each seller adds their own gateway keys, with the existing platform-global config kept as the
fallback for sellers who don't configure their own.

## What existed before this phase

Payments were entirely platform-global: `app/Libraries/{Razorpay,Stripe,Paypal,Paystack,Phonepe}.php` each
read one `payment_method` row from the `settings` table with zero seller context (confirmed by reading every
constructor). No `seller_payment_gateways`-shaped table, migration, or route existed anywhere (confirmed by
grep across the whole app before starting).

## Scope decided for this pass

Fully wiring all 5 gateway classes in one pass would have meant touching several genuinely different code
shapes (Razorpay/Paystack read a plain array from `json_decode(..., true)`; Paypal/Phonepe read a `stdClass`
via `json_decode($settings)` with no `true` flag; Stripe wraps the Stripe PHP SDK and a checkout-session flow)
without being able to honestly claim each one was verified end to end. Per the brief's own "no fake
implementation" rule, this pass builds the full, real, tested infrastructure and wires it completely through
**Razorpay only** - the reference implementation - rather than touching all 5 shallowly. The infrastructure
(migration, model, service, seller CRUD panel) is gateway-agnostic; extending it to Paystack (structurally
identical to Razorpay's constructor) is a small follow-up, and Stripe/Paypal/Phonepe need their own,
separately-verified passes.

## What was built

- **`database/migrations/2025_02_19_000000_create_seller_payment_gateways.php`** - `seller_payment_gateways`
  (`seller_id`, `gateway`, `credentials`, `is_enabled`, unique on `seller_id`+`gateway`). `seller_id` is a
  plain `integer()` with no DB-level FK, matching this codebase's established convention for referencing
  `seller_data.id` (a legacy `int(11)`, not Laravel's default bigint - see
  `2025_02_05_000000_create_branches_and_employees.php`'s docblock).
- **`app/Models/SellerPaymentGateway.php`** - `credentials` uses Laravel's `encrypted:array` cast, so
  gateway secrets are encrypted at rest via `APP_KEY` and only ever exist in plaintext in application
  memory, never in the database or in a log of a raw SQL query.
- **`app/Services/SellerPaymentGatewayService.php`** - the resolver. `credentialsFor($sellerId, $gateway)`
  returns the seller's own credentials only when a row exists and `is_enabled` is true, else `null` (caller
  falls back to the platform default). Two ways to find "the seller" for a given checkout, both read-only,
  both confirmed by reading the real call sites rather than assumed:
  - `resolveSellerIdForOrder($orderId)` - an order belongs to exactly one seller only when every one of its
    `order_items` shares the same `seller_id` (guaranteed when `single_seller_order_system` is on; true by
    coincidence for plenty of single-seller carts even when it's off). A genuine multi-seller order returns
    `null` (platform default) rather than guessing - this pass keeps the existing single-charge-per-order
    model, it doesn't add payment splitting.
  - `resolveSellerIdForStore($storeId)` - `CartController::pre_payment_setup()` creates the razorpay order
    before an `Order` row exists yet, with only `session('store_id')` (a single scalar - the one store the
    shopper is checking out against) in scope; maps it via `seller_store.store_id`.
- **`app/Http/Controllers/Seller/PaymentGatewayController.php`** + **`resources/views/seller/pages/tables/
  payment_gateways.blade.php`** + sidebar entry - seller self-service CRUD, mirroring
  `Seller\AffiliateProgramController`'s established pattern (seller resolved server-side from `Auth::id()`,
  never a client-suppliable id). The index page never echoes a previously saved secret back into the HTML -
  it only tells the view which fields are already configured (for a "saved, re-enter to change" placeholder),
  never their decrypted values. Disabling a gateway does not wipe its saved credentials; only a submission
  that enables it (which requires every field) replaces them.
- **`app/Libraries/Razorpay.php`** - constructor takes an optional `$sellerId`; when given, the seller's own
  enabled credentials take priority over the platform default field-by-field (falls back per-field, not
  all-or-nothing, though in practice a seller row always has both fields set by the CRUD's own validation).
- Wired into both real, order/store-aware entry points:
  - `App\v1\ApiController::razorpay_create_order()` - resolves the seller from the real `order_id`.
  - `CartController::pre_payment_setup()` - resolves the seller from `session('store_id')` at checkout time.

  Deliberately **not** touched: the wallet-refill branch of `razorpay_create_order()` (no order exists, so
  no seller to resolve - correctly stays platform-only) and `Admin\Webhook.php::razorpay_webhook()` (the
  inbound webhook URL is one fixed platform endpoint; routing it per-seller is a separate, larger project
  this pass doesn't attempt - documented here rather than silently left inconsistent).

## What this deliberately leaves for later

- **Paystack/Stripe/Paypal/Phonepe** aren't wired to `SellerPaymentGatewayService` yet. The infrastructure
  supports adding them (`SellerPaymentGateway::GATEWAYS` and `PaymentGatewayController::FIELDS` are both
  designed to extend by adding an entry, not restructuring), but each constructor's actual settings-access
  shape needs its own read-and-verify pass before claiming it works.
- **Per-seller webhook verification** - today's single-endpoint-per-gateway webhook design can't
  disambiguate which seller a given signed payload belongs to without its own routing scheme (e.g. a
  `?seller=` param the gateway echoes back, verified against that seller's own webhook secret). Not
  attempted here; the existing platform-wide-secret webhook verification (docs/PHASE_1_TRANSACTION_BOUNDARIES.md's
  earlier signature-forgery fix) is untouched and still correct for the platform-default case.
- A **found, pre-existing, unrelated bug**, noted but not fixed in this pass: `Razorpay.php`'s constructor
  has always read a `refund_webhook_secret_key` settings key for `$secret_hash` that `Admin\SettingController`
  never actually writes (it writes `razorpay_webhook_secret_key` instead, which is what
  `Admin\Webhook.php::razorpay_webhook()` correctly reads for signature verification). `$secret_hash` itself
  is never read anywhere in the codebase, so this is dead code, not a live vulnerability - flagged here for
  visibility rather than fixed silently, since fixing an unrelated field name is outside Phase 6's actual ask.

## Tests

`tests/Feature/Phase6/SellerPaymentGatewayTest.php` (11 tests): save/enable, encrypted-at-rest (raw column
never contains the plaintext secret), the index page never echoes a saved secret, IDOR (a seller cannot read
or overwrite another seller's row), disabling preserves credentials, remove, the service's enabled/disabled/
missing-row fallback behavior, store→seller resolution, and the `Razorpay` class actually using seller
credentials when configured and falling back to the platform default otherwise.

`tests/Feature/Phase1/MigrationBaselineTest.php`'s table-count assertion updated 122 → 123 for the new table.

Full suite: **588 passing** (577 before this phase), zero regressions.
