# Phase 11 (32-phase SaaS brief) — Platform Monetization: Subscriptions

The product owner's earlier decision (docs/IMPLEMENTATION_PROGRESS.md's "Next-step decision needed"
section) was subscription **and** commission together, 2-3 tiers (Basic/Pro/Premium) — but the exact tier
names, prices, and per-tier limits were left as a genuine open blocker this doc couldn't resolve on its
own. Resolved now: **seed real placeholder defaults, let the admin control them** — rather than this pass
guessing actual business pricing, which risked either under- or over-charging real sellers on launch.

## What was built

- **`database/migrations/2025_02_20_000000_create_subscription_plans.php`** — creates `subscription_plans`
  (`name`, `slug`, `billing_cycle`, `price`, `commission_rate` nullable override, `max_products` nullable =
  unlimited, `description`, `features` JSON array, `status`, `sort_order`) and adds
  `subscription_plan_id`/`subscription_started_at`/`subscription_expires_at` to `seller_data`. Seeds 3
  placeholder plans (Basic/Pro/Premium) idempotently — same pattern as
  `2025_02_02_000000_seed_default_storage_type.php` (only inserts when the table is empty, so a re-run
  never clobbers an admin's own edits). The seeded prices/limits are explicitly labeled placeholders in
  both the migration's own data (`description` field) and the admin UI itself — not a real pricing
  decision, a starting point for the admin to edit.
- **`app/Models/SubscriptionPlan.php`** — status/billing-cycle constants matching this repo's
  `CommissionRule`-style convention; `features` cast to array.
- **`app/Http/Controllers/Admin/SubscriptionPlanController.php`** — full CRUD (`index`/`list`/`store`/
  `update`/`destroy`) mirroring `Admin\CommissionRuleController`'s exact structure (the closest existing
  analog — a small admin-managed pricing/rule table), plus `assignToSeller()` — the admin picks a plan and
  a seller id, which sets `subscription_plan_id`/`subscription_started_at`/`subscription_expires_at`
  (expiry computed from the plan's billing cycle: +1 month or +1 year from now), or clears all three when
  no plan is given. `destroy()` is blocked (with a clear message) when any seller is currently on that
  plan, so deleting a plan out from under active sellers can't silently orphan their reference.
- **`resources/views/admin/pages/tables/subscription_plans.blade.php`** + sidebar entry (super-admin only,
  next to Commission Rules/Affiliate Links) — add/edit modals, a bootstrapTable list, and a lightweight
  "assign to seller" modal (seller looked up by id, matching this repo's existing `CommissionRule` scope_id
  precedent of a plain numeric input rather than a searchable dropdown — the admin finds the id from the
  existing Sellers list page).
- **`app/Models/Seller.php`** — the three new columns added to `$fillable`, plus a `subscriptionPlan()`
  relation.

## What this deliberately does not do yet

- **No billing/payment collection.** Assigning a plan is a manual admin action (`assignToSeller()`) that
  sets dates — nothing charges the seller's card or wallet, and nothing runs on `subscription_expires_at`
  passing (no cron job downgrades an expired seller). Real recurring billing is its own large project
  (would plug into the per-seller/platform payment gateways this session already built in Phase 6/6B) —
  building it speculatively here, before the admin has even confirmed real pricing, risked wiring a billing
  engine around numbers everyone already expects to change.
- ~~No enforcement of plan limits anywhere else in the app.~~ **Closed in a follow-up pass** (same phase,
  next commit): `max_products` is now enforced in `Seller\ProductController::store()` (only within the
  same seller-ownership-checked scope this method's existing security fix already applies — an admin
  creating a product on a seller's behalf is unaffected), and `commission_rate` now stands in as a
  vendor-scope fallback in `AffiliateService::resolveCommissionRule()` — only when no explicit
  admin-managed `CommissionRule` already exists at vendor scope for that seller (an explicit admin rule
  still wins, matching the precedence an admin would expect). Both skip entirely for a seller with no plan,
  or a plan that leaves the field unset (null = unlimited / use-platform-default) — no behavior change for
  anyone not on a plan with these fields actually set.
- ~~No seller-facing "my subscription" page.~~ **Closed in the same follow-up**: `Seller\
  SubscriptionController` + `seller/pages/tables/my_subscription.blade.php` — read-only (current plan,
  products-used-of-limit, commission rate, expiry, feature list). No "change plan" button — the product
  owner's ask was admin control, not seller self-service upgrades, so the page points sellers to contact
  support instead.
- **No billing/payment collection** is still true and still deliberately out of scope — see above.

## Tests

`tests/Feature/Phase11/SubscriptionPlanTest.php` (9 tests): the 3 defaults are seeded, admin create/update/
delete (including the commission-rate-over-100 rejection mirroring `CommissionRuleRateCapTest`'s own cap,
and the delete-blocked-while-a-seller-is-assigned guard), assign/clear a seller's subscription (expiry
computed correctly), and the `Seller::subscriptionPlan()` relation.

`tests/Feature/Phase11/SubscriptionEnforcementTest.php` (8 tests, follow-up pass): a seller at/below/with-no
plan/with-an-unlimited-plan product limit; the plan commission-rate fallback (used when no explicit vendor
rule exists, correctly outranked when one does, correctly absent for a seller with no plan at all); and the
my-subscription page rendering the seller's current plan.

`tests/Feature/Phase1/MigrationBaselineTest.php`'s table-count assertion updated 123 → 124.

Full suite: **610 passing** (601 before this phase), zero regressions.
