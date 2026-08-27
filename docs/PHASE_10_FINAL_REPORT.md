# Phase 10 Final Report — Partners + Assets + Liabilities

**Status: complete for the scope delivered — see §4 in `PHASE_10_PARTNERS_ASSETS_LIABILITIES.md` for what's
explicitly deferred.** That document carries full design/implementation detail; this report is the index
and the numbers.

## Exact numbers

| Metric | Count |
|---|---|
| Commits this phase | 1 |
| New tables | 5 (`partners`, `partner_transactions`, `assets`, `depreciation_schedules`, `liabilities`) |
| New chart-of-accounts rows added | 4 (`1200` Fixed Assets, `1210` Accumulated Depreciation, `2200` Loans Payable, `5200` Depreciation Expense) |
| New models | 5 |
| New services | 3 (`PartnerService`, `AssetService`, `LiabilityService`) |
| New Phase 10 test files | 3 |
| New Phase 10 tests | 20 |
| Real bug found and fixed during implementation (before ever shipping) | 1 — `LiabilityService::recordPayment()` would have posted a ledger entry against a liability that never had a matching origination entry, driving Accounts Payable to a nonsensical negative balance |
| Existing test updated (not a regression — expected table-count shift) | 1 (`MigrationBaselineTest`, 112 → 117) |
| Total test suite (Phase 1–10) | 265 passing, 0 failing |
| Total test suite at Phase 10 start | 245 |

## What changed

- **Partner capital accounts**: contribution/withdrawal post automatically to the Phase 9 ledger (clear,
  universal treatment); profit/loss share update the capital balance without posting to the shared
  Owner's Equity account, since doing so correctly needs per-partner sub-accounts this phase didn't build
  (documented, not faked).
- **Fixed assets + straight-line depreciation**: registering an asset posts nothing (funding source is
  caller-specific); running depreciation posts automatically (universal treatment), is idempotent per
  period, and correctly caps at the asset's salvage value.
- **Liabilities**: recording a loan posts automatically (universal treatment); recording any other liability
  category doesn't (funding/purpose is caller-specific); paying down a liability only posts when there was a
  matching origination entry to append to.

## The one thing worth highlighting: a real bug caught before it shipped

The first draft of `LiabilityService::recordPayment()` posted a ledger entry for every payment regardless of
liability category. Writing a test for paying a `recordOther()`-created liability (which deliberately has no
origination entry) exposed that this would debit Accounts Payable with no offsetting credit ever recorded —
a real, silently-wrong financial record. Fixed before merging: payment only posts to the ledger for `loan`
liabilities, which are the only category with a matching origination entry. This is exactly the kind of
mistake Phase 9's restraint (§1 of that phase's doc) was written to avoid making across a dozen call sites at
once — and it happened here anyway, in a single, deliberately narrow, well-understood case, which is the
argument for why the broader retrofit Phase 9 declined would have been far riskier.

## Documented, not built this phase (with reason)

| Finding | Why not built now | Doc |
|---|---|---|
| Per-partner equity sub-accounts (needed for correct profit/loss-share GL posting) | This phase's single shared Owner's Equity account would conflate every partner's capital if credited per-share; needs either dynamic per-partner accounts or a parent/child chart-of-accounts structure | `PHASE_10_PARTNERS_ASSETS_LIABILITIES.md` §1, §4 |
| Asset disposal accounting (gain/loss, removing accumulated depreciation) | `status`/`disposed_at` fields exist; no service logic built yet - separate, real piece of work | `PHASE_10_PARTNERS_ASSETS_LIABILITIES.md` §4 |
| Non-straight-line depreciation methods | Not in the roadmap's one-line scope | `PHASE_10_PARTNERS_ASSETS_LIABILITIES.md` §4 |
| No UI | This phase delivers the backend; matches every prior phase's pattern | `PHASE_10_PARTNERS_ASSETS_LIABILITIES.md` §4 |

## Verification performed

- Migration run clean against the real MariaDB instance this repo's test suite uses; new chart-of-accounts
  rows confirmed appended (not duplicated) to Phase 9's seed.
- `php -l` clean on every touched/new PHP file.
- Full suite run after the change: **265/265 passing**, zero regressions.
- Every automatic ledger posting in this phase (contribution, withdrawal, loan origination, loan payment,
  depreciation) has a direct test proving the entry is balanced and lands on the correct accounts with the
  correct sign — not just that the calling method returns success.
- The depreciation salvage-value cap is proven by running a short-lived asset to full exhaustion (3 calls,
  the 3rd correctly returning `null`), not just checking one period's math.
- The liability-payment bug (see above) is proven by a dedicated test confirming zero journal entries post
  for a non-loan payment, not just that the balance update looks right.

## What Phase 10 did not do (explicitly, scope boundaries)

Did not build per-partner GL sub-accounts (profit/loss share isn't GL-posted as a result). Did not build
asset disposal accounting. Did not build non-straight-line depreciation. Did not build any new UI.
