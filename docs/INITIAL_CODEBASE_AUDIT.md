# Initial Codebase Audit — eShop Plus 1.0.6 → Value Market

**Phase:** 0 (Audit)
**Status:** Partial — see "Audit Scope & Evidence" below before reading any conclusion in this document.

## Audit Scope & Evidence (read this first)

The full eShop Plus 1.0.6 source (442MB Laravel admin/backend zip + 9MB Flutter mobile zip, both in the
project's Google Drive folder) could not be pulled into this session — the only transfer path available
(a Drive MCP tool that returns file bytes as inline base64 text) cannot carry a 442MB archive, and no
Google OAuth token is exposed to this sandbox's shell for a direct `curl` download. That request is
recorded in-session; the extracted source has not yet been pushed to this repository.

Instead, five reference files were uploaded directly into this session and are the entire evidence base
for this document:

| File | What it is | Value |
|---|---|---|
| `composer.json` / `composer.lock` | Laravel backend dependency manifest + exact resolved versions | Confirms framework/PHP version, every backend package and its locked version |
| `package.json` / `package-lock.json` | Admin-panel frontend dependency manifest | Confirms admin panel is React + Inertia.js, and the payment/UI libraries wired in |
| `eshop_plus.sql` | Full MySQL/MariaDB schema dump (90 tables, structure + indexes + constraints, no data) | The single most reliable artifact — ground truth for what the data model actually supports today |

**What this audit could NOT verify, because no PHP, JS, or Dart source files were available:**
controllers, models (relationships/casts/scopes), policies, middleware, service/repository classes,
route files, actual REST/API endpoint list, Blade/React component code, queue jobs/listeners, the three
Flutter mobile apps (customer, seller, delivery — inferred to exist from the changelog and package name,
never inspected), test suite contents, `.env`/deployment configuration, and CI/CD setup.

Every claim below is labeled **[DB]** (directly read from the schema dump), **[PKG]** (directly read from
composer/package manifests), or **[INFERRED]** (a reasonable deduction from DB/package evidence, not
confirmed against source). Nothing here is a guess presented as fact — where evidence runs out, the
section says so explicitly. **Phases 2 and onward (RBAC, controllers, APIs, mobile apps) cannot be
executed responsibly until the actual application source is pushed to this repo** — see the open item at
the end of `IMPLEMENTATION_ROADMAP.md`.

---

## 1. What Already Exists

**[PKG]** Laravel 10 (`v10.48.25` locked) on PHP `^8.1`, with an Inertia.js + React 18 admin panel (not a
separate SPA — server-driven routing via Inertia) built with Tailwind, MUI, NextUI and FontAwesome/Feather
icon sets. Vite is the frontend build tool.

**[PKG]** Backend packages present: `laravel/sanctum` (token auth — the natural fit for the mobile apps),
`laravel/socialite` (social login), `spatie/laravel-permission` (RBAC), `spatie/laravel-medialibrary` +
`spatie/laravel-translatable` (i18n on model fields), `spatie/laravel-sitemap`, `livewire/livewire`,
`munafio/chatify` (real-time buyer/seller chat, own DB tables), `pusher/pusher-php-server` (realtime),
`aws/aws-sdk-php` + `league/flysystem-aws-s3-v3` (S3 storage), `google/apiclient` with the
`FirebaseCloudMessaging` service (push notifications), `intervention/image` + `imagine/imagine` (image
processing), `laraveldaily/laravel-invoices` (PDF invoices), `league/csv` (import/export), and **five**
payment gateway SDKs: `stripe/stripe-php`, `srmklive/paypal`, `razorpay/razorpay`, plus JS-side
`flutterwave-react-v3` and `react-paystack` — confirming the platform already targets multiple regional
payment rails (India, Africa, global cards) rather than one processor.

**[DB]** A 90-table schema (full list and detail in `DATABASE_GAP_ANALYSIS.md`) implementing a working
**multi-vendor marketplace**:
- Stores/vendors (`stores`, `seller_store`, `seller_data`, `seller_commissions`)
- Catalog: simple + variant products (`products`, `product_variants`, `attributes`, `attribute_values`,
  `product_attributes`), bundle/combo products as a **parallel, largely duplicated** product model
  (`combo_products` + its own attributes/faqs/ratings/custom-fields tables)
- Orders and fulfillment (`orders`, `order_items`, `order_charges`, `order_trackings` — with a
  Shiprocket courier integration, `order_bank_transfers` for manual bank-transfer proof-of-payment)
- Delivery-boy logistics (`parcels`, `parcel_items`, `delivery_boy_notifications`, `fund_transfers` for
  cash reconciliation — the closest thing to a cash-drawer concept in the current schema)
- Wallet and payment transaction logs (`wallet_transactions`, `transactions`, `payment_requests` for
  seller/delivery-boy withdrawal requests)
- Customer engagement: `favorites`, `product_ratings`/`combo_product_ratings`, `product_faqs`,
  `promo_codes`, `search_history`, `cart_reminders`
- Support: `tickets`/`ticket_messages`/`ticket_types` plus Chatify's own chat tables
- Localization: `languages` (with an `is_rtl` flag), `currencies` (with per-order rate snapshot columns),
  translatable fields stored as JSON (`name`, `title`, `description`, …)
- RBAC: **two parallel systems** — a legacy `users.role_id` + custom `user_permissions` table, and the
  full `spatie/laravel-permission` table set (`roles`, `permissions`, `model_has_roles`,
  `model_has_permissions`, `role_has_permissions`)

**[PKG]** The changelog for 1.0.6 confirms active development: bank-transfer payment, per-store custom
product fields, seller-initiated category/brand requests with admin approval, and return requests routed
directly to sellers instead of admin — all evidence this is a maintained, evolving codebase, not
abandoned.

## 2. What Works (evidenced)

Backed by the schema and dependency set, the following flows have real data-model and package support and
almost certainly function today: vendor onboarding and storefronts; simple + variant product catalog with
attributes; cart and checkout with multiple payment gateways and manual bank transfer; order lifecycle
with per-order-item status (`order_items.status`), delivery-boy assignment and OTP-based delivery
confirmation (`orders.otp`, `order_items.otp`); wallet credit/debit; promo codes; multi-currency order
capture (currency + conversion rate stored per order); ratings/FAQs/favorites; ticket-based support and
live chat; role/permission-gated admin actions via Spatie.

**Not verified** (would require source): whether these flows are bug-free, whether authorization checks
are actually applied consistently at the controller level, or whether the mobile apps consume all of this
correctly.

## 3. What Is Incomplete

- **POS**: only a single `orders.is_pos_order` boolean flag exists. No cashier-shift table, no
  register open/close, no cash-reconciliation-at-till table, no split-payment tracking, no barcode/SKU
  scan log. A POS *screen* may exist in the admin panel (React/Inertia is a plausible stack for it), but
  the data model cannot currently support shift accounting or till reconciliation.
- **Affiliate/referral**: `users.referral_code` and `users.friends_code` exist, but there is no click
  table, no session/conversion tracking, no link-generation table, and no commission ledger beyond the
  flat `seller_commissions` (vendor commission *rate*, not a transaction ledger). This is referral-code
  plumbing, not an affiliate engine.
- **Multi-location inventory**: `cities`/`areas`/`zipcodes`/`zones` model *delivery serviceability*, not
  vendor warehouses. `pickup_locations` is one address per seller for courier pickup, not a warehouse with
  stock. Stock is a single `int` column on `products`/`product_variants` — there is no per-location stock,
  no stock-movement ledger, no reservation/available-vs-on-hand split.
- **Multi-language content**: the JSON-translatable-column pattern is in place, but full language
  coverage (system strings, transactional emails, mobile app UI) cannot be confirmed without source.

## 4. What Should Be Reused

- The **Laravel 10 / Sanctum / Spatie Permission** foundation — modern, well-supported, and directly
  compatible with everything the target platform needs (multi-guard auth, granular RBAC, token auth for
  mobile).
- The **existing marketplace core** (stores, products/variants, orders/order_items, cart, promo codes,
  ratings, wallet) — this is real, working commerce logic that the target platform's Commerce Engine
  should extend, not replace.
- **Translatable/JSON i18n pattern** and the `languages.is_rtl` flag — already the right shape for
  Arabic/English + future languages.
- **Payment gateway integrations already wired** (Stripe, PayPal, Razorpay, Flutterwave, Paystack, manual
  bank transfer) — reuse the adapters, extend with a proper payment-method abstraction if one doesn't
  already exist in source.
- **Chatify** and the **ticketing system** for CRM/support surfaces.
- **Shiprocket courier integration** (`order_trackings`) as one delivery provider option alongside the
  in-house delivery-boy system.

## 5. What Should Be Refactored

- **Dual RBAC** (`users.role_id`/`user_permissions` vs. Spatie tables) — collapse onto Spatie Permission
  alone; the legacy path is tech debt and a likely source of authorization bugs if both are still checked
  in different places.
- **Combo products as a parallel model** — `combo_products` duplicates most of `products`' columns
  instead of composing simple products into bundles. Worth a real evaluation once the source is available:
  either keep it as a deliberate "bundle" entity type sharing the product table, or leave it but document
  why.
- **Money as `double` everywhere** — every monetary column in the schema (`price`, `amount`, `balance`,
  `commission`, `total`, `wallet_transactions.amount`, …) is `double`. This must move to `DECIMAL` with an
  explicit currency column per the master prompt's money-handling rule; see risk section below.
- **MyISAM tables carrying financial/order data** — `orders`, `products`, `wallet_transactions`,
  `return_requests`, `sections`, `settings`, `sliders`, `time_slots`, `notifications`, `favorites`,
  `delivery_boy_notifications` are all `ENGINE=MyISAM`. MyISAM has no transactions and only table-level
  locking. `orders` and `wallet_transactions` on MyISAM is a real correctness risk under concurrent write
  load and must move to InnoDB before any ledger work sits on top of it.
- **Foreign keys**: only **2** `FOREIGN KEY` constraints exist in the entire 90-table schema (both on
  `seller_store`). Every other relationship (`user_id`, `order_id`, `product_id`, `category_id`,
  `store_id`, …) is enforced only in application code, if at all. This needs a deliberate decision per
  table (add FKs vs. keep app-level enforcement with strong test coverage) rather than blanket assumption
  either way.

## 6. What Should Be Replaced / Newly Developed

Everything the master prompt calls for that has **zero schema footprint today** is a new build, not a
refactor:

- Accounting Engine: chart of accounts, journal entries/lines, general ledger, AR/AP, unified ledger —
  **nothing exists**. `wallet_transactions` and `transactions` are flat single-entry logs, not double-entry
  bookkeeping.
- Inventory Engine: warehouses, branches, stock movements, transfers, valuation (FIFO/weighted-average),
  reorder levels — **nothing exists** beyond a single stock integer.
- Procurement: suppliers, purchase orders, GRNs, purchase invoices/returns, supplier payables —
  **nothing exists**.
- POS: shifts, cash drawer, split payments, till reconciliation — **nothing exists** beyond the
  `is_pos_order` flag.
- Partners/Shareholders, Assets, Liabilities — **nothing exists**.
- Employee management with branch/department assignment distinct from the seller/user model —
  **nothing exists**; sellers *are* users today.
- Affiliate/Reseller engine: link generation, click/session tracking, configurable commission rules,
  commission ledger with pending→payable→paid state machine — **nothing exists** beyond a referral code
  string.
- Multi-company/multi-branch organizational structure — **nothing exists**; the platform is
  single-company, multi-vendor.
- CRM (segments, tags, notes, CLV) — **nothing exists** beyond basic user/order history that CRM
  reporting could be built on top of.
- AI BI layer — **nothing exists** (expected at this stage; the master prompt treats it as late-phase).

## 7. Existing Database Relationships

Documented in full in `DATABASE_GAP_ANALYSIS.md`. Headline finding: relationships are almost entirely
**implicit** (naming convention only — `*_id` columns without declared FKs), which is workable but means
referential integrity currently depends entirely on application code correctness that this audit could not
inspect.

## 8. Existing APIs

**Not verified — no route files or controllers were available.** `laravel/sanctum` in composer.json
strongly implies token-based REST APIs consumed by the mobile apps and possibly the React admin panel; the
`client_api_keys` table suggests a keyed API-client model (third-party or per-app API keys) on top of
Sanctum. No endpoint list, versioning scheme, or request/response shape can be confirmed until
`routes/api.php` and the controllers are available.

## 9. Existing Mobile Applications

**Not verified — the mobile source zip could not be pulled into this session.** Evidence for their
existence: the Drive folder contains `eShopPlus_mobileAppCode_v1.0.6.zip` (9MB), and the changelog notes
"Compatible Flutter version - 3.32.6" and references both a seller "app/panel" and delivery-boy flows.
Typical for this class of CodeCanyon marketplace product (and consistent with the `delivery_boy_*` /
`seller_*` schema split) is three Flutter apps — customer, seller/vendor, delivery driver — but this is an
**[INFERRED]** structure, not a confirmed one.

## 10. Technical Risks

1. **MyISAM on financial/order tables** (`orders`, `wallet_transactions`, `return_requests`) — no
   transaction support, table-level locking, higher corruption risk on crash. High priority to fix before
   building the ledger on top.
2. **Money stored as floating point (`double`)** throughout — rounding errors compound across
   commission splits, tax, multi-currency conversion, and refunds. Must migrate to `DECIMAL` with
   currency-aware storage.
3. **Near-total absence of DB-level foreign keys** (2 of 90 tables) — integrity currently rests entirely
   on unverified application code.
4. **No accounting/ledger foundation** — every money-moving feature the master prompt asks for
   (commissions, POS, procurement, partners) needs to post to a ledger that does not exist yet; this is the
   single largest new-build item and should anchor the architecture phase.
5. **Dual RBAC systems** live side by side — until source is inspected, it's unknown which one
   controllers actually enforce, or whether both are checked inconsistently in different places.
6. **Large surface area of already-integrated third-party services** (5+ payment gateways, Shiprocket,
   Firebase, Pusher, AWS S3, Google API client) — each is a dependency and a credential-management
   concern; none of this was assessed for supply-chain risk beyond version pinning.

## 11. Security Risks

Cannot be assessed at the code level (no controllers/middleware/policies available). From the schema
alone: `users.password` is a single `varchar(255)` (consistent with Laravel's default bcrypt/argon hashing
— **not confirmed**, just consistent with it), `activation_code`/`forgotten_password_code` columns suggest
a **custom** auth/password-reset flow alongside Laravel's own `password_reset_tokens` table — worth
checking for a legacy CodeIgniter-era carryover (the `users` table's `activation_selector`,
`forgotten_password_selector`, `remember_selector` naming is a classic CodeIgniter Ion Auth pattern, which
suggests this codebase evolved from an older CodeIgniter app and may carry forward auth code that predates
Laravel best practice). This specific point should be verified first once source is available — it is a
plausible, not confirmed, security-relevant finding.

## 12. Performance Risks

MyISAM's table-level locking on `orders`/`products` will serialize writes under concurrent load — a
correctness and performance risk simultaneously. No caching layer, queue configuration, or index strategy
could be assessed without source/config; `settings` and `sliders` (also MyISAM) being read on most page
loads is a minor amplifier of the same issue. Full performance assessment blocked pending source.

## 13. Next Step

This audit is necessarily partial. The highest-leverage next action is getting the actual Laravel
application source (`app/`, `routes/`, `resources/`, config, tests) and the Flutter mobile app source
pushed into this repository so Phase 0 can be completed with real evidence for APIs, authorization,
mobile apps, and code-level security — see `IMPLEMENTATION_ROADMAP.md`.
