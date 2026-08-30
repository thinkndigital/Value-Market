# Phase 6B (32-phase SaaS brief) — Jordan/Gulf Payment Gateways

Follow-up to docs/PHASE_6_PAYMENT_GATEWAYS.md. Product owner's direction: Razorpay/Paystack/Phonepe cover
India/Africa, which this marketplace doesn't need right now — prioritize gateways real for Jordan and the
Gulf instead. Confirmed with the user which specific providers (never guessed): **HyperPay**, **PayTabs**,
and **Tap Payments**.

## What was built

Same shape as Razorpay in Phase 6 — a real `app/Libraries/*.php` class per gateway (checkout/charge
creation + a server-side verify call using our own credentials, never trusting a client-supplied reference
or a redirect alone), full per-seller override through the existing `SellerPaymentGatewayService`/
`SellerPaymentGateway` infrastructure (`Seller\PaymentGatewayController::FIELDS` and
`SellerPaymentGateway::GATEWAYS` both extended — no new migration needed, `gateway` is already a plain
string column), and wired into both real checkout code paths in `CartController`
(`pre_payment_setup()` creates the checkout/charge; `place_order()` verifies it before creating the Order).

- **`app/Libraries/HyperPay.php`** — Copy&Pay flow. `create_checkout()` POSTs to `/v1/checkouts`; the
  frontend loads HyperPay's own widget with the returned checkout id (card collection happens inside
  HyperPay's PCI-compliant widget, never on this server); `get_payment_status()` GETs the final
  `result.code` server-side. `is_successful()` matches HyperPay's own documented success-code regex —
  manual-review codes are conservatively treated as not-yet-successful, not as a full implementation of
  every review-code branch HyperPay defines.
- **`app/Libraries/PayTabs.php`** — hosted-page flow (`tran_type: sale`) returning a `redirect_url`;
  `verify_payment()` re-checks with `tran_type: verify` server-side. Region maps to PayTabs' actual
  regional endpoints (`JOR`/`SAU`/`ARE`/`EGY`/`OMN`, falling back to `GLOBAL`).
- **`app/Libraries/TapPayments.php`** — hosted-charge flow (`source: src_all`, every payment method Tap
  offers the merchant) returning `transaction.url`; `retrieve_charge()` re-fetches the charge server-side
  with our secret key rather than trusting the webhook/redirect body.

All three constructors take an optional `$sellerId`, matching Razorpay's pattern exactly.

## Wiring into the real checkout flow

- **`CartController::pre_payment_setup()`** — three new `elseif` branches (mirroring the existing
  `razorpay`/`midtrans` branches), each resolving the seller from `session('store_id')` via
  `SellerPaymentGatewayService::resolveSellerIdForStore()` and returning the checkout/redirect data the
  frontend needs.
- **`CartController::place_order()`** — both of its payment-verification code paths (the branch with a
  physical-product delivery check, and the digital-product branch that skips it — this app duplicates this
  logic between the two, an existing shape this pass didn't restructure) now verify hyperpay/paytabs/tap
  the same way razorpay/paystack already do: call `OrderService::verifyPaymentTransaction()` before
  `placeOrder()`, and record the resulting `Transaction` row afterward. New request fields:
  `hyperpay_checkout_id`, `paytabs_tran_ref`, `tap_charge_id` (validated `required` when that
  `payment_method` is chosen, same pattern as `razorpay_payment_id`/`paystack_reference`).
- **`OrderService::verifyPaymentTransaction()`** gained an optional `$sellerId` parameter (default `null`,
  existing callers unaffected) plus three new `switch` cases. **Also fixes a real gap left over from Phase
  6**: the `razorpay` case now threads `$sellerId` through too — before this, a seller's own Razorpay
  *order creation* used their credentials (Phase 6) but *verification/capture* still used the platform
  default, which would have failed outright for any seller who actually configured their own account
  (Razorpay ties a payment to whichever merchant account created the order; a different account's API keys
  can't capture it). Found while wiring the new gateways to the same call sites, fixed in the same pass
  rather than left for later.
- **A found, pre-existing, unrelated bug, fixed in the same touched code**: the digital-product branch of
  `place_order()` had two `elseif ($data['payment_method'] == 'stripe')` arms back to back — the second
  (dead) one read `$request['razorpay_payment_id']`, meaning a razorpay transaction placed through that
  specific branch never got its real transaction id recorded. Corrected to `'razorpay'`, matching the
  physical-product branch's already-correct version directly above it in the same file.

## What this deliberately leaves for later

- **Admin platform-wide settings UI** for these three gateways was not built this pass. Each library's
  constructor already reads a platform-default fallback from the `payment_method` setting (the same key
  naming convention as `razorpay_key_id` etc.), so an admin-level fallback is architecturally supported the
  moment that form exists — but no blade form or `SettingController` validation was added to populate it
  yet. Per the product owner's own Phase 6 decision, per-seller keys are the primary model; sellers
  configure these three fully today via the seller panel (`seller/payment_gateways`) with no platform
  fallback required. Flagged explicitly rather than silently left half-wired.
- **Asynchronous webhook/callback receivers** for these three gateways were not built. Order completion in
  this app's `CartController` checkout flow is already synchronous (`place_order()` verifies then creates
  the Order — the same bar Razorpay/Paystack meet today; Stripe/Phonepe/Paypal in this same flow don't even
  have that, they trust the client-supplied reference outright), so a redundant async safety-net webhook is
  a separate, additive improvement, not a gap in this pass's actual scope.
- **Per-seller webhook routing** — same limitation noted in Phase 6 for Razorpay; not attempted here either
  since no webhook exists yet to route.

## Tests

`tests/Feature/Phase6/JordanGulfGatewaysTest.php` (13 tests): seller CRUD save per gateway (mirroring
`SellerPaymentGatewayTest.php`'s Razorpay coverage), each library using seller credentials when configured
and falling back to the platform default otherwise, each gateway's `is_successful()` logic, one IDOR check
proving the shared CRUD's ownership scoping holds for the new gateways too, and the store→seller resolution
`pre_payment_setup()` depends on for all three.

Full suite: **601 passing** (588 before this phase), zero regressions.
