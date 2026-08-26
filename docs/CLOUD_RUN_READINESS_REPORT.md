# Value Market — Cloud Run Readiness Report

Deployment/containerization preparation only. No business logic, RBAC, accounting, inventory, POS, or
customer-facing behavior was modified — see §"Files created/changed" for the exact, complete list.

## Files created/changed

**Created:**
- `Dockerfile` — multi-stage build (Composer deps → Vite assets → PHP 8.1 Apache runtime)
- `.dockerignore`
- `cloudbuild.yaml` — Cloud Build pipeline (build → push → deploy)
- `docker/apache-cloud-run.conf`, `docker/entrypoint.sh`, `docker/opcache.ini` — supporting runtime config
  referenced by the Dockerfile
- `docs/CLOUD_RUN_DEPLOYMENT.md` — full deployment guide
- `docs/CLOUD_RUN_READINESS_REPORT.md` — this file

**Changed (both explicitly authorized during this task — see "Decisions requiring judgment calls" below):**
- `resources/js/app.js` — created (was missing entirely; `vite.config.js` already declared it as a build
  entry, so its absence broke `npm run build` regardless of Docker). Content: `import './bootstrap';`,
  matching Laravel's own default scaffold and the pattern of the `bootstrap.js` file that already existed.
  Confirmed no Blade view's compiled-asset reference depends on any behavior beyond this — see below.
- `routes/web.php` — added a `GET /up` health-check route (no auth, no DB dependency, returns
  `{"status":"ok"}` only). Laravel 10 has no built-in equivalent (`/up` is a Laravel 11+ feature); this task
  requested one if none already existed.

Nothing else in the repository was touched.

## Build strategy

Three Docker stages:
1. `composer:2` — `composer install --no-dev --no-scripts --no-autoloader --prefer-dist`, reproducible from
   `composer.lock` alone (never `composer update`).
2. `node:20-bookworm-slim` — `npm ci --legacy-peer-deps && npm run build` (see "Decisions requiring judgment
   calls" for why `--legacy-peer-deps` is needed), producing `public/build/`.
3. `php:8.1-apache` — the two build outputs above are copied in, the full application source is added, the
   Composer autoloader is finalized (`composer dump-autoload --optimize --no-dev`, which also fires Laravel's
   normal `package:discover` step), `storage/`+`bootstrap/cache/` are created fresh with `www-data`
   ownership, and the image's entrypoint substitutes Cloud Run's `$PORT` into Apache's config at container
   start.

## PHP / Laravel versions

- PHP `^8.1` (`composer.json`) — Docker image: `php:8.1-apache`.
- Laravel `10.*` — no Laravel 11+ features assumed (confirmed the built-in `/up` route doesn't exist here,
  for exactly this reason).

## Required PHP extensions (and why each is actually needed, not just assumed)

| Extension | Why |
|---|---|
| `pdo_mysql` | This app's actual, exclusively-used database driver — every migration and query targets MySQL/MariaDB |
| `pdo_pgsql` | Requested explicitly; `config/database.php` declares a `pgsql` connection, though nothing in this app's schema/queries currently uses it |
| `gd` | `intervention/image`'s default driver (`composer.json`) |
| `imagick` | **Not in the task's original list — a real, confirmed runtime dependency found while auditing the codebase.** `app/Http/Controllers/{Seller,Admin}/MediaController.php` both instantiate `new Imagick()` directly (no GD fallback) to resize animated GIFs while preserving animation. Omitting it would fatal-error the first time anyone resizes an animated GIF. |
| `mbstring`, `bcmath`, `intl`, `zip`, `curl`, `xml`, `fileinfo`, `tokenizer`, `opcache` | Requested explicitly; consistent with this app's actual dependencies (Sanctum, Socialite, the AWS/Stripe/PayPal/Razorpay SDKs, `league/csv`, `laraveldaily/laravel-invoices`) |

`mbstring`, `curl`, `fileinfo`, `tokenizer`, and `xml` ship enabled by default in the official `php:8.1-apache`
image and needed no extra install step. `mysqli` was considered and deliberately **not** installed — grepped
the codebase for any raw `mysqli_*`/`new mysqli` usage and found none; everything goes through
PDO/Eloquent.

## Required environment variables

See `docs/CLOUD_RUN_DEPLOYMENT.md` §7 for the full `--set-env-vars`/`--set-secrets` reference. Summary: every
key `.env.example` already lists (APP_*, DB_*, MAIL_*, AWS_*, PUSHER_*), **plus** three deliberate departures
from `.env.example`'s local-dev defaults that matter specifically for Cloud Run's multi-instance, ephemeral
model — `CACHE_DRIVER`/`SESSION_DRIVER` away from `file`, `QUEUE_CONNECTION` away from `sync` only if a real
queue is introduced later, `FILESYSTEM_DISK` away from `local`, and `LOG_CHANNEL=stderr` instead of a file
channel. None of these are hardcoded anywhere in this repo — they're documented as the values to pass at
deploy time.

## Required Google APIs

`run.googleapis.com`, `cloudbuild.googleapis.com`, `artifactregistry.googleapis.com`,
`sqladmin.googleapis.com`, `secretmanager.googleapis.com`, `cloudscheduler.googleapis.com` (the last one only
actually needed once §11's scheduler wiring is implemented, but harmless to enable up front).

## Database requirements

MySQL/MariaDB (Cloud SQL for MySQL is the natural managed option — see
`docs/CLOUD_RUN_DEPLOYMENT.md` §4). **No database was created, and no migrations were run**, per the task's
explicit instructions. `DB_HOST` on Cloud Run should be the Cloud SQL Unix socket path
(`/cloudsql/<connection-name>`) via `--add-cloudsql-instances`, not a public IP.

## Storage requirements

`config/filesystems.php` defaults to `local` — Cloud Run's filesystem is ephemeral and not shared across
instances, so any file uploaded through this app on Cloud Run with the default disk would be lost on the
next scale event/restart and invisible to other concurrent instances in the meantime. The app already
supports `s3` as an alternate disk (real code, not something this task added) — switching
`FILESYSTEM_DISK=s3` at deploy time is the documented fix (`docs/CLOUD_RUN_DEPLOYMENT.md` §10), not
implemented here since it wasn't asked for and isn't a pure deployment-config change (it needs a real bucket
provisioned and credentials issued).

## Queue / scheduler requirements

`QUEUE_CONNECTION` defaults to `sync` (no worker required for the app to function as-is today).
`app/Console/Kernel.php` schedules two commands (`sitemap:generate` daily, `cart:send-reminders` daily at
09:00) that need `php artisan schedule:run` invoked every minute by something external — Cloud Run's
request-driven service model doesn't provide a cron equivalent on its own. Documented in
`docs/CLOUD_RUN_DEPLOYMENT.md` §11 as a Cloud Scheduler → Cloud Run Job wiring to set up before those two
scheduled tasks matter in production; not implemented here per the task's explicit "do not implement workers
yet unless required simply to make the web service boot."

## Decisions requiring judgment calls (asked, not assumed)

Two points during this task genuinely sat at the boundary between "deployment configuration" and "touching
application source," so they were put to the user rather than decided unilaterally:

1. **`resources/js/app.js` was missing entirely**, and `vite.config.js` requires it as a build entry —
   confirmed by actually running `npm run build` and watching it fail with `Could not resolve entry module
   "resources/js/app.js"`, independent of anything Docker-related. Further confirmed (grepped every Blade
   view) that nothing in the app currently consumes `resources/js/app.js`'s or `resources/css/app.css`'s
   compiled output via `@vite()` — the only `@vite()` calls reference static files under
   `frontend/elegant/css/*`, paths that aren't even in `vite.config.js`'s configured input list (a separate,
   pre-existing, seemingly-unrelated issue, left untouched). **User confirmed**: create a minimal
   `resources/js/app.js` (`import './bootstrap';`) — not business logic, just a missing build entry point.
2. Whether to create a health-check route — explicitly requested by the task itself (Task 8: "only if one
   does not already exist"), confirmed none does, added `/up`.

## Unresolved deployment blockers

None that block building and running the image itself (with the `resources/js/app.js` fix applied, `npm run
build` succeeds; the PHP/Composer side has no analogous issue). Genuine, pre-existing repo issues found and
**not** fixed, because fixing them would mean touching application source/dependencies beyond this task's
scope:

- **`npm ci` fails outright on a plain, unmodified checkout** due to an existing peer-dependency conflict
  (`@nextui-org/react@2.6.11` requires `framer-motion >=11.5.6`, but `package.json` pins `framer-motion
  ^10.16.4`). Worked around in the Dockerfile with `npm ci --legacy-peer-deps` (a flag, not a
  `package.json`/`package-lock.json` change — resolves to exactly what a developer's own
  `npm ci --legacy-peer-deps` would produce today). Confirmed by actually running both the failing and the
  working command, not assumed. A real fix (aligning `framer-motion`'s pinned version, or removing the
  unused `@nextui-org/react` dependency if it's genuinely unused) is a dependency-management decision outside
  this task's "do not modify package.json unless strictly necessary, do not upgrade dependencies" boundary.
- The `frontend/elegant/css/*` `@vite()` calls noted above appear to reference paths outside
  `vite.config.js`'s configured input — worth a maintainer's attention independent of this deployment task,
  since it suggests those specific `@vite()` calls may already be non-functional in whatever environment
  currently serves this app in "production," Cloud Run or otherwise.
- Storage (S3) and the scheduler (Cloud Scheduler → Cloud Run Job) are documented but not provisioned —
  see the two sections above; both need real infrastructure decisions (bucket name/region, IAM) beyond a
  generic template.

## Tests executed and results

| Check | Result |
|---|---|
| `php artisan test --testsuite=Feature` | **83 passed (135 assertions), 0 failed** — full existing suite, unaffected by this task's two source changes (confirmed by running it after each) |
| `php -l` on every new/modified PHP file (`routes/web.php`) | Clean |
| `python3 -c "import yaml; yaml.safe_load(...)"` on `cloudbuild.yaml` | Valid YAML |
| `sh -n docker/entrypoint.sh` | Valid shell syntax |
| `npm ci --legacy-peer-deps` | Succeeds (710 packages) — plain `npm ci` does not, see above |
| `npm run build` | Succeeds after adding `resources/js/app.js` — produces `public/build/manifest.json` + hashed CSS/JS assets |
| `docker build` | **Not run — no Docker daemon available in this environment** (`docker info` fails: `dial unix /var/run/docker.sock: connect: no such file or directory`; attempting to start it failed on a `ulimit` permission error specific to this sandbox). The Dockerfile, `.dockerignore`, and the three `docker/` support files were instead reviewed by hand for correctness, including one real issue caught this way: an initial draft purged `-dev` packages with `apt-get --auto-remove` after installing `gd`/`zip`/`intl`/`imagick`, which risks also removing the runtime shared libraries those extensions link against (nothing else would depend on them once the `-dev` metapackage is gone) — changed to leave them installed, trading image size for not silently breaking image processing in a way this environment can't catch before it reaches production. |
| `gcloud`/Cloud Run deploy | **Not attempted.** No claim of a working deployed service is made anywhere in this report or the deployment guide — both are unexecuted-but-reviewed instructions, exactly as the task requires. |

## What was NOT done (explicitly, per the task's own boundaries)

- No business logic, RBAC, accounting, inventory, POS, or customer-facing behavior touched.
- No `.env` file created or copied into the image or the repository.
- No secret value appears in any committed file.
- No database created, no migrations run, no Cloud Run deploy executed.
- No dependency versions upgraded; `composer.lock`/`package-lock.json` untouched.
- Phase 3 (or any other future-phase work) not started.
