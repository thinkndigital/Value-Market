# Phase 17 — Full QA and Production Readiness

## 1. Scope, and an honest gap

`docs/IMPLEMENTATION_ROADMAP.md`'s Phase 17 description: *"End-to-end verification against the master
prompt's Section 51 standard before calling anything done."* The "master prompt" is the original external
instruction set that started this multi-phase project (referenced by section number throughout
`IMPLEMENTATION_ROADMAP.md` - "Section 43", "Section 6", "Section 34", "Section 47" - each time citing a
specific numbered section of it). It is not itself a file in this repository, and its literal text is not
available in this session. Rather than fabricate what "Section 51's standard" says and verify against an
invented bar, this phase names that gap honestly and instead runs the most rigorous, concrete QA pass
achievable against this project's own actually-demonstrated standard: the same "verified, not assumed"
discipline `docs/SECURITY_AUDIT.md` §2 established in Phase 1, applied here end-to-end across the whole
application rather than one subsystem at a time.

Four checks were run, each chosen because it verifies something no single prior phase's own test suite run
could catch (a full fresh migration sequence, a full route-table consistency check, a full dependency
security audit, a full `php -l` sweep) - the kind of whole-application verification that's easy to skip when
work happens phase-by-phase.

## 2. Full fresh migration + route-table check

**Migration**: `php artisan migrate:fresh --force` against a fully empty database runs the entire history -
every migration from `2019_12_14_000001_create_personal_access_tokens_table` through this session's own
`2025_02_13_000000_add_performance_indexes` (~40 files spanning the original Laravel skeleton, the baseline
eShop Plus schema, and every phase this project added) - in order, with zero errors. This had not been
verified end-to-end before; every prior phase ran its own migration(s) individually or via the test suite's
`RefreshDatabase` (which does run the full set, but silently, per-test, without anyone reading the output).
Run explicitly and read this time.

**Route names**: attempting `php artisan route:cache` (a standard Laravel production optimization) failed
immediately on a duplicate route name. Enumerating every route name found **~50 groups** of duplicate names
across the admin/seller/delivery_boy panels and the three v1 API surfaces - Laravel's named-route registry
is process-wide, so two routes registered under the same name anywhere in the app collide, even across
unrelated route files. One (`get_zones`, duplicated identically across `routes/api.php`/`seller_api.php`/
`delivery_boy_api.php`) was fixed: confirmed via grep that no code anywhere calls `route('get_zones')` by
name, so renaming two of the three copies to `seller_api.get_zones`/`delivery_boy_api.get_zones` is a pure
name-only change with zero effect on the actual URIs the Flutter apps hit. The remaining ~50 were spot-
checked (`categories.update`, `taxes.destroy`, `blogs.destroy` - the three with a confirmed `route()` call
site) and found to currently resolve to the URL the calling code actually needs, by coincidence of route-
registration order, not by design - real technical debt and a genuine `route:cache` blocker, but **not**
observed to be a live functional bug today. Fixing all ~50 correctly requires per-case verification of
intended behavior (which duplicate SHOULD win the name, and whether any Blade/JS code depends on the
current, accidental winner) - real, bounded work, but not something to rush through blind for 50 cases in
one unsupervised pass. Documented in full in `docs/DEPLOYMENT.md` with the exact reproduction command and
recommendation, not silently dropped.

## 3. Dependency security audit

`composer audit` at the start of this phase: **61 advisories across 22 packages (2 critical, 13 high, 34
medium, 9 low, 3 unrated)**. Two were fixed immediately as targeted, verified-safe updates:

- **`livewire/livewire` v3.5.18 → v3.8.6** (CVE-2025-54068, CRITICAL - remote command execution during
  component property update hydration). The locked version was inside the vulnerable range
  (`>=3.0.0-beta.1,<3.6.4`); the fix version is still within this project's own existing `^3.4` constraint -
  a same-major-version patch update, not a breaking change.
- **`mtdowling/jmespath.php` 2.8.0 → 2.9.2** (CVE-2026-54133, CRITICAL - code injection via unescaped
  function names, a transitive dependency of `aws/aws-sdk-php`). Same situation: the fix version satisfies
  the existing `^2.6` constraint `aws/aws-sdk-php` already declares.

With both criticals closed, three long-standing `*` (unbound) version constraints in `composer.json`
(`league/flysystem-aws-s3-v3`, `pusher/pusher-php-server`, `razorpay/razorpay` - the only three packages in
the file not already following its own convention of an explicit range) were pinned to caret constraints
matching their currently-locked major version, then a full `composer update` was run - which, respecting
every package's existing semver constraint (including these three newly-pinned ones), pulled in every
further fix available without a breaking major-version jump: `laravel/framework` v10.48.25 → v10.50.3 (still
inside `10.*`), `guzzlehttp/guzzle`, `league/commonmark`, `symfony/*`, and dozens more, resolving **50 more
advisories down to 11 remaining, across 3 packages, zero of them critical or high**.

The 11 remaining all require a **major** version bump to close fully: `dompdf/dompdf` (this app's PDF
generation for invoices/parcel labels - `laraveldaily/laravel-invoices` depends on it) v2→v3, `spatie/
laravel-medialibrary` (a file-upload restriction bypass and an SSRF finding - genuinely worth prioritizing,
but the fix requires v10→v11) v10→v11, and `laravel/framework` itself (a CRLF injection in the framework's
default email validation rule, fixed only in 12.60+/13.10+ - this app is on Laravel 10.x) 10.x→12.x/13.x.
Each is real breaking-change territory affecting security-sensitive code (file uploads, PDF rendering, the
framework core) - exactly the class of change this project's own established discipline (see the RBAC
redesign and `Model::unguard()` deferrals in `docs/SECURITY_AUDIT.md` §6.3) declines to attempt blind,
unsupervised, in one pass. Documented with exact current/required versions in `docs/DEPLOYMENT.md` §4 as a
concrete, scoped follow-up.

Also confirmed clean this pass: no hardcoded secrets/API keys/private keys anywhere in the repository
(pattern-scanned for AWS access keys, Stripe live-mode secret keys, PEM private key headers); `.env` is
correctly gitignored and never committed (only the credential-free `.env.example` template is); dev-only
tooling (`barryvdh/laravel-debugbar`, `spatie/laravel-ignition`, `phpunit/phpunit`, ...) is correctly
declared under `composer.json`'s `require-dev`, so `composer install --no-dev` excludes it from a production
install as intended.

## 4. Whole-codebase syntax sweep

`php -l` run across every file in `app/` (not just files touched this session, or this phase) - zero syntax
errors. This is a cheap, fast, whole-repository check that no prior phase's own scoped verification could
have caught for files outside its own touched set.

## 5. Full regression check after the dependency update

The `composer update` in §3 changed dozens of packages simultaneously, including the framework itself - by
far the largest single set of changes this session made without individually re-reading every touched file
(impractical for a dependency update spanning that many packages). The safety net: a full fresh migration
(§2) plus the complete test suite (320 tests) run again immediately after, both clean, zero regressions.
This is the specific reason a full, whole-suite regression run - not a scoped one - matters for this kind of
change in a way it doesn't for a normal application-code commit.

## 6. `docs/DEPLOYMENT.md`

Written this phase - the concrete deliverable Phase 1's own `docs/SECURITY_AUDIT.md` §3 named in advance
("this belongs in docs/DEPLOYMENT.md when that's written (Phase 17)") for the `APP_DEBUG=true` production
misconfiguration risk. Extended with everything else this phase's QA pass surfaced: the migration/route-cache
findings from §2, the dependency-currency findings from §3, and an explicit list of what real-infrastructure
verification (load testing, a real staging dry run, queue-worker supervision) this phase could not perform
in this dev environment and why.

## 7. What Phase 17 explicitly did not do

Did not verify against the master prompt's literal Section 51 text (unavailable this session - see §1).
Did not fix all ~50 duplicate route-name groups (see §2; one fixed, the rest documented with reasoning). Did
not perform the 3 remaining major-version dependency upgrades (see §3; each is real, scoped, deferred with
exact target versions named). Did not perform load/stress testing or a real staging-environment dry run (no
production-equivalent infrastructure exists in this session's environment). Did not audit the Flutter mobile
apps (not present in this repository, confirmed absent since this project's own Phase 0 audit).
