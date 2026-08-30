# Implementation Progress — 32-Phase SaaS Transformation

Tracks status against the 32-phase brief (custom domains, per-merchant payment gateways, unified inventory,
POS repair, subscriptions, full RTL/LTR, etc.). This is a **different numbering** from this repo's own prior
20 internally-numbered phases (`docs/PHASE_1_*` … `docs/PHASE_20_*`) — those are cross-referenced per phase
below where they cover the same ground, not re-done from scratch.

Status values: `COMPLETE`, `IN PROGRESS`, `NOT STARTED`, `PARTIAL (pre-existing)` — the last one marks
something the codebase already had before this brief, verified but not newly built under this effort.

| Phase | Title | Status | Note |
|---|---|---|---|
| 1 | Complete System Discovery | **COMPLETE** | `docs/COMPLETE_SYSTEM_MAP.md`, this file. |
| 2 | Complete Route & Page Inventory | PARTIAL | `docs/PHASE_2_ROUTE_SWEEP_REPORT.md` — automated sweep of all 298 no-param GET routes across 4 panels (real user, real HTTP kernel). Found 75 real 500s, root-caused every one, fixed the 4 confirmed-safe ones (shared pagination bug), categorized the rest by real-world risk. `tests/Feature/RouteSweepTest.php` is now permanent regression coverage. Not done: the 107 parameter-required routes (need per-route fixtures), and everything past "does it render" (forms/AJAX/search/filters/RTL) per this brief's own fuller checklist. |
| 3 | Security & Seller Isolation | PARTIAL (pre-existing) | Extensively done in this repo's own Phase 2 (`PHASE_2_RBAC_AUDIT.md`, `PHASE_2_IDOR_AUDIT.md`, `PHASE_2_MULTITENANCY.md`) plus Phase 15 (`PHASE_15_SECURITY_HARDENING.md`) and re-verified today for every new affiliate endpoint. A fresh full re-audit against *this brief's* explicit checklist (file access, exports, webhook replay, etc.) has not been re-run as its own pass. |
| 4 | Multi-Tenant Architecture | **CLOSED** | `docs/PHASE_4_MULTI_TENANT_DECISION.md` — confirmed with the product owner: no new `Merchant` model. `Seller` already is the tenant unit (every resource scoped by `seller_id`/`store_id`, a seller gets exactly one `SellerStore` created once at onboarding, and this session's own Phase 6/12 work was already built directly on `seller_id`). Revisit only if a real one-seller-multiple-stores need appears later. |
| 5 | Custom Merchant Domains | NOT STARTED | Confirmed net-new — zero existing domain infrastructure (system map §8). |
| 6 | Merchant-Specific Payment Gateways | **MOSTLY COMPLETE** | `docs/PHASE_6_PAYMENT_GATEWAYS.md` + `docs/PHASE_6B_JORDAN_GULF_GATEWAYS.md` — `seller_payment_gateways` table (encrypted-at-rest credentials), resolver service, seller self-service CRUD panel, fully wired end-to-end (checkout creation + server-side verify before order placement) for Razorpay, HyperPay, PayTabs, and Tap Payments, PLUS an admin platform-wide settings UI for all three Jordan/Gulf gateways (a seller's own override still wins when configured). Not done: Stripe/Paypal/Paystack/Phonepe not wired to the per-seller layer (deprioritized by the product owner), and per-seller webhook routing (no async webhooks built for any gateway in this app — order completion is synchronous via `place_order()`, matching the existing bar). |
| 7 | Order Architecture | PARTIAL (pre-existing) | Single `OrderService::placeOrder()` entry point already serves both storefront and POS; `channel` discriminator already exists (this repo's Phase 3). Multi-merchant-single-cart split (§ of this brief) not verified — no evidence a single checkout currently splits across multiple sellers' sub-orders; needs inspection before assuming it works or is missing. |
| 8 | Single Inventory Source of Truth | **MOSTLY COMPLETE** | `docs/PHASE_9_10_POS_CONCURRENCY_AND_BRANCHES.md` — `ProductService::updateStock()` now wraps its read+write in `DB::transaction()` + `lockForUpdate()`, closing the concurrent-decrement race for all 15+ call sites (online checkout, POS, admin, webhooks, purchase orders, returns) at once; the insufficient-stock guard bug (checked "positive" not "enough") is fixed alongside it. No `online_stock`/`offline_stock` split exists (still true, still a good sign). |
| 9 | POS Repair | **MOSTLY COMPLETE** | `docs/PHASE_9_POS_CART_FIX.md` (cart bugs) + `docs/PHASE_9_10_POS_CONCURRENCY_AND_BRANCHES.md` (concurrency locking, branch-scoped stock validation, full-screen responsive UI — the three items the first doc left open, all three now done). Not done: rejecting the *order itself* on a detected race (today the decrement is safely clamped, not the order refused) — a separate, larger cross-cutting change across all 15+ stock call sites' error contracts. |
| 10 | Branch Inventory | **MOSTLY COMPLETE** | `docs/PHASE_9_10_POS_CONCURRENCY_AND_BRANCHES.md` — `stock_items` (real per-branch running totals, existing since this repo's own Phase 5) is now actually validated against at POS sale time via `InventoryService::validateBranchStock()`, not just recorded. Not done: combo products aren't branch-tracked, and the storefront/API checkout path has no branch concept (POS-only in this app's model). |
| 11 | Platform Commission & Monetization | **MOSTLY COMPLETE** | `docs/PHASE_11_SUBSCRIPTIONS.md` — `subscription_plans` table + admin CRUD + seller assignment, seeded with 3 placeholder tiers (Basic/Pro/Premium) the admin edits directly. `max_products` now enforced in `Seller\ProductController::store()`, `commission_rate` now a vendor-scope fallback in `AffiliateService::resolveCommissionRule()` (an explicit admin `CommissionRule` still wins), and a seller-facing read-only "My Subscription" page. Not done: real billing/payment collection — assignment is still a manual admin action, nothing charges a seller or auto-expires/downgrades them. |
| 12 | Merchant-Controlled Affiliate System | **MOSTLY COMPLETE** | Built today: per-product opt-in + seller-chosen rate, public/private catalog, join-request approval, auto-listed ready-link catalog for affiliates. Gap: no per-affiliate allowlist on an otherwise-public store (system map §10). |
| 13 | Merchant-Controlled Delivery Staff | PARTIAL (pre-existing) | Delivery boys already scoped to their seller per this repo's Phase 4/8; not re-verified line-by-line this pass. |
| 14 | Accounting | PARTIAL (pre-existing) | Double-entry infrastructure exists (this repo's Phase 9); whether every money event posts a balanced journal entry (POS sales, affiliate commissions, withdrawals) is not re-verified here. |
| 15 | Currency | **COMPLETE** | `TECHNICAL_DEBT.md`'s currency row — `formatePriceDecimal()` now resolves the correct decimal places per currency (a static ISO 4217 minor-unit table, `CurrencyService::decimalPlacesFor()`) instead of hardcoding 2. Re-verified while fixing: `formateCurrency()` itself was never actually part of the bug — it doesn't touch precision, just prepends/appends the symbol. |
| 16 | Multilingual — Arabic RTL + English LTR | PARTIAL (pre-existing) | Real, working infrastructure already exists and is more complete than the brief assumed: bulk language upload/export controllers for every panel, `labels()` helper with safe fallback, 6 locales already present. **Not verified:** actual RTL correctness across every panel/component, whether `dir` is applied globally, DB-level translation coverage beyond product name/short_description (system map §12). |
| 17 | CRM | PARTIAL (pre-existing) | Built in this repo's Phase 11, seller-isolated per its own docs; not re-verified this pass. |
| 18 | Analytics | PARTIAL (pre-existing) | This repo's Phase 12; not re-verified this pass. |
| 19 | AI | PARTIAL (pre-existing) | This repo's Phase 14 (`PHASE_14_AI_ANALYTICS_LAYER.md`); confirmed analytical-only by design per that doc's own framing, matching this brief's "AI must not become source of truth" rule. |
| 20 | Performance | PARTIAL (pre-existing) | This repo's Phase 16/18/19 (admin-home query profiling, N+1 fixes); not re-run as a fresh full audit. |
| 21 | API | NOT STARTED | Route counts verified (98 mobile-facing routes); the three monolithic `v1\ApiController` classes are known debt (`TECHNICAL_DEBT.md`); full contract/pagination/versioning audit not done. |
| 22 | Cloud Run / Infrastructure | PARTIAL (pre-existing) | Live and working — verified repeatedly this session (real deploys, real Cloud SQL, real GCS-backed media via `storage_types`). `docs/CLOUD_RUN_DEPLOYMENT.md` exists; `cloudbuild.yaml`'s region/service-name substitutions were stale and fixed today. |
| 23 | Storage | PARTIAL (pre-existing) | Spatie MediaLibrary in active use, S3-interop GCS confirmed working today (rotated live credentials, confirmed real upload). |
| 24 | Queues & Async | NOT STARTED (verification) | `QUEUE_CONNECTION=sync` in the deployed config — no real async queue is running today regardless of what `docs/QUEUE_ARCHITECTURE.md` designs; needs its own check of whether that doc was ever actually deployed. |
| 25 | Full UI/UX Regression | NOT STARTED | Same scope as Phase 2 above, plus localization/RTL — large, not attempted this pass. |
| 26 | Responsive Testing | NOT STARTED | Not attempted this pass. |
| 27 | Automated Testing | PARTIAL (pre-existing) | 119 test files exist and pass (570 at last full run today); coverage of this brief's specific matrices (POS concurrency, multi-merchant split orders, RTL) is not verified as existing. |
| 28 | CI/CD | PARTIAL (pre-existing) | `cloudbuild.yaml` exists (fixed today); whether a full GitHub → test → build → deploy pipeline is wired as a trigger (vs. manual `gcloud run deploy`, which is what's actually been used this whole session) is not confirmed. |
| 29 | Final Security Hardening | NOT STARTED | Depends on Phases 3-28 landing first. |
| 30 | Final Database Audit | PARTIAL (pre-existing) | The two specific "known issues" this brief names are already documented in `TECHNICAL_DEBT.md` (Seller `$fillable` mismatch, `CashCollectionController::list()` variable-variable typo) — neither has been fixed yet. |
| 31 | Single Store vs. Multi-Vendor | NOT STARTED | Not inspected this pass. |
| 32 | Final Production Readiness | NOT STARTED | Depends on everything above. |

## What this means practically

Phases 3, 7, 11-14, 16-20, 22-23, 27-28, 30 are **not blank** — real prior work exists and is cited per row
above; they need targeted verification/completion passes, not from-scratch builds. Phases 4-6, 8-10, 15, 21,
24-26, 29, 31-32 are either confirmed net-new (5, 6, 8-10, 15, 24) or large audits not yet attempted (2, 21,
25-26, 29, 31-32) — these are the ones with real multi-session scope.

## Next-step decision needed before Phase 2

Several later phases require product/business decisions this document cannot make on its own:
- **Phase 5 (domains):** subdomain-only (`merchant.valuemarket.com`) vs. full custom-domain support (real
  DNS/SSL infrastructure work either way, but custom domains are a materially bigger project).
- **Phase 6 (payment gateways):** which gateways to support per-merchant, and whether existing platform-wide
  gateway config is kept as a fallback/default.
- **Phase 11 (monetization):** commission-only vs. subscription vs. both, and actual pricing tiers.
- **Phase 9/10 (POS):** confirm the 4 known bugs are still the current priority before investing in the
  full-screen POS UI rebuild also asked for in this phase.

Recommended: proceed to Phase 2 (route/page inventory) next, since it's pure verification work with no
business decisions blocking it — flagging the above for your input in parallel rather than blocking on them.
