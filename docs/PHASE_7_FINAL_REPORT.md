# Phase 7 Final Report — Affiliate / Reseller Engine

**Status: complete for the scope delivered — see §5 in `PHASE_7_AFFILIATE_ENGINE.md` for what's explicitly
deferred.** That document carries full design/implementation detail; this report is the index and the
numbers.

## Exact numbers

| Metric | Count |
|---|---|
| Commits this phase | 1 |
| New tables | 4 (`affiliate_links`, `link_clicks`, `commission_rules`, `referral_conversions`) |
| New models | 4 |
| New services | 1 (`AffiliateService`) |
| New controllers | 2 (`AffiliateController`, `Admin\CommissionRuleController`) |
| New routes | 6 |
| Existing methods extended (2 integration points) | `OrderService::placeOrder()` (optional `affiliate_code`, sets `channel = CHANNEL_AFFILIATE`), `Admin\OrderController`'s delivered-status branch (approves + pays commissions, same trigger as the existing refer-a-friend bonus) |
| New Phase 7 test files | 4 |
| New Phase 7 tests | 13 |
| Existing test updated (not a regression — expected table-count shift) | 1 (`MigrationBaselineTest`, 104 → 108) |
| Total test suite (Phase 1–7) | 223 passing, 0 failing |
| Total test suite at Phase 7 start | 210 |

## What changed

- **Trackable links + click tracking**: `affiliate_links`/`link_clicks`, a public (no-auth) redirect
  endpoint (`GET /r/{code}`) that logs the click and forwards the visitor with the code attached.
- **Configurable commission rule engine**: `commission_rules` scoped by
  `product > category > vendor > affiliate > platform` (most specific wins), admin-managed via
  `Admin\CommissionRuleController`.
- **Real end-to-end attribution**: a storefront checkout carrying `affiliate_code` is marked
  `channel = Order::CHANNEL_AFFILIATE` (reserved since Phase 3, unused until now) and gets a `pending`
  `referral_conversions` row with the commission pre-computed — proven through the real
  `OrderService::placeOrder()`, not just the service in isolation.
- **Payout on delivery, not on order placement**: mirrors the existing refer-a-friend bonus's exact timing
  and idempotency technique — approved and credited to the affiliate's wallet only once an order is marked
  delivered, and only once per order no matter how many times that status transition is re-processed.

## Documented, not built this phase (with reason)

| Finding | Why not built now | Doc |
|---|---|---|
| Affiliate storefronts | Frontend/UI feature; this phase (backend-only, matching every prior phase) doesn't build new UI | `PHASE_7_AFFILIATE_ENGINE.md` §5 |
| A formal commission ledger | Commissions credit the existing wallet system (same as the pre-existing refer-a-friend bonus already does); a proper AR/AP-style ledger entry belongs to Phase 9 once a chart of accounts exists to post against - building a one-off parallel ledger now would duplicate what Phase 9 is for | `PHASE_7_AFFILIATE_ENGINE.md` §4, §5 |
| Per-seller commission splitting on a multi-vendor cart | One commission rule resolves per link (based on what it promotes), not a breakdown across every seller in a mixed cart | `PHASE_7_AFFILIATE_ENGINE.md` §5 |
| Fraud/self-referral prevention | Not in the roadmap's one-line scope; needs real usage data to design against | `PHASE_7_AFFILIATE_ENGINE.md` §5 |

## Verification performed

- Migration run clean against the real MariaDB instance this repo's test suite uses.
- `php -l` clean on every touched/new PHP file, including the modified `OrderService.php` and
  `Admin\OrderController.php`.
- `php artisan route:list` confirms all 6 new routes registered, no name collisions.
- Full suite run after the change: **223/223 passing**, zero regressions - critically including every
  Phase 3 `OrderChannelTest` and every other test that exercises `OrderService::placeOrder()`, none of which
  needed any change, confirming the `affiliate_code` extension is truly additive (optional parameter,
  defaults preserve prior behavior exactly).
- The riskiest logic (commission scope-precedence resolution, percentage-vs-flat computation, and payout
  idempotency) has direct unit-level test coverage before the controller/integration layer, not after -
  same discipline as Phase 5's inventory math.
- Confirmed via a real `OrderService::placeOrder()` call (not just `AffiliateService` in isolation) that
  `channel`/conversion attribution work end to end through the actual checkout code path.

## What Phase 7 did not do (explicitly, scope boundaries)

Did not build affiliate storefronts (UI). Did not build a formal commission ledger — reused the existing
wallet system, deferring formal ledger entries to Phase 9. Did not build per-seller commission splitting for
multi-vendor carts. Did not build fraud/self-referral prevention. Did not touch the existing
`processReferralBonus()` refer-a-friend feature or the `referral_code`/`friends_code` columns it uses.
