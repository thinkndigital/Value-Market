# Phase 1 — Database Migration Plan

**Status: implemented and verified.** Every claim below was tested against a real MariaDB 10.11 instance
(via a scratch `mariadb-server` install in this session) and, separately, against the actual eShop Plus
application source now checked into this repo — not asserted from reading the schema alone.

## 1. What "baseline" means here, and why

Task A asked for "a proper Laravel migration baseline for the existing schema," explicitly warning against
blindly generating migrations from the SQL dump. Two things made a straightforward `Schema::create()`
fluent-builder translation of all 89 tables too risky to do by hand:

1. **The schema is large and inconsistent in ways that are easy to mistranslate** — `double`, `double(10,2)`,
   `float`, and `decimal(10,2)`/`decimal(15,2)` all appear as "the same kind of column" across different
   tables; some `id` columns are `int(11)`, others `bigint(20) unsigned`, others `mediumint(8) unsigned`.
   Hand-translating 89 tables' worth of these into fluent Blueprint calls risks silently changing a type
   somewhere and nobody noticing until production data doesn't fit.
2. **The existing migration files in this codebase are not trustworthy as a starting point.** Only 3
   migration files exist at all (`create_users_table`, `add_avatar_to_users`, and a corrupted-filename
   file), against a live schema of 89 tables — see §2 below for exactly how misleading this is.

**Decision**: the baseline (`database/migrations/2025_01_01_*_baseline_*.php`, 12 files grouped by domain)
reproduces the audited schema dump **verbatim**, via `DB::unprepared()` executing the exact `CREATE TABLE`
/ index / constraint SQL from `docs/DATABASE_GAP_ANALYSIS.md`'s source dump, rather than a fluent-builder
reinterpretation. This guarantees byte-for-byte fidelity that can be (and was) mechanically verified against
the original dump — a translation into Blueprint calls could not offer the same guarantee without
essentially re-deriving the same dump by hand and hoping nothing was missed. Every subsequent, *intentional*
change (InnoDB conversion, DECIMAL conversion) is a **separate** migration on top, so the baseline itself
stays a clean, auditable snapshot with zero editorial changes except the one documented in §4.

Each baseline migration guards every table with `if (!Schema::hasTable($t))`, so it is **idempotent** in
both directions: run against a brand-new database, it creates the full schema from scratch; run against an
existing eShop Plus production database (schema already installed via the product's SQL installer), every
`Schema::hasTable` check is true and the migration is a safe no-op for table creation — while still letting
Laravel's own `migrations` table start recording real history (see §2).

## 2. The existing migration files were not just incomplete — they were misleading

Only 3 files were ever provided for `database/migrations/`, but the live schema's own `migrations` table
(captured in the audited dump) lists **8** migrations as having run in batch 1:
`create_users_table`, `create_password_reset_tokens_table`, `create_failed_jobs_table`,
`create_personal_access_tokens_table`, `add_active_status_to_users`, `add_avatar_to_users`,
`add_dark_mode_to_users`, `add_messenger_color_to_users`. Five of those eight files were never provided to
this session, so their content is unknown.

Of the 3 files that were available:

- **`2014_10_12_000000_create_users_table.php`'s `up()` method is completely empty.** It does nothing. Yet
  the database's own bookkeeping (the `migrations` table) records it as successfully run. The real 50-column
  `users` table was created by eShop Plus's SQL installer, not by this file — someone gutted the migration
  and left the bookkeeping row in place (almost certainly so a fresh `php artisan migrate` wouldn't try to
  `CREATE TABLE users` a second time and fail).
- **`2023_12_12_999999_add_avatar_to_users.php` is real and correct** — it adds the `avatar` column,
  guarded by `Schema::hasColumn`, matching a column that genuinely exists in the live schema.
- **The third file, named `2024_04_03_124401_add_google_id_column#U201d.php`** (note the stray smart-quote
  character baked into the filename — a copy-paste artifact), **has nothing to do with a Google ID column**.
  Its content is an unrelated, unmodified copy of Laravel's own default `create_users_table` stub (the
  7-column version: id/name/email/password/remember_token/timestamps). It is not recorded in the
  `migrations` table, so it most likely was added after the dump was taken and has never actually been run
  — if it ever is, it will fail immediately with "table already exists." This file is excluded from the
  baseline; do not resurrect it under its original name or intent.

**Practical conclusion**: nothing about the 3 provided migration files could be trusted as a starting point
for a baseline. The new baseline migrations replace them entirely (see the "Migration bookkeeping" note in
§5 for how this interacts with a database that has these fake entries in its `migrations` table already).

## 3. Verification performed (not just planned)

1. Imported the audited, unmodified schema dump into a scratch MariaDB database (`eshop_baseline`) — 90
   tables (89 + Laravel's own `migrations`), zero import errors.
2. Generated the 12 baseline migration files programmatically from that same verified dump (a small,
   throwaway Python script — not checked into the repo — parsed `CREATE TABLE`/index/constraint blocks and
   emitted the guarded PHP; this is why 89 tables' worth of DDL contains no hand-transcription typos).
3. Built a full, fresh Laravel 10 application skeleton in a scratch directory, ran `composer install`
   against the **actual** eShop Plus `composer.json`/`composer.lock` (not a generic skeleton's), and layered
   the real `app/`, `config/`, `routes/`, `resources/` on top.
4. Ran `php artisan migrate` against a brand-new database. Found and fixed 3 real bugs in the process (not
   simulated — each below is a genuine failure the migration run produced):
   - **FK ordering bug** (my own generator's output, not a codebase bug): `seller_store`'s two foreign keys
     reference `seller_data.id` and `users.id`; the generator initially emitted `seller_store` before
     `seller_data` in the same migration file, so the FK's `ALTER TABLE` ran before the referenced table's
     primary key existed (`errno 150`). Fixed by reordering.
   - **Duplicate constraint bug** (generator bug): a regex meant to capture index-only `ALTER TABLE ... ADD
     KEY` statements also matched `ADD CONSTRAINT` statements (both start with `ADD `), so `seller_store`'s
     foreign keys were emitted twice, and the second `ALTER TABLE` failed with `errno 121` ("duplicate key")
     because the constraint already existed from the first. Fixed with a negative lookahead.
   - **Invalid zero-date default** (real eShop Plus schema bug, not a generator bug — see §4): the baseline
     failed outright under MySQL/MariaDB's default strict mode.
5. After fixing those three issues, the full baseline (12 migrations) + the InnoDB conversion migration +
   the DECIMAL conversion migration ran clean, in order, against both a scratch database and the actual
   application's own dev database.
6. Diffed the resulting schema (via `mysqldump --no-data`, table-by-table) against the original, untouched
   dump: **every one of the 89 tables is structurally identical**, except the one documented, necessary
   deviation in §4. Table engines were compared separately and also matched exactly (before the InnoDB
   migration ran).
7. Re-ran the full migration set a second time against the already-migrated database to confirm
   idempotency: `php artisan migrate` reported "Nothing to migrate," as expected.
8. Automated as `tests/Feature/Phase1/MigrationBaselineTest.php` — table count, InnoDB engine on the 11
   converted tables, DECIMAL type on 7 representative money columns, the 2 `seller_store` foreign keys, and
   idempotency — all passing (`php artisan test`, see the Phase 1 report for full output).

## 4. The one intentional deviation from the literal dump

`wallet_transactions.last_updated` is defined in the live schema as:

```sql
`last_updated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE current_timestamp()
```

`'0000-00-00 00:00:00'` is a pre-strict-mode MySQL zero-date. It is **rejected outright** by MySQL/MariaDB
under `NO_ZERO_DATE`/strict mode — which is Laravel's own default connection setting
(`config/database.php`'s `'strict' => true`). This isn't a style preference; the literal baseline DDL
**fails to execute** the moment a strict-mode connection (i.e., any normal Laravel app, including this one)
tries to run it. The fix changes the default to `current_timestamp()`, matching the sibling column
`date_created`'s own default and the column's existing `ON UPDATE current_timestamp()` clause (which already
implies "track the last write time"). No other column in the 89-table schema has this problem — checked by
grepping the full dump for `0000-00-00`.

## 5. Migration bookkeeping on an existing production database

Because the baseline is guarded (`Schema::hasTable`), running it against a database that already has the
real eShop Plus schema installed is safe — it does nothing to the 89 existing tables. What it *does* do is
give Laravel's `migrations` table honest, complete entries for the first time (see §2: the current bookkeeping
contains fabricated/empty entries). This is a net improvement, not a risk: `php artisan migrate:status` will
finally reflect reality.

**Before running any of this against a real production database**: back it up first (standard practice this
plan assumes rather than re-states), then run in this order — baseline migrations (no-op on existing
tables) → `db:orphan-report` (Task D, see `docs/PHASE_1_DATA_INTEGRITY_REPORT.md`) → resolve anything it
flags → InnoDB conversion migration → `money:precision-report` if/when it's built out further (Task C, see
`docs/PHASE_1_FINANCIAL_PRECISION.md`) → DECIMAL conversion migration.
