# Deployment

This file exists because `docs/SECURITY_AUDIT.md` §3 (Phase 1) named it in advance: *"This belongs in
docs/DEPLOYMENT.md when that's written (Phase 17), flagged here so it isn't lost."* The item below is that
flagged operational reminder, plus the other production-readiness items Phase 17's QA pass surfaced.

## Before going live, in this order

### 1. Environment (critical - do this first)

`.env.example` ships Laravel's own standard **local-development** defaults:

```
APP_ENV=local
APP_DEBUG=true
```

**Production must override both** - `APP_ENV=production`, `APP_DEBUG=false`. This is not a defect in this
repository (`.env.example` is a template; the real production `.env` is never committed - confirmed
`.gitignore`d, confirmed not present in git history). It is stated here plainly because the consequence of
missing it is severe and easy to miss: with `APP_DEBUG=true`, an unhandled exception on any page renders a
full stack trace - including environment variable values - **to any visitor**, not just an admin. Verify
this explicitly on first deploy and on every server/container rebuild that starts from a fresh `.env.example`
copy, not just once.

Also confirm before going live: `APP_KEY` is generated fresh for production (`php artisan key:generate`,
never reused from a dev/staging environment), and every payment-gateway/notification credential in `.env`
(Stripe, Razorpay, Paypal, Firebase, AWS, Pusher, ...) is the real production credential, not a sandbox/test
key left over from development.

### 2. Run the full migration set

```
php artisan migrate --force
```

Verified this phase: the entire migration history (`2019_12_14` through `2025_02_13`, all ~40 files) runs
cleanly from empty on a fresh database, in order, with no errors - not just individually, as a full sequence
(see `docs/PHASE_16_PERFORMANCE_OPTIMIZATION.md`/`PHASE_17_FULL_QA_PRODUCTION_READINESS.md` for how this was
checked). `--force` is required outside `local`/`testing` environments; Laravel will otherwise prompt for
confirmation, which blocks a non-interactive deploy.

### 3. Standard production optimizations - with one caveat

```
php artisan config:cache
```
Verified clean this phase.

```
php artisan route:cache
```
**Currently fails.** This codebase has ~50 groups of routes across the admin/seller/delivery_boy panels and
the customer/seller/delivery_boy v1 API surfaces that share the same route **name** on different URIs (e.g.
`products.update` is registered once under `admin/products/{product}` and again, separately, under
`seller/products/{product}` - Laravel's named-route registry is process-wide, not scoped per route file).
`route:cache` treats a duplicate name as a hard build-time error. One instance (`get_zones`, duplicated
across `routes/api.php`/`seller_api.php`/`delivery_boy_api.php`) was fixed this phase - confirmed unused by
name anywhere in the codebase, so renaming two of the three was a pure, zero-risk, name-only change. The
remaining ~50 were investigated at a sample (`categories.update`, `taxes.destroy`, `blogs.destroy`) and
found to currently resolve to the *intended* route by coincidence of registration order, not by design - so
this is confirmed **not** an observed live bug today, but it is both (a) blocking a standard Laravel
performance optimization and (b) a latent risk: a future route-file reordering, or adding one more route
under either name, could silently change which URL `route('name', ...)` generates in Blade code that
currently works by accident. See `docs/PHASE_17_FULL_QA_PRODUCTION_READINESS.md` §2 for the full list and
why fixing all ~50 wasn't attempted blind this phase. Until resolved, **skip `route:cache`** - the
application runs correctly without it, just marginally slower per-request on route resolution.

```
php artisan view:cache
```
Not verified this phase - Blade compilation caching is generally low-risk, but wasn't explicitly exercised;
run it and smoke-test a few pages (storefront, seller panel, admin panel) before relying on it in production.

### 4. Dependency currency

`composer audit` is clean of every advisory resolvable within this project's existing version constraints
(patched from 61 advisories including 2 CRITICAL down to 11, this phase - see
`docs/PHASE_17_FULL_QA_PRODUCTION_READINESS.md` §3 for the full before/after). The 11 remaining all require a
**major** version bump of the affected package (`dompdf` v2→v3, `spatie/laravel-medialibrary` v10→v11,
`laravel/framework` 10.x→12.x/13.x) - genuinely breaking-change territory, not something to jump into as
part of a routine deploy. Run `composer audit` again before each production deploy; if a NEW advisory
appears within the existing constraints (not requiring a major bump), pull it in the same way this phase
did (`composer update <package>`, then the full test suite) as routine maintenance.

### 5. Composer install for production

```
composer install --no-dev --optimize-autoloader
```

Confirmed this phase: dev-only tooling (`barryvdh/laravel-debugbar`, `spatie/laravel-ignition`,
`fakerphp/faker`, `phpunit/phpunit`, `mockery/mockery`, ...) is correctly declared under `require-dev`, so
`--no-dev` excludes it as intended - debugbar/ignition must never run in production (ignition's error page is
nearly as verbose as `APP_DEBUG=true`'s default trace).

## What was NOT verified this phase (be aware, not blocked)

- **Real infrastructure**: this dev environment runs a single MariaDB instance with no read replicas, no
  CDN, no real object storage (S3 etc. configured but not exercised end-to-end), no load balancer. None of
  the checks in this document substitute for a staging-environment dry run against your actual production
  topology.
- **Load/stress testing**: no concurrent-user or throughput testing was performed - this requires real
  infrastructure to be meaningful (see `docs/PHASE_16_PERFORMANCE_OPTIMIZATION.md` §4).
- **The Flutter mobile apps**: not present in this repository (confirmed absent since Phase 0's own audit -
  `docs/IMPLEMENTATION_ROADMAP.md`'s opening note); nothing about their build/release process is covered
  here.
- **Queue worker supervision** (Supervisor/systemd config, worker count, `queue:work` vs `queue:listen`):
  this application defines jobs but this phase did not audit or configure a production queue-worker
  deployment - use Laravel's own queue deployment documentation for your chosen driver.
