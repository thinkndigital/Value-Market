# Phase 7 — Affiliate / Reseller Engine

`docs/IMPLEMENTATION_ROADMAP.md` (Phase 7) scopes this as net-new: *"link generation, click/conversion
tracking, affiliate storefronts, commission rule engine."* `docs/DATABASE_GAP_ANALYSIS.md` §5 confirmed the
schema has nothing for this — only `users.referral_code`/`friends_code`, which power a completely different,
already-working feature (see §1).

## 1. Not to be confused with the existing refer-a-friend bonus

`app/function_helper.php`'s `processReferralBonus()` is a real, working feature: a customer shares their
`referral_code`, a friend signs up against it via `friends_code`, and the *referring customer* gets a
one-time wallet bonus once the friend's early orders deliver. This phase builds something different and
larger — a **trackable link** any user can generate, pointing at a product/store/category/the whole
platform, with click tracking and a **configurable, scoped commission rate** paid on completed sales, not a
flat signup bonus. Both features are kept fully separate; nothing here touches `processReferralBonus()` or
the `referral_code`/`friends_code` columns.

## 2. Links, clicks, and the redirect

`AffiliateService::createLink()` generates a short unique code for a `(user_id, target_type, target_id)`
tuple. `GET /r/{code}` (`AffiliateController::trackAndRedirect()`, deliberately outside any `auth`
middleware — the visitor clicking it doesn't have an account yet) logs a `link_clicks` row, increments the
link's `clicks_count`, and redirects to the target with `?affiliate_code=` appended. Carrying that code
through the storefront's own checkout flow to the eventual `placeOrder()` call is the storefront's
responsibility, the same way any other query-string-driven referral pattern works — this phase provides the
tracking/attribution machinery, not a new storefront UI.

## 3. Commission rule engine

`commission_rules`: `scope` (`platform` / `vendor` / `affiliate` / `category` / `product`) + `scope_id` +
`rate_type` (`percentage`/`flat`) + `rate_value`. `AffiliateService::resolveCommissionRule()` checks scopes
in order of specificity — `product > category > vendor > affiliate > platform` — and uses the first active
match. A link that specifically promotes a product carries that product (and, when explicitly supplied, its
seller) as its own commission context; a platform-wide link falls through to whatever's configured at
`affiliate` (a rate specific to that one affiliate) or `platform` (the global default) scope. **If nothing is
configured at any level, no conversion is recorded at all** — the sale still completes normally, it's just
not commission-tracked, rather than silently inventing a zero-rate conversion. `Admin\CommissionRuleController`
gives admins CRUD over rules.

## 4. Attribution and payout — mirrors the existing refer-a-friend timing exactly

`OrderService::placeOrder()` accepts an optional `affiliate_code`. When present and valid: the order's
`channel` is set to `Order::CHANNEL_AFFILIATE` (defined back in Phase 3, explicitly reserved and unused until
now — see `PHASE_3_COMMERCE_CORE.md`), and `AffiliateService::recordConversion()` records a **`pending`**
conversion with the commission pre-computed from whatever rule resolves — money doesn't move yet.

`AffiliateService::approveConversionsForOrder()` is called from the exact same trigger point
`processReferralBonus()` already uses — `Admin\OrderController`'s delivered-status branch — approving every
pending conversion for that order and crediting the affiliate's wallet via the existing
`WalletService::updateWalletBalance()`, with an idempotency key (`'affiliate-commission-' . $conversion->id`,
checked against `Transaction.order_id` before crediting) so re-processing the same order status change never
pays twice — the identical technique `processReferralBonus()` already uses for its own bonus.

**Why commission credits the existing wallet system, not a new ledger**: `DATABASE_GAP_ANALYSIS.md`'s gap
table calls this a "`commission_ledger`," but Phase 9 (Accounting + Unified Ledger) is explicitly where this
codebase's formal chart-of-accounts/journal-entry ledger belongs — every other module's money movement is
scoped to post there once it exists. Building a one-off parallel ledger just for affiliate commissions now,
ahead of Phase 9, would be exactly the kind of redundant parallel concept this project's own docs
(`DATABASE_GAP_ANALYSIS.md` §6) warn against. Crediting the wallet (the same mechanism `processReferralBonus()`
already uses for real money) is the correct v1 home; a formal commission ledger entry is Phase 9's job to add
once the chart of accounts exists to post it against.

## 5. What this phase does not do (explicitly, scope boundaries)

- **Affiliate storefronts** — the roadmap phrase names this, but it implies a dedicated
  affiliate-branded storefront view, which is a frontend/UI feature this phase (backend-only, matching every
  prior phase's pattern) doesn't build.
- **A formal commission ledger** — commissions credit the existing wallet (§4); a proper AR/AP-style ledger
  entry is Phase 9's job once the chart of accounts exists.
- **Multi-vendor commission splitting** — a single order can span multiple sellers; this phase resolves one
  commission rule per *link* (based on what the link promotes), not a per-seller breakdown of a mixed cart.
  Sufficient for a link that promotes one product/store/category (the common case), not built out further.
- **Fraud/self-referral prevention** — not asked for by the roadmap's one-line scope; a natural follow-up
  once real usage data exists to design against.

## 6. Tests

`tests/Feature/Phase7/` (4 new files, 13 new tests):

- `AffiliateServiceTest.php` (9) — link creation; click tracking (including an unknown code); commission
  scope precedence (most-specific wins, falls back to platform, returns `null` when nothing's configured);
  conversion recording (percentage math, and the "no rule → no conversion" case); wallet-credit approval with
  proven idempotency (approving the same order twice pays once).
- `OrderPlacementAffiliateAttributionTest.php` (1) — a real `OrderService::placeOrder()` call carrying an
  `affiliate_code` end to end: `channel` set to `CHANNEL_AFFILIATE`, a pending conversion recorded - not
  `AffiliateService` tested in isolation.
- `AffiliateControllerTest.php` (3) — a user can create/list their own links; listing never leaks another
  user's links; the public click-and-redirect endpoint works without authentication.

`tests/Feature/Phase1/MigrationBaselineTest.php`'s table-count assertion updated 104 → 108 for the 4 new
tables (expected consequence of this phase's migration).

Full suite: **223 passing** (210 before this phase), zero regressions.

## 7. Addendum — seller-managed program (2025_02_09_000000 migration)

Everything above shipped with only one way to create a `commission_rules` row: admin, via
`Admin\CommissionRuleController`. A seller had no say in which of their own products earned affiliates a
commission, or at what rate, and an affiliate's only way onto a product was to manually search for it and
generate a link one at a time - there was no discoverable catalog.

This addendum adds the seller-facing half, still on top of the same engine (no parallel commission system):

- **Per-product opt-in, seller-chosen rate** (`Seller\AffiliateProgramController::toggleProduct()`) - upserts
  a `commission_rules` row at `scope=product` for the seller's own product (ownership checked the same way
  every other seller-panel controller in this app checks it: `Seller::where('user_id', Auth::id())`, never a
  client-supplied seller id). Disabling flips `status` to inactive rather than deleting the row, so
  re-enabling doesn't ask for the rate again - the same soft-toggle admin's own commission rules already use.
- **Public vs. private catalog** (`seller_store.affiliate_visibility`, default `public`) - a private store's
  products are invisible to the auto-listed catalog below until the seller approves a specific affiliate's
  request (`store_affiliate_requests`, unique per store+affiliate so a repeat request updates the same row
  instead of spamming new ones).
- **Auto-listed, ready-to-copy catalog** (`AffiliateController::availableProducts()`) - replaces the old
  "search a product, then generate a link" two-step flow for anything already commission-enabled: every
  eligible product (public stores, plus private stores the affiliate is approved for) is listed with a link
  already minted via the new `AffiliateService::getOrCreateProductLink()` (get-or-create, unlike
  `createLink()`, which deliberately allows minting distinct-code links on demand for campaign tracking -
  see `AffiliateProductLinkTest` - so that existing manual flow is untouched, not replaced).
- Affiliate portal dashboard redesigned around this: wallet balance and withdrawal request/history (already
  built in an earlier pass but never wired into the view) are now visible, alongside the new catalog, a
  per-conversion commission history table, and a private-stores "request to join" widget.

**Tests**: `SellerAffiliateProgramTest.php` (6 - ownership/IDOR on both the per-product toggle and the
request-approval endpoint, page render) and `AffiliateAvailableProductsTest.php` (8 - public vs. private
visibility gating, an approved affiliate unlocking a private store, a disabled product dropping out of the
catalog, duplicate-request prevention). `MigrationBaselineTest.php`'s table count updated 121 → 122 for
`store_affiliate_requests`.

Full suite: **563 passing**, zero regressions.

## Master architecture prompt Phase 7: "My Products"

The 81-section "VALUE MARKET — COMPLETE MASTER ARCHITECTURE & RESTRUCTURING PROMPT" (see
`docs/SIDEBAR_ENGINE.md` for the engagement-wide context) reframes this engine's own sidebar around its
section 23-28 spec. Section 24 ("Affiliate My Products") describes a saved/curated list, distinct from
browsing the live catalog - "products the Affiliate has added/saved" via either Copy Link or the
Marketplace.

Auditing this before building anything found it already fully exists at the data layer: both paths already
call `AffiliateService::getOrCreateProductLink()`, which persists exactly one `AffiliateLink` row per
`(user_id, product_id)` the first time a link is generated - `clicks_count`/`conversions_count` already
live on that same row. So "My Products" needed zero new schema or save action, just a dedicated view onto
data this app has tracked since the original Phase 7 pass above:

- `AffiliateController::myProductsPage()`/`myProductsList()` - queries `AffiliateLink` scoped to the logged-in
  user and `target_type = TARGET_PRODUCT`, joins in the product's current name/image/store (a product can
  disappear or be disabled after a link was generated - handled with a "no longer available" fallback rather
  than dropping the row, so the affiliate's tracked history is never silently lost).
- New sidebar entry, positioned above "Marketplace" per the prompt's own section 23 ordering.

**Verification**: `tests/Feature/AffiliateMyProductsTest.php` (5 tests) - generating a link makes it appear;
scoped to the logged-in affiliate only (another affiliate's link doesn't leak in); click/conversion counts
already on the link are reflected as-is; a platform-level link (not a product one) is correctly excluded;
the page itself renders. Live-QA'd via Playwright: confirmed the empty state's copy is correct with no
links yet, then (after minting one directly, since the demo seed data has no commission-enabled products to
click through in the UI) confirmed the real row renders with the product's name/store/image and a working
Copy button. Full suite: 706 passing (the same one pre-existing, date-dependent failure noted throughout
this engagement), zero regressions.

Still open from the master prompt's Phase 7 spec, each substantial enough to be its own follow-up: **Affiliate
Store** (section 26 - a real mini-store/landing page builder: product selection, logo/colors/banner,
preview, publish, a public-facing rendering page - comparable in scope to this engagement's earlier Customer
Storefront work, not something to build ad-hoc alongside a small item like this one) and a dedicated
**Marketing** tools hub (section 28 - QR codes, banners, downloadable social media assets; link/click history
already exists via `AffiliateController::conversionsHistory()`/the dashboard, just not gathered under one
page). Creator (section 29-43) is explicitly its own later phase per the prompt itself, living inside the
Affiliate account rather than as a separate role.
