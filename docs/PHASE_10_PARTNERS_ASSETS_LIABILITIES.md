# Phase 10 — Partners + Assets + Liabilities

`docs/IMPLEMENTATION_ROADMAP.md` (Phase 10) scopes this as net-new: *"built on the Phase 9 ledger."*
`docs/DATABASE_GAP_ANALYSIS.md` §5 confirmed both "Partners / Shareholders" and "Assets / Liabilities" are
completely absent from the real schema.

Unlike Phase 9's deliberately narrow single integration point, this phase's three areas (partner capital,
fixed-asset depreciation, liability payments) each have a small, fixed, **universally well-defined**
accounting treatment — not the open-ended "which of a dozen business-specific categories does this belong
in" problem Phase 9 declined to guess at. That's what makes automatic ledger posting appropriate here where
it wasn't for a module-wide retrofit in Phase 9. Each service below is explicit about exactly which
operations post automatically and which deliberately don't, and why.

## 1. Partners (`PartnerService`)

`partners` + an immutable `partner_transactions` ledger (contribution / withdrawal / profit_share /
loss_share). `capitalBalance()` is always computed by summing transactions — never a separately-maintained
running total that could drift out of sync with the history (same principle Phase 4 applied to
`TenantContext` and Phase 5 to `stock_items`).

**Contribution and withdrawal** post automatically — a partner putting cash in, or taking cash out, has an
unambiguous treatment (`Debit/Credit Cash (1000)` against `Owner's Equity (3000)`).

**Profit share and loss share deliberately do not post to the general ledger.** Allocating a slice of
overall company profit into *one specific partner's* capital account requires a per-partner equity
sub-account — this phase's single shared `3000 Owner's Equity` account would conflate every partner's
capital together if credited per-partner-share. Building proper per-partner sub-ledger accounts is real,
scoped follow-up work (§4), not something to fake by posting to the wrong account. The transaction is still
recorded and still affects `capitalBalance()` — only the GL posting is deferred.

## 2. Assets (`AssetService`)

`assets` (acquisition cost, useful life, salvage value) + `depreciation_schedules` (one immutable row per
depreciation run).

**`registerAsset()` does not post an acquisition journal entry.** How an asset was paid for — cash on hand,
a loan (§3), a mix — is caller-specific context this method doesn't have; call `LedgerService::postEntry()`
or `LiabilityService::recordLoan()` directly for that, rather than this method guessing.

**Depreciation posts automatically**, because straight-line depreciation is a universal, well-defined
treatment: `((cost − salvage) / useful_life_months)` per period, `Debit Depreciation Expense (5200)` /
`Credit Accumulated Depreciation (1210)`. `runDepreciation()` is idempotent per `(asset_id, period_date)`
and caps the final period's charge so an asset never depreciates past its salvage value — both proven by
test, including the exact final-period capping arithmetic.

**Modeling note**: a real accumulated-depreciation account is a *contra-asset* — credit-normal despite
relating to assets. Rather than adding a sixth account-type kind to `LedgerService`'s two-bucket
normal-balance logic for this one account, `1210` is classified under the engine's existing `liability`
bucket purely so `accountBalance()` computes its sign correctly (credit − debit) — not a claim that
accumulated depreciation is actually a liability. Documented in the migration and here so it isn't
mistaken for a modeling error later.

## 3. Liabilities (`LiabilityService`)

**`recordLoan()`** posts automatically — a loan received has one universal treatment (`Debit Cash (1000)` /
`Credit Loans Payable (2200)`).

**`recordOther()`** (accrued expenses, etc.) does **not** post — like `registerAsset()`, the correct
offsetting account depends on what the liability is actually for, which this method doesn't know.

**`recordPayment()`'s real bug, found by writing a test before trusting the method**: the first draft posted
`Debit Payable / Credit Cash` for *every* payment, regardless of category. For a `recordOther()` liability —
which never got an origination entry — that would debit `Accounts Payable (2000)` with no matching credit
ever recorded, driving that account to a nonsensical negative balance. Fixed: `recordPayment()` only posts a
ledger entry when the liability is a `loan` (the only category with a matching origination entry to append
to); a non-loan liability's balance still updates correctly, just without a ledger entry that would
misrepresent the account.

## 4. What this phase does not do (explicitly, scope boundaries)

- **Per-partner equity sub-accounts** — needed to correctly GL-post `profit_share`/`loss_share` (§1). A
  real, scoped follow-up: either dynamic per-partner accounts under `3000`, or a parent/child chart-of-
  accounts structure using the `parent_id` column already present but unused by this phase's seed.
  Left un-built rather than posted incorrectly.
  - **Asset disposal accounting** (gain/loss on sale, removing accumulated depreciation) — `assets.status`/
  `disposed_at` exist as fields but no service method handles disposal; a real, separate piece of logic.
- **Depreciation methods beyond straight-line** (declining balance, units of production) — not asked for by
  the roadmap's one-line scope.
- **No UI** — this phase delivers the backend, matching every prior phase's pattern.

## 5. Tests

`tests/Feature/Phase10/` (3 new files, 20 new tests):

- `PartnerServiceTest.php` (6) — contribution/withdrawal post balanced entries and update the capital
  balance correctly; profit/loss share update the balance *without* posting (and are confirmed to post
  zero new journal entries, not just "no error"); a zero/negative amount is rejected; two partners' balances
  don't leak into each other.
- `AssetServiceTest.php` (7) — registration posts nothing; straight-line monthly amount computed correctly
  (including the zero-useful-life case); a real depreciation run posts a balanced entry and records the
  schedule; the same period is a no-op the second time; depreciation correctly stops at salvage value
  (proven by running it to exhaustion, not just checking one period); invalid salvage value is rejected.
- `LiabilityServiceTest.php` (7) — a loan posts a balanced origination entry; `recordOther()` posts nothing;
  `recordOther()` rejects the `loan` category (use `recordLoan()` instead); partial and full payment update
  balance/status correctly; overpayment is rejected; **the bug found and fixed in §3** — paying a
  non-loan liability updates its balance without posting a ledger entry, confirmed directly rather than
  just asserting the final balance looked right.

`tests/Feature/Phase1/MigrationBaselineTest.php`'s table-count assertion updated 112 → 117 for the 5 new
tables (expected consequence of this phase's migration).

Full suite: **265 passing** (245 before this phase), zero regressions.
