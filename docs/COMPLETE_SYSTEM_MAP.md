# Complete System Map — Value Market

Phase 1 (System Discovery) of the Multi-Tenant SaaS transformation brief. This is an inventory of what
**actually exists today**, verified against the real repository (file counts, `route:list`, migration
contents), not aspirational. Where this repo's own prior work (20 internally-numbered phases, `docs/PHASE_*`)
already covers ground this brief's Phase N asks for, that's cited directly rather than re-discovered from
scratch — most of "System Discovery" was already done by that work; this document is the first place it's
all pulled into one map against the *new* 32-phase structure.

## 1. Stack (verified)

Laravel 10, PHP, Blade (no Inertia/React in actual use — those packages are present but dead, see
`TECHNICAL_DEBT.md`), MySQL/MariaDB, Spatie MediaLibrary v11, Spatie Permissions, Google Cloud Run
(`value-market-us`, `us-central1`), Cloud SQL, GCS. `QUEUE_CONNECTION=sync` — no real async queue is deployed
despite `docs/QUEUE_ARCHITECTURE.md` existing as a design doc (verify its status before assuming queue
infrastructure is live — Phase 24 territory).

## 2. Scale (real counts)

| | Count |
|---|---|
| Controllers — Admin | 49 |
| Controllers — Seller | 32 |
| Controllers — Delivery Boy | 6 |
| Controllers — Affiliate | 2 (`AffiliateController`, `AffiliateAuthController`) |
| Controllers — API (`v1`) | 3 (`App\v1\ApiController`, `Seller\v1\ApiController`, `Delivery_boy\v1\ApiController` — the "three monolithic API controllers" already flagged in `TECHNICAL_DEBT.md`) |
| Eloquent models | 103 |
| Migrations | 37 files (baseline + additive; the live schema is the source of truth per `PHASE_1_DATABASE_MIGRATION_PLAN.md` — migrations bookkeeping was historically fabricated, now corrected) |
| Services | 38 |
| Policies | 7 |
| Middleware | 24 |
| Feature tests | 119 files |
| Registered routes — admin | 470 |
| Registered routes — seller (web) | 184 |
| Registered routes — seller (api) | 87 |
| Registered routes — delivery_boy | 28 (web) + 26 (api) |
| Registered routes — mobile `api/v1` | 98 |
| Registered routes — affiliate/web | 78 (shared `web.php`) |

## 3. Dashboards / panels (real routes, not assumed)

- **Admin** (`/admin/*`) — everything: products, sellers, orders, customers, delivery, affiliates (read-only +
  commission-rule CRUD), commissions, wallets/withdrawals, purchases/suppliers/GRN, inventory, POS oversight,
  accounting (chart of accounts/journal), assets/liabilities/partners, CRM, reports, analytics, AI layer,
  settings (currency, language, payment, shipping, system).
- **Seller** (`/seller/*`) — own store: products, inventory, purchases/suppliers/GRN, orders, customers/CRM,
  branches, employees, POS + cash shifts, wallet/withdrawals, commissions, **affiliate program** (new today —
  per-product opt-in + rate, public/private catalog, join-request approval), reports, accounting view, store
  settings. **No domain settings, no payment-gateway settings** — both are platform-global today (§7).
- **Delivery Boy** (`/delivery_boy/*`) — assigned orders, delivery status, COD, earnings, profile.
- **Affiliate** (`/affiliate/*`) — just rebuilt today into a real multi-page panel with its own sidebar layout
  (`affiliate/layout.blade.php`, `x-affiliate.side-bar`): dashboard, products (auto-listed, ready links),
  product detail pages, commission history, withdrawals, private-store browsing/requests.
- **API** (`/api/v1/*`) — mobile/customer surface, backed by the three monolithic `v1\ApiController` classes.

## 4. Authentication & authorization

- `users.role_id` + `Role` model (legacy) **plus** Spatie `hasPermissionTo()` (granular) — a documented dual
  RBAC mechanism (`TECHNICAL_DEBT.md`), not unified. `Gate::before()` handles the super-admin bypass on the
  legacy side.
- Ownership pattern used consistently across Seller-panel controllers (including everything built today):
  `Seller::where('user_id', Auth::id())->value('id')`, never a client-supplied id. This is the actual current
  seller-isolation mechanism, verified and IDOR-tested extensively in Phase 2 (`docs/PHASE_2_*`,
  `SECURITY_AUDIT.md`) and again today (`SellerAffiliateProgramTest`, `AffiliateAvailableProductsTest`).
- `AffiliateAuthController` is deliberately role-agnostic (any active user, not scoped to `role_id`).

## 5. Seller / tenant architecture today (relevant to Phase 4)

`Seller` (table `seller_data`) — one row per seller account, `belongsTo` a `User`. `SellerStore` (table
`seller_store`) — the actual store entity a seller operates, `store_id`/`seller_id`/`user_id`. `Branch` and
`Employee` models exist (Phase 4 in this repo's own numbering, `docs/PHASE_4_VENDOR_SYSTEM.md`) for
sub-locations and staff under one seller. **This is a single-tenant-per-seller model already** — every
seller-owned resource in every panel scopes through `seller_id`/`store_id`. What does **not** exist yet:
a `Merchant` concept above `Seller` (today `Seller` IS the merchant unit), custom domain-to-merchant
resolution, or a subscription/billing entity. `Seller::$fillable` includes columns (`store_name`,
`store_url`, `commission`, ...) that don't exist on `seller_data` — dormant, not a live bug (see
`TECHNICAL_DEBT.md`); the real controllers write correct arrays to `seller_store` instead.

## 6. Order → payment → inventory → accounting flow (as it exists)

`OrderService::placeOrder()` is the single entry point for both storefront/API checkout and the Seller
POS path (`Seller\PosController`), setting `orders.channel` (marketplace/pos/affiliate — added in this
repo's Phase 3, `docs/PHASE_3_COMMERCE_CORE.md`) and `is_pos_order`. Payment webhooks (`Admin\Webhook.php`)
verify gateway signatures before acting — never trust client-reported payment status (already a hard rule,
`SECURITY_AUDIT.md`). Inventory deduction for the online path works; **POS has 4 documented, unfixed bugs**
around cart/stock (see §9). Accounting (`chart_of_accounts`/`journal_entries`/`journal_lines`, Phase 9 in
this repo's numbering) exists as double-entry infrastructure — not verified in this pass whether every money
event (POS sale, affiliate commission, withdrawal) actually posts a journal entry; that's real Phase 14 work,
not assumed done here.

## 7. Payments — global today, not per-merchant (relevant to Phase 6)

Payment gateway credentials are configured once, platform-wide, via `Admin\SettingController` /
`SettingService` — confirmed by grep: no per-seller gateway-credential table or model exists anywhere in the
103 models. **Building merchant-owned payment gateways (Phase 6 of this brief) is net-new architecture, not
a repair.** Needs a real design pass (encrypted per-merchant credential storage, a gateway-manager
abstraction, per-merchant webhook routing) before any code — explicitly flagged, not started.

## 8. Custom domains — does not exist (relevant to Phase 5)

Grep across models and controllers for `domain`/`custom_domain` returns nothing. There is no hostname
resolver, no domain-to-store mapping, no SSL/verification flow. **Net-new**, same caveat as §7 — this is a
real infrastructure project (Cloud Run custom domain mapping + a resolver middleware + DNS verification UX),
not a bug fix.

## 9. POS — real, already-documented, unfixed bugs (relevant to Phase 9/10)

From `TECHNICAL_DEBT.md` (confirmed by automated test, not just reading):
1. `CartService::addToCart()` returns `false` for a brand-new item unless `$fromApp=true`; POS never passes
   that flag, so a walk-in customer's very first item add fails.
2. The same method crashes (`Undefined array key 1`) on a cart with more than one distinct new product.
3. POS's order-item creation loop returns after its first iteration — multi-item POS carts silently drop
   every line but one.
4. POS `place_order()` never decrements stock for regular products.

None of these are fixed. POS is not production-safe for a real multi-item sale today. No full-screen/
responsive POS UI audit has been done in this pass (Phase 9/10 of this brief).

## 10. Affiliate system — now merchant-controlled (built today)

Previously admin-only commission rules; as of today a seller can opt individual products in/out with their
own rate, and choose public vs. private (request+approval) catalog visibility. Full detail:
`docs/PHASE_7_AFFILIATE_ENGINE.md` §7 (today's addendum). This already satisfies most of this brief's
Phase 12 ask (merchant-controlled affiliate products/commission/eligibility) — the one gap against the
brief's spec is fixed-amount vs. percentage is supported, but there's no per-affiliate eligibility rule
beyond the public/private store gate (e.g. no allowlist of *specific* affiliates on a public store).

## 11. Delivery staff — already merchant-scoped

`Seller\Delivery_boyController` and related — delivery boys are created/managed within a seller's own scope
already (Phase 4/8 in this repo's numbering). Not re-verified line-by-line in this pass; flagged for Phase
13 confirmation rather than assumed.

## 12. Localization — real infrastructure already exists (relevant to Phase 16)

**Correcting a likely wrong assumption in the brief:** this is not "a place for uploading languages" that
might be broken — `Admin\LanguageController`, `Seller\LanguageController`, `Delivery_boy\LanguageController`,
and `Admin\FrontLanguageController` all exist with working bulk-upload/export routes (confirmed routes:
`seller.translation_bulk_upload`, `export_translation_csv`, etc.). Translation storage is classic Laravel
PHP array files under `resources/lang/{locale}/*.php` (`admin_labels.php`, `front_messages.php`), with
locales already present for `en`, `ar`, `es`, `ja`, `hi`, `hn`. Every label call in the app goes through a
`labels($key, $default)` helper that falls back to the literal default text when a key is missing — this is
why the app never shows a raw translation key, and why partial translation coverage doesn't break pages.
**Not yet verified in this pass:** actual RTL layout correctness across every panel, whether `dir="rtl"` is
applied globally vs. per-page, and whether database content (product names etc.) is translatable — product
`name`/`short_description` are already stored as JSON (`{"en": "..."}`, confirmed today while building the
affiliate product-detail page), so DB-level i18n groundwork exists for those fields specifically. Full
RTL/LTR audit is real Phase 16 work, not done here.

## 13. Subscriptions / platform monetization — does not exist (relevant to Phase 11)

No `Subscription` model, no recurring-billing code found anywhere. Platform revenue today is whatever
commission logic `commission_rules`/`Seller.commission` already encode (admin-set, not merchant-tiered
in the way this brief describes). Net-new.

## 14. Cross-reference — this repo's own prior phase docs

Everything above that says "already exists" is backed by a specific prior-phase doc, not memory:
`PHASE_1_*` (architecture/DB/security baseline), `PHASE_2_*` (RBAC/IDOR/multi-tenancy hardening — the
existing seller-isolation guarantees §5 relies on), `PHASE_3_COMMERCE_CORE.md` (order channel + RMA),
`PHASE_4_VENDOR_SYSTEM.md` (branches/employees), `PHASE_5_INVENTORY_PROCUREMENT.md`, `PHASE_6_POS.md`,
`PHASE_7_AFFILIATE_ENGINE.md`, `PHASE_8_DELIVERY.md`, `PHASE_9_ACCOUNTING_LEDGER.md`,
`PHASE_10_PARTNERS_ASSETS_LIABILITIES.md`, `PHASE_11_CRM.md`, `PHASE_12_ANALYTICS.md`,
`PHASE_14_AI_ANALYTICS_LAYER.md`, `PHASE_15_SECURITY_HARDENING.md`, `PHASE_16_PERFORMANCE_OPTIMIZATION.md`,
`PHASE_17_FULL_QA_PRODUCTION_READINESS.md`, `TECHNICAL_DEBT.md`, `PRODUCTION_READINESS.md`,
`SECURITY_AUDIT.md`. This repo's own phase numbers do **not** map 1:1 to this brief's new 32-phase numbering
— `docs/IMPLEMENTATION_PROGRESS.md` (companion doc) tracks status against the brief's numbering specifically.
