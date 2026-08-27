# Phase 9 — Accounting + Unified Ledger

`docs/IMPLEMENTATION_ROADMAP.md` (Phase 9) scopes this as net-new: *"chart of accounts, journal entries,
GL, AR/AP — the platform's financial foundation, every other module's money movement posts here."*
`docs/DATABASE_GAP_ANALYSIS.md` §5 confirmed this is completely absent from the real schema.

## 1. Read this before anything else: what "posts here" honestly means in this pass

The roadmap phrase "every other module's money movement posts here" is the eventual destination, not
something this single phase can responsibly claim to have finished. A chart of accounts is inherently
**business-specific** — which expense categories exist, how commission vs. delivery-fee vs. refund money
gets classified, whether a given business needs departmental sub-accounts — is a real accounting design
decision, not something to invent by reading source code. Retrofitting *every* money-moving code path in
this application (order placement, POS sales, refunds, affiliate commissions, delivery earnings, fund
transfers) to post fully-categorized entries in one pass would mean guessing at that business design across
a dozen call sites, with no accountant or business owner in the loop — a real risk of silently-wrong
financial records, which is worse than not having them yet.

**What this phase actually delivers**: a real, correct, fully-tested double-entry ledger *engine*
(`chart_of_accounts`/`journal_entries`/`journal_lines`, with the debits-equal-credits invariant enforced by
the write path itself, not left to callers), a sensible minimal default chart of accounts, and exactly
**one** wired integration point — chosen because it's the single safest, most centralized one available (see
§3). Every other module's ledger integration is explicit, scoped follow-up work, not silently implied as
done. This mirrors the same restraint Phase 2 applied to the ~90-site `TenantContext` migration and Phase 6
applied to combo-stock ledger integration — extend the riskiest surface conservatively, document the rest.

## 2. The ledger engine

`chart_of_accounts`: `code`/`name`/`type` (`asset`/`liability`/`equity`/`revenue`/`expense`)/`parent_id`
(hierarchy, unused by the seed below but available)/`is_system` (protects the seeded rows). Seeded with 9
minimal accounts covering what this phase's own integration point needs (Cash, Accounts Receivable, Accounts
Payable, Customer & Vendor Wallet Liability, Owner's Equity, Sales Revenue, Commission Expense, Delivery
Expense, and a Suspense/Uncategorized clearing account) — a *starting point* a real business will extend,
not a claim of a complete chart of accounts for every business this platform might run.

`journal_entries`/`journal_lines`: immutable once posted, matching this codebase's established ledger
pattern (Phase 5's `stock_movements`) — a correction is a new offsetting entry, never an edit.

`LedgerService::postEntry()` is the **only** way an entry gets created. It enforces, before writing anything:
at least two lines; each line has exactly one of debit/credit set (never both, never neither); and total
debits equal total credits (float-safe, rounded to 4 decimal places matching this codebase's established
money-precision convention). An entry that doesn't balance, or references an unknown account code, throws
before any row is written — proven by test, including that a rejected entry leaves zero rows behind.

`LedgerService::accountBalance()` computes a signed balance in each account's own normal-balance convention
(debit − credit for asset/expense; credit − debit for liability/equity/revenue) — not a raw, sign-ambiguous
difference.

## 3. The one wired integration point: `WalletService::updateWalletBalance()`

Grepping the codebase (the same audit technique Phase 5 used for `ProductService::updateStock()`) found
**all 15** existing wallet-changing call sites — the refer-a-friend bonus, Phase 7's affiliate commissions,
Phase 8's delivery earnings, order refunds, and more — already funnel through this one method. That makes it
the single safe place to post a real, balanced ledger entry for every wallet movement without touching any
of those 15 sites.

Every entry: `Suspense/Uncategorized (9000)` on one side, `Customer & Vendor Wallet Liability (2100)` on the
other — a credit increases the liability (platform owes the user more), a debit decreases it. **Suspense is
the counter-account deliberately, not a placeholder mistake**: `updateWalletBalance()` receives a
`transaction_type` string, but reliably mapping every value that flows through it (`'wallet'`, affiliate
commission credits, delivery earning credits, order-refund debits, ...) to the *correct* revenue/expense
account is exactly the business-specific categorization judgment call §1 explains this phase doesn't make
unilaterally. Every entry is real, balanced, and auditable either way — it just lands in a bucket flagged
"needs review" instead of a guessed-and-possibly-wrong one.

One subtlety caught by writing a test for it before trusting the integration: `updateWalletBalance()` has a
pre-existing branch where a `credit`/`refund` operation whose `Transaction.type == 'razorpay'` creates a
`Transaction` audit record but **does not** actually move the user's balance. The ledger posting is guarded
by the *actual* balance change, not just which branch ran — posting a journal entry there would have
recorded money moving when it didn't.

## 4. Documented, not built this phase (with reason)

- **AR/AP schema and workflow** — `1100 Accounts Receivable` / `2000 Accounts Payable` exist in the seeded
  chart of accounts as placeholders for the eventual concept, but no code posts to them yet; no
  invoice/bill/aging workflow was built. The roadmap's own phase split (Phase 10 — Partners + Assets +
  Liabilities is explicitly "built on the Phase 9 ledger") confirms AR/AP workflow detail is meant to layer
  on top of this foundation, not be invented speculatively here.
- **Retrofitting every other money-moving module** (order placement/refunds, POS sales, affiliate
  commissions, delivery earnings, fund transfers) to post fully-categorized entries — see §1. This phase's
  contribution is the tested engine plus one proven-safe integration; wiring the rest requires the same
  care applied deliberately, module by module, ideally with real chart-of-accounts input from whoever runs
  this platform's books.
- **Reclassifying existing Suspense entries** — a batch/admin tool to move a Suspense-landed entry to its
  correct account once identified is natural follow-up, not built now.
- **Financial statements (trial balance, P&L, balance sheet reports)** — `LedgerService::accountBalance()`
  is the correct primitive for these, but no reporting UI/endpoint was built this phase.

## 5. Tests

`tests/Feature/Phase9/` (2 new files, 12 new tests):

- `LedgerServiceTest.php` (8) — a balanced entry posts; an unbalanced one is rejected; a line with both
  debit and credit (or neither) is rejected; fewer than two lines is rejected; an unknown account code is
  rejected *and confirmed to leave zero rows written*; `accountBalance()` computed correctly for both
  debit-normal and credit-normal account types; a three-way split entry (more than 2 lines, still balanced)
  is accepted.
- `WalletLedgerIntegrationTest.php` (4) — a real `WalletService::updateWalletBalance()` credit/debit each
  post a correctly-signed, balanced entry against the Wallet Liability account; the razorpay
  balance-doesn't-actually-move case posts **no** entry (the edge case described in §3); every entry written
  across a short sequence of real wallet operations stays balanced.

`tests/Feature/Phase1/MigrationBaselineTest.php`'s table-count assertion updated 109 → 112 for the 3 new
tables (expected consequence of this phase's migration).

Full suite: **245 passing** (233 before this phase), zero regressions — critically including every existing
`WalletServiceTest` (Phase 1) and every test across Phases 1–8 that exercises a wallet credit/debit
indirectly (Phase 7's affiliate payout, Phase 8's delivery earnings), none of which needed any change,
confirming the `LedgerService` integration is truly additive.
