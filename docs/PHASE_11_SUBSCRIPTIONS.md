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
- **No enforcement of plan limits anywhere else in the app.** `commission_rate` and `max_products` are
  stored and shown, not read by any other controller yet — a seller on a plan with `max_products: 50` can
  still list a 51st product today. Wiring `commission_rate` into `AffiliateService::resolveCommissionRule()`
  and `max_products` into `Seller\ProductController::store()` are natural, contained follow-ups once the
  admin has confirmed these are the real numbers to enforce (enforcing placeholder numbers would be worse
  than not enforcing anything).
- **No seller-facing "my subscription" page.** The ask was specifically admin control; a seller-facing view
  is straightforward to add later (read-only, `Auth::user()->seller->subscriptionPlan`) but wasn't part of
  this pass's scope.

## Tests

`tests/Feature/Phase11/SubscriptionPlanTest.php` (9 tests): the 3 defaults are seeded, admin create/update/
delete (including the commission-rate-over-100 rejection mirroring `CommissionRuleRateCapTest`'s own cap,
and the delete-blocked-while-a-seller-is-assigned guard), assign/clear a seller's subscription (expiry
computed correctly), and the `Seller::subscriptionPlan()` relation.

`tests/Feature/Phase1/MigrationBaselineTest.php`'s table-count assertion updated 123 → 124.

Full suite: **610 passing** (601 before this phase), zero regressions.
