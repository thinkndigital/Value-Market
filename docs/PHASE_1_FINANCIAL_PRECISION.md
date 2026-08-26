# Phase 1 — Financial Precision (Task C)

## 1. The central rule

Every monetary/rate/exchange-rate column moves from `double`/`float`/`varchar` to `DECIMAL`. Three fixed
precision tiers, chosen once here so no later phase needs a second precision migration:

| Tier | Precision | Applies to |
|---|---|---|
| Amount | `DECIMAL(15,4)` | Prices, totals, balances, delivery charges, discounts, commissions-as-amount |
| Rate | `DECIMAL(8,4)` | Percentage columns (e.g. `order_items.tax_percent`) |
| Exchange rate | `DECIMAL(20,10)` | `currencies.exchange_rate`, `orders.order_payment_currency_conversion_rate` |

4 fractional digits on amounts absorbs repeated rounding across tax/commission splits without the
compounding error `double` introduces; exchange rates get materially more precision because they're
multiplicative factors applied to potentially large totals, not stored balances themselves.

## 2. Exact column inventory (47 columns, source-verified)

Grepped the full audited schema dump for every `double`/`float` column and, separately, every column whose
name suggests money regardless of declared type (to catch the one that wasn't `double` at all — see §3).
Full table in `app/Console/Commands/MoneyPrecisionReport.php` and
`database/migrations/2025_01_03_000000_convert_money_columns_to_decimal.php` (both list the same 47
columns; the migration is the source of truth). Deliberately **excluded** after review, not overlooked:

- `*.rating` columns (`products.rating`, `combo_products.rating`, `product_ratings.rating`,
  `seller_store.rating`, `stores.rating`) — review scores (0–5), not money.
- `product_variants.weight/height/breadth/length` — physical dimensions (`float`), not money.
- `offers.min_discount`/`max_discount`, `stores.delivery_charge_amount`/`minimum_free_delivery_amount` —
  currently `int`. Left alone: converting these requires knowing whether they're meant to be whole-currency
  amounts or something else business-rule-defined, which wasn't confirmed. Flagged for a deliberate decision
  in whichever phase next touches offers/store delivery settings, not converted blind here.

## 3. `currencies.exchange_rate` was not a number at all

The single most consequential value for multi-currency correctness — the exchange rate — was stored as
`varchar(256)`, not any numeric type. `combo_products.delivery_charges` was `varchar(256)` too, inconsistent
with the equivalent `products.delivery_charges` column (`double`). Both now `DECIMAL`. Converting a varchar
column to `DECIMAL` is exactly why `money:precision-report`'s non-numeric check exists — see §5.

## 4. Data validation tooling (built and verified, not just planned)

`php artisan money:precision-report [--csv=path]` (`app/Console/Commands/MoneyPrecisionReport.php`) scans
every column in §2 that hasn't already been converted and reports, without changing any data:

- **Non-numeric values** — only possible on the two varchar-typed columns (§3); these would make the
  conversion migration's `ALTER TABLE` fail outright.
- **Values that would lose precision** — more fractional digits than the column's target scale, which the
  `ALTER TABLE MODIFY` would silently round away.

**Verified working, including a real bug found and fixed while verifying it**: the first version compared a
value already cast to the target scale against itself rounded to the target scale — always equal, so it
never actually caught anything. Fixed by casting to a deliberately higher-precision intermediate
(`DECIMAL(30,15)`) before comparing. Confirmed against a scratch database seeded with `'not-a-number'` (correctly
flagged as non-numeric) and `'1.23456789012'` against the 10-decimal exchange-rate target (correctly flagged
as precision loss — the 11th digit would be rounded away). Automated as
`tests/Feature/Phase1/MoneyPrecisionReportTest.php` (3 tests, all passing).

## 5. Data validation — what could and couldn't be done in this session

**Could verify**: the tool correctly detects non-numeric and precision-losing values (§4), and the
conversion migration correctly preserves exact values on real data (e.g. an order total of `99.99` survived
the conversion as `99.9900`, confirmed by direct query after running the migration against the actual
application's own dev database).

**Could not verify**: there is no real production data in this repository (see
`docs/PHASE_1_DATA_INTEGRITY_REPORT.md` §1 for the same limitation applied to foreign keys). The seed data
in the audited dump (currencies, countries, etc.) came back clean when scanned — zero non-numeric or
precision-loss flags — but that's 18 reference tables' worth of clean seed data, not a real transactional
history. **Before running `2025_01_03_000000_convert_money_columns_to_decimal.php` against a real
production database, run `php artisan money:precision-report` first and resolve everything it flags.** The
migration's own docblock repeats this instruction so it isn't missed by someone reading the migration in
isolation.

## 6. Rounding policy going forward

Not silently rounding existing data (Task C's explicit rule) only covers the *migration*. It does not by
itself define a rounding policy for *new* calculations once columns are `DECIMAL` — that's a currency/money
question for whichever phase builds the Commerce/Accounting engines properly (multiply-then-round vs.
round-then-multiply, banker's rounding vs. half-up, where in a tax/commission split calculation rounding
happens). Phase 1 deliberately doesn't invent this policy — it only fixes the storage type. Existing display
formatting (`formatePriceDecimal()` in `app/function_helper.php`, `CurrencyService::formateCurrency()`) is
untouched and still hardcodes 2 decimal places regardless of currency (so JPY would still display 2 decimals
it shouldn't have, KWD would still be missing a decimal it should have) — a pre-existing simplification,
noted here because it's directly adjacent to this work, not fixed here because display formatting is a
separate concern from storage precision and touching it risks a visible UI regression outside this phase's
scope.
