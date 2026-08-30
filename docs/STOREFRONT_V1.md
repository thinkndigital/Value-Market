# Customer Storefront v1

Built this session per the product owner's explicit priority (first of four items raised in one message:
storefront, POS re-verification, product-URL-import, admin/seller permission redistribution - "كلهم
بالترتيب", all of them, in that order). Full context/decisions/scope are in the approved plan this was built
from; this doc is the as-built record.

## What exists now

- `GET /` (home), `GET /products` (listing, `search`/`category_id` filters, pagination), `GET
  /products/{slug}` (detail) - public, no login required.
- `GET/POST /login`, `GET/POST /register`, `POST /logout` - session (`web` guard) auth, scoped to
  `Role::CUSTOMER`. Registration mirrors `App\v1\ApiController::register_user()`'s validation shape, then
  auto-logs-in (the API version doesn't, since it hands back a bearer token instead).
- `GET /cart`, `POST /cart/add`, `POST /cart/remove`, `GET/POST /checkout`, `GET /my-account`, `GET
  /my-account/orders` - gated by the new `customer.auth` middleware (`EnsureCustomerAuthenticated`), not the
  existing `auth`/`role` aliases (both hard-redirect every unauthenticated non-admin request to the admin
  login page - see the class docblock).

## Architecture: thin wrappers, no new business logic

Per the plan's explicit intent, none of this reimplements cart/order logic:

- Home/listing/detail call `ProductService::fetchProduct()` and `Admin\CategoryController::get_categories()`
  directly - the same calls `App\v1\ApiController::get_products()`/`get_categories()` already make (Phase 21
  API audit confirmed both are healthy).
- Cart add/remove and checkout rebuild the incoming web request into the shape
  `App\v1\ApiController::manage_cart()`/`remove_from_cart()`/`place_order()` expect, temporarily swap the
  container's bound `request` instance so those methods' internal `request()`/`auth()` helper calls resolve
  correctly, call them directly, then restore the real request. `auth()->check()` inside those methods
  resolves against whichever guard is currently default; these routes stay on the ordinary `web` session
  guard (never touch `auth:sanctum`), so the already-authenticated customer is recognized with no bridging
  needed. See `Customer\CartController::callWithRebuiltRequest()`'s docblock.
- Order history reuses `OrderService::fetchOrders()`.

## Bugs found and fixed while wiring this up

Found via a real Playwright click-through (register → browse → add to cart → checkout), not just unit tests
- matches this session's established "run it, don't just assert it renders" discipline:

- `CartController::manage_cart()` (the pre-existing, unrouted `App\Http\Controllers\CartController` -
  distinct from the mobile API's `App\v1\ApiController`): `address_id`/`is_saved_for_later` request keys
  were read from each other's input names (a straight variable swap), and an unguarded
  `$settings['single_seller_order_system']` read crashed on a fresh install.
- `App\v1\ApiController::manage_cart()`: a **second** instance of the `maximum_item_allowed_in_cart`
  unguarded-key bug Phase 21 batch 2 already fixed one instance of in this same method - this one is in the
  max-cart-items branch, which only executes the first time a given product variant is added (an
  already-in-cart variant skips it), so the batch-2 sweep's fixture - which pre-seeded the cart with the
  exact variant it exercised `manage_cart()` against - never took that branch and missed it.
- Missing `App\Http\Controllers\TransactionController` import in the new `CheckoutController` (should have
  been `Admin\TransactionController`, the class `App\v1\ApiController::place_order()` actually type-hints) -
  caught immediately by the same live click-through, fixed before commit.

## Known gaps (explicitly out of scope for this pass, not oversights)

- **No address-management UI.** Checkout requires an address to already exist on the account; there's no
  "add address" form yet. A customer with zero saved addresses hits a dead end at checkout. Real, worth a
  fast follow-up - not built here because the plan's scope stopped at "My Account: order history + basic
  profile."
- **Combo products aren't sold through the storefront.** `manage_cart`'s `product_type` is hardcoded to
  `'regular'` in the new controllers; combo-product browsing/cart support is a fast-follow, not built here.
- **Only the default theme renders.** The other 5 header/home themes stay selectable in the admin store
  settings form (harmless no-op, unchanged from before) but nothing implements them - matches the plan's
  explicit v1 scope.
- **A pre-existing `DeliveryService` deliverability quirk, found but not fixed here**: with `local_shipping_
  method` unset/off in `shipping_method` settings, `checkCartProductsDeliverable()`'s zipcode/city branch
  never runs and the function's `is_deliverable` default (`false`) stands even when the product/store's own
  `deliverable_type` is `'all'`/`1` ("always allow" per that code's own comment). Reproduced locally: a
  demo product with `deliverable_type = 1` on a store with `product_deliverability_type = 'all'` still got
  "not deliverable on selected address" at checkout, because this dev DB has zero `shipping_method` setting
  and zero `Zipcode` rows seeded. This is pre-existing `DeliveryService` logic already used by the live
  mobile API (`App\v1\ApiController::place_order()`), not new storefront code - flagged for its own pass
  rather than fixed blind under this task's budget, since it needs to be verified against real
  `shipping_method` configuration this session doesn't have local data for.

## Regression coverage

`tests/Feature/Storefront/StorefrontSmokeTest.php` - guest access to home/products/product-detail, the
login gate on cart/checkout/account, register auto-login, login, and an authenticated add-to-cart round
trip (which exercises the `maximum_item_allowed_in_cart` fix above). Full suite: 659 passing (653 before
this build), zero regressions.
