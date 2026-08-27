# Phase 9 Final Report — Accounting + Unified Ledger

**Status: the ledger engine is complete and tested; module-wide retrofit is deliberately deferred — read
§1/§4 of `PHASE_9_ACCOUNTING_LEDGER.md` before treating any part of this as "the platform's accounting is
done."** That document carries full design/implementation detail; this report is the index and the numbers.

## Exact numbers

| Metric | Count |
|---|---|
| Commits this phase | 1 |
| New tables | 3 (`chart_of_accounts`, `journal_entries`, `journal_lines`) |
| Seeded chart-of-accounts rows | 9 |
| New models | 3 |
| New services | 1 (`LedgerService`) |
| Existing method extended (single integration point) | 1 (`WalletService::updateWalletBalance()` — all 15 existing call sites unchanged) |
| New Phase 9 test files | 2 |
| New Phase 9 tests | 12 |
| Existing test updated (not a regression — expected table-count shift) | 1 (`MigrationBaselineTest`, 109 → 112) |
| Total test suite (Phase 1–9) | 245 passing, 0 failing |
| Total test suite at Phase 9 start | 233 |

## What changed

- **A real double-entry ledger engine**: `LedgerService::postEntry()` enforces the fundamental accounting
  invariant (debits = credits) before writing anything, rejects malformed lines (both debit and credit set,
  or neither) and unknown account codes, and leaves zero rows behind on rejection. `accountBalance()`
  computes normal-balance-aware signed balances.
- **A minimal seeded chart of accounts** (9 accounts) — explicitly a starting point, not a claim of a
  complete business-specific chart of accounts (see below).
- **One integration point, chosen deliberately**: `WalletService::updateWalletBalance()`, the single
  chokepoint all 15 existing wallet-changing call sites across the app already share. Every real wallet
  balance change now also posts a balanced journal entry against the Customer & Vendor Wallet Liability
  account, with Suspense/Uncategorized as the counter-account (see below for why) — and an edge case found
  and correctly guarded during implementation: a pre-existing `razorpay`-type branch that creates a
  `Transaction` audit row without actually moving the balance now correctly posts *no* journal entry either.

## The one thing to read carefully: this is a ledger engine, not a finished accounting system

Retrofitting every money-moving module (order placement, POS, affiliate commissions, delivery earnings,
refunds, fund transfers) to post fully and correctly categorized journal entries in one pass would mean
guessing at real business-specific chart-of-accounts design decisions with no accountant or business owner
in the loop — a risk of silently-wrong financial records, which is strictly worse than not having ledger
entries yet for those flows. This phase deliberately did not do that. It built the correct, tested
*mechanism* (the engine + one proven-safe integration through the wallet chokepoint), and documents the
rest as real, named follow-up work rather than quietly implying it's done because a "Phase 9 — Accounting"
report exists.

## Documented, not built this phase (with reason)

| Finding | Why not built now | Doc |
|---|---|---|
| Retrofitting order placement / POS / affiliate / delivery / fund-transfer flows to post fully-categorized ledger entries | Correct categorization is a business-specific accounting decision this phase can't make unilaterally by reading source code; every entry that *does* post today (via the wallet chokepoint) is real and balanced, just landed in Suspense pending proper classification | `PHASE_9_ACCOUNTING_LEDGER.md` §1, §3 |
| AR/AP workflow (invoices, bills, aging) | Placeholder accounts exist in the seed; the roadmap's own phase split puts AR/AP workflow detail in Phase 10, built on this phase's ledger | `PHASE_9_ACCOUNTING_LEDGER.md` §4 |
| Reclassifying Suspense-landed entries | Natural follow-up tool, not built now | `PHASE_9_ACCOUNTING_LEDGER.md` §4 |
| Financial statement reporting (trial balance, P&L, balance sheet) | `accountBalance()` is the correct primitive; no reporting UI/endpoint built this phase | `PHASE_9_ACCOUNTING_LEDGER.md` §4 |

## Verification performed

- Migration run clean against the real MariaDB instance this repo's test suite uses; chart-of-accounts seed
  confirmed present (9 rows, correct codes/types) via direct query after migrating.
- `php -l` clean on every touched/new PHP file, including the modified `WalletService.php` (the single
  riskiest file touched this phase, given its centrality).
- Full suite run after the change: **245/245 passing**, zero regressions — critically including every
  pre-existing `WalletServiceTest` (Phase 1) and every test in Phases 7–8 that credits/debits a wallet
  indirectly (affiliate payouts, delivery earnings), none of which needed any change.
- The core invariant (debits = credits, rejected before any write) has direct, adversarial test coverage:
  unbalanced entries, malformed lines, unknown accounts, and confirmation that a rejected entry writes zero
  rows - not just that valid entries work.
- The wallet-integration edge case (the `razorpay` branch that doesn't move balance) was specifically
  tested, not just reasoned about - confirmed zero journal entries post when the underlying balance didn't
  actually change.

## What Phase 9 did not do (explicitly, scope boundaries)

Did not retrofit order placement, POS, affiliate commissions, delivery earnings, or fund transfers to post
categorized ledger entries directly — only the wallet chokepoint is wired, landing in Suspense pending real
business categorization. Did not build AR/AP workflow. Did not build financial statement reporting. Did not
expand the chart of accounts beyond the 9-account minimal seed this phase's own integration needed.
