# Phase 1 — Architecture Foundation (Tasks F & G)

## Task G — Multi-Tenant Foundation: the canonical tenant/business ownership model

### The question this phase was asked to resolve

Phase 0 left an open question: does `stores` represent the vendor/business tenant, or is a higher-level
Company/Organization entity needed? This required inspecting actual model relationships, not just the
schema — done now, with source in hand.

### The answer, with evidence

**`seller_data` (the `Seller` model) is the real tenant/business-ownership boundary in this codebase today.
`stores` is a separate, orthogonal concept — a marketplace channel/storefront — not a tenant container.**

- `Seller` (table `seller_data`) has exactly one `user_id` — one seller identity per user account. Products
  belong to a seller via `products.seller_id`. Orders, commissions, and stock are all ultimately scoped by
  seller, not by store.
- `stores` models the platform's *own* multi-storefront capability: `stores.is_single_seller_order_system`
  and `is_default_store` are flags for "does this particular storefront run as a single-seller shop or a
  full marketplace" and "which store is the default" — this is about how many *branded storefronts* one
  eShop Plus installation runs, not about isolating one merchant's data from another's.
- `seller_store` is the pivot connecting the two: `Seller::stores()` is `belongsToMany` and
  `Store::sellers()` is `belongsToMany`, each carrying **per-store** attributes on the pivot (commission
  rate, category_ids, deliverable zones, rating). A single seller **can** appear in multiple stores under
  this model (multi-channel selling from one seller identity), each with different terms — confirmed by
  reading both models' relationship definitions, not inferred from column names alone.
- Tenant scoping is currently enforced **ad-hoc, per controller method**: e.g.
  `Seller\ProductController` repeatedly does `$seller_id = Seller::where('user_id', $user_id)->value('id')`
  then `->where('seller_id', $seller_id)` on the query being built. There is no global scope, no policy, no
  middleware doing this centrally — it's manually repeated in every method that needs it (a concrete,
  code-verified version of the IDOR risk Phase 0 flagged generically).

### What this means for later phases

- **Warehouses/branches (Phase 5+) nest under `seller_data`**, not under `stores`. A seller with one
  `seller_data` row can have many branches/warehouses; `stores` stays what it already is (a storefront
  channel), separate from that hierarchy.
- **"Multi-company" in the master prompt's sense doesn't require a new top-level entity** — `seller_data`
  already is the per-business tenant unit. What's missing isn't a Company table; it's *centralized
  enforcement* of the scoping that's currently copy-pasted per controller (Phase 2's job).
- **This is a documented architectural decision, made from source, not a guess.** If a future phase decides
  the platform genuinely needs one seller to represent multiple *legally distinct* companies (as opposed to
  multiple storefronts under one business), that's a new requirement to raise explicitly — the current code
  does not support it and nothing here assumes it will be needed.

## Task F — Architecture Foundation: conventions

### Principle followed

"Introduce clean domain/service boundaries without unnecessarily rewriting the current application... do
not create empty abstractions simply for architecture aesthetics." Every convention introduced below is
real, working code wired into (or explicitly reviewed against) an actual existing code path — not a
stub class sitting unused.

### Policies — introduced and wired

`app/Policies/ProductPolicy.php`, registered in `AuthServiceProvider::$policies`. Encodes the exact
ownership rule `Seller\ProductController::update()` was already checking inline
(`$product_details[0]->seller_id !== $seller_id`) — same business rule, now in one reusable, testable place
instead of copy-pasted per call site. **Wired into that real call site**, replacing the inline check (which
also fixed a latent type-safety issue — the original used strict `!==` comparison between values not
guaranteed to be the same type, which could reject a legitimate owner; the policy casts both sides to `int`
before comparing). `AuthServiceProvider`'s existing `Gate::before()` super-admin bypass (already present,
pre-dating this phase) continues to apply to every policy automatically. Verified by
`tests/Feature/Phase1/ProductPolicyTest.php` — owner allowed, non-owner denied, super-admin bypasses.

This is the template for Phase 2+ to extend: one `Policy` class per tenant-owned model
(`Order`, `Store`, `Coupon`, ...), each encoding the same `seller_data`-based ownership check, replacing the
scattered inline checks Task G's investigation found throughout the Seller controllers.

### FormRequests — built correctly, deliberately **not** force-wired into existing code

`app/Http/Requests/Pos/PlaceOrderRequest.php` is a complete, correct FormRequest matching
`PosController::place_order()`'s existing top-level validation (`data` required, `payment_method` required,
`payment_method_name` required when `payment_method == 'other'`), with a `failedValidation()` override that
preserves this app's existing `{"error": true, "message": "..."}` response contract instead of Laravel's
default 422 `{"message": ..., "errors": {...}}` shape — **every existing endpoint in this app uses that
`{error, message}` shape**, so a FormRequest using Laravel's default failure response here would be a
breaking API change for every client (web admin, seller app), not a refactor.

It was **not** wired into `PosController::place_order()` in this phase. The existing inline checks return
specific, translated (`labels(...)`) messages per field, and the `payment_method_name` failure additionally
returns a `csrfHash`. Replacing that with generic FormRequest messages would be a real UX/localization
regression, not a pure refactor — preserving it faithfully means porting translation keys into the
FormRequest, which is more than a "convention" change and risks a subtle regression in a file this phase
already modified once for the transaction-boundary fix. The class is real, tested-by-inspection, and ready
to wire in once someone ports those message keys — likely a natural fit for Phase 3 (Commerce Core), not
forced in here just to say a FormRequest is "in use."

### Services, Actions, Events, Listeners, Jobs, API Resources — conventions for later phases, not built speculatively

The codebase already has a real `app/Services/` layer (20 services: `OrderService`, `WalletService`,
`ProductService`, etc.) — this is reused, not replaced. What's genuinely missing (`app/Actions`,
`app/Events`, `app/Listeners`, `app/Jobs`, API Resource classes) is left unbuilt in Phase 1, deliberately:

- **Actions**: appropriate once a Service method's "one coherent operation" boundary needs to be reusable
  outside HTTP (e.g. from a queued job or a console command) — `OrderService::placeOrder()` is exactly that
  shape, but extracting it into an Action now, mid-transaction-boundary-fix, would be a second large change
  to the same method in the same phase. Natural candidate for Phase 3.
- **Events/Listeners**: the inline FCM push + email block at the end of `placeOrder()` (deliberately left
  *outside* the new transaction boundary — see `docs/PHASE_1_TRANSACTION_BOUNDARIES.md`) is the textbook
  candidate for an `OrderPlaced` event with `SendOrderPlacedNotification`/`SendOrderPlacedEmail` listeners.
  Not extracted now because doing it safely means verifying every notification call site's exact behavior
  first (recipient FCM lookups, message templating) — real, careful work belonging to Phase 3, not a
  Phase-1 database-foundation task.
- **Jobs**: no queue-worthy long-running operation was identified as in-scope for this phase; the codebase's
  existing `Console/Commands` (`SendCartReminders`, `GenerateSitemap`) already run on a schedule rather than
  a queue, and changing that dispatch model isn't a Phase 1 concern.
- **API Resources**: the three monolithic API controllers (Phase 0 finding) build response arrays inline,
  per-endpoint. Introducing `JsonResource` classes is valuable but means auditing each endpoint's exact
  current response shape first (many are tested against by mobile apps this session never received source
  for) — a Phase 2/13 task once the mobile app source is available to test against, not a safe Phase 1 move.

This isn't a punt — it's the same discipline applied consistently: build what's real and testable now
(Policy, FormRequest, the transaction-boundary pattern itself), name what's deferred and why, rather than
scatter empty scaffolding across `app/Actions`, `app/Events` etc. that nothing calls.

## Bugs found and fixed while establishing this foundation (not the phase's primary goal, but necessary)

Verifying this phase's work required actually booting the application (`php artisan route:list`,
`php artisan migrate` against the real app, running the real test suite) — something no prior audit pass in
this project had done. That surfaced two real, independent bugs, both fixed because they blocked verification
itself, not as scope creep:

1. **`Admin\Webhook.php` imported `App\Http\Controllers\TransactionController`, which does not exist** — the
   real class is `App\Http\Controllers\Admin\TransactionController` (missing namespace segment). This
   controller handles Paystack/PhonePe/Razorpay payment webhooks; any request reaching it would have fatally
   errored with "Target class does not exist" before this fix. One-line fix
   (`app/Http/Controllers/Admin/Webhook.php`), confirmed by the fact that `php artisan route:list` failed
   before the fix and resolved all 1,066 routes cleanly after it.
2. **Ten controller/model files declare a namespace with different casing than their directory** — e.g.
   `Seller/PaymentRequestController.php` declares `namespace App\Http\Controllers\seller;` (lowercase),
   `Admin/FeaturedSectionsController.php` declares `namespace App\Http\Controllers\admin;`, and
   `Models/Promocode.php` declares `class PromoCode` (capital C) in a lowercase-c filename. Composer's
   autoloader flags all of these as PSR-4 non-compliant during `composer install`. **Tested empirically,
   not assumed**: `class_exists()` resolves both the directory-case and the namespace-declared-case version
   of the same class successfully today (PHP's class-name resolution is case-insensitive once the file is
   loaded via PSR-4 fallback), and `php artisan route:list` correctly resolves every affected route. **Not
   currently broken.** It would break under `composer install --classmap-authoritative` (a common production
   deployment optimization that disables PSR-4 fallback and relies solely on the generated classmap, from
   which these 10 classes are excluded due to the case mismatch) — flagging as a latent risk for whoever
   sets up the production build pipeline (Phase 17 / deployment), not fixed here since it's a larger,
   unrelated cleanup (10 files, cosmetic renames) outside this phase's scope and the current default
   deployment path isn't affected.
