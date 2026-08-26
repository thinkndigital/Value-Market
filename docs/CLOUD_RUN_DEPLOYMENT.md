# Value Market — Google Cloud Run Deployment Guide

Deployment/containerization documentation only. Nothing here changes application, RBAC, accounting,
inventory, POS, or any other business logic — see `docs/CLOUD_RUN_READINESS_REPORT.md` for the exact list
of files this preparation touched and why each one was necessary.

## 1. Prerequisites

- A Google Cloud project. This guide assumes project id `value-market` (adjust `PROJECT_ID` below if
  different) and region `me-central1`.
- `gcloud` CLI installed and authenticated (`gcloud auth login`), or Cloud Build triggered from a connected
  GitHub repository (see §5).
- Billing enabled on the project (Cloud Run, Cloud SQL, Artifact Registry, and Cloud Build all require it).
- A MySQL/MariaDB-compatible database reachable from Cloud Run — Cloud SQL for MySQL is the natural fit
  (this app's schema and every query in it target MySQL/MariaDB specifically; PostgreSQL support exists in
  Laravel's config but is not what this application's migrations/queries actually use — see
  `docs/CLOUD_RUN_READINESS_REPORT.md` §Database).

## 2. Required Google Cloud APIs

```bash
gcloud services enable \
  run.googleapis.com \
  cloudbuild.googleapis.com \
  artifactregistry.googleapis.com \
  sqladmin.googleapis.com \
  secretmanager.googleapis.com \
  cloudscheduler.googleapis.com \
  --project=value-market
```

## 3. Artifact Registry (one-time setup)

The Docker repository this pipeline pushes to (`cloudbuild.yaml`'s `_REPOSITORY` substitution) is assumed to
already exist:

```bash
gcloud artifacts repositories create value-market \
  --repository-format=docker \
  --location=me-central1 \
  --description="Value Market container images" \
  --project=value-market
```

Resulting image path: `me-central1-docker.pkg.dev/value-market/value-market/value-market`.

## 4. Cloud SQL (database)

```bash
gcloud sql instances create value-market-db \
  --database-version=MYSQL_8_0 \
  --region=me-central1 \
  --tier=db-custom-2-4096 \
  --project=value-market

gcloud sql databases create value_market --instance=value-market-db --project=value-market

gcloud sql users create value_market_app \
  --instance=value-market-db \
  --password="<generate a strong password, store it in Secret Manager, not here>" \
  --project=value-market
```

Note the instance connection name (`gcloud sql instances describe value-market-db --format='value(connectionName)'`,
shaped like `value-market:me-central1:value-market-db`) — Cloud Run connects to it via the Cloud SQL Auth
Proxy sidecar Cloud Run manages automatically when you pass `--add-cloudsql-instances` on deploy (§7); the
application only ever sees `DB_HOST=127.0.0.1` (the local proxy socket), never Cloud SQL's public IP.

## 5. Secret Manager

Never put real values in `cloudbuild.yaml`, the Dockerfile, or any file committed to this repository. Create
each secret once:

```bash
printf '%s' 'BASE64_APP_KEY_HERE' | gcloud secrets create value-market-app-key --data-file=- --project=value-market
printf '%s' 'db-password-here'    | gcloud secrets create value-market-db-password --data-file=- --project=value-market
# Repeat for AWS_SECRET_ACCESS_KEY, STRIPE secret key, PAYPAL secret, RAZORPAY secret, MAIL_PASSWORD,
# PUSHER_APP_SECRET, and any other credential this app's .env.example lists.
```

Generate `APP_KEY` locally first (do **not** generate it inside the Docker build — see
`docs/CLOUD_RUN_READINESS_REPORT.md` §Environment):

```bash
php artisan key:generate --show
```

## 6. GitHub connection + Cloud Build trigger (optional, for CI/CD)

```bash
gcloud builds triggers create github \
  --repo-name=Value-Market \
  --repo-owner=thinkndigital \
  --branch-pattern="^main$" \
  --build-config=cloudbuild.yaml \
  --project=value-market
```

Adjust the branch pattern to whichever branch should auto-deploy. A manual build/deploy (no trigger needed)
is:

```bash
gcloud builds submit --config=cloudbuild.yaml --project=value-market
```

## 7. Cloud Run — first deploy (defines env vars, secrets, and Cloud SQL binding)

`cloudbuild.yaml`'s `deploy` step only updates the *image* on an existing service; the first deploy (or any
time env vars/secrets change) needs the fuller command below, run once manually:

```bash
gcloud run deploy value-market \
  --image=me-central1-docker.pkg.dev/value-market/value-market/value-market:latest \
  --region=me-central1 \
  --platform=managed \
  --allow-unauthenticated \
  --port=8080 \
  --min-instances=0 \
  --add-cloudsql-instances=value-market:me-central1:value-market-db \
  --set-env-vars="APP_NAME=Value Market,APP_ENV=production,APP_DEBUG=false,APP_URL=https://<your-cloud-run-url-or-custom-domain>,LOG_CHANNEL=stderr,DB_CONNECTION=mysql,DB_HOST=/cloudsql/value-market:me-central1:value-market-db,DB_DATABASE=value_market,DB_USERNAME=value_market_app,CACHE_DRIVER=database,SESSION_DRIVER=database,QUEUE_CONNECTION=database,FILESYSTEM_DISK=s3" \
  --set-secrets="APP_KEY=value-market-app-key:latest,DB_PASSWORD=value-market-db-password:latest,AWS_SECRET_ACCESS_KEY=value-market-aws-secret:latest" \
  --project=value-market
```

Notes on the env vars above (why these specific values, not the .env.example defaults):

- `DB_HOST=/cloudsql/<connection-name>` — the Unix socket path Cloud Run's Cloud SQL integration exposes
  when `--add-cloudsql-instances` is set; Laravel's `mysql` connection accepts a socket path as `DB_HOST`.
- `LOG_CHANNEL=stderr` — Cloud Run captures stdout/stderr into Cloud Logging automatically; a `single`/`daily`
  file channel would write to the container's ephemeral filesystem and be lost on every restart/scale event.
- `CACHE_DRIVER=database` / `SESSION_DRIVER=database` / `QUEUE_CONNECTION=database` — **not** this app's
  `.env.example` defaults (`file`/`file`/`sync`). Cloud Run can and does run multiple container instances of
  the same service concurrently; a `file`-driver session or cache written by one instance is invisible to
  another, which breaks "stay logged in" and any cache-dependent behavior unpredictably. `database` is the
  safe minimum given this app doesn't otherwise use Redis/Memcached in this deployment; using Redis
  (Memorystore) instead is a reasonable upgrade, not required for correctness.
- `FILESYSTEM_DISK=s3` — see §10 (Storage). Leaving this as `local` on Cloud Run means every uploaded file
  disappears the moment the instance recycles.

## 8. Cloud Run service settings (reference)

| Setting | Value |
|---|---|
| Service name | `value-market` |
| Region | `me-central1` |
| Authentication | Allow public access (`--allow-unauthenticated`) |
| Port | `8080` |
| Billing | Request-based (Cloud Run's default; do not enable "instance-based billing" unless a specific always-on workload requires it) |
| Scaling | Auto scaling, min instances `0` |

These are deployment settings, not application configuration — nothing in the app's code depends on them.

## 9. Database migrations

**Never run migrations as part of the Docker build or the Cloud Build pipeline automatically** (Task 6/13's
explicit constraint — a build step has no safe, reviewable moment to run destructive/irreversible schema
changes against a live production database). Run them as a deliberate, separate, manual step after a deploy
whose image you've reviewed:

```bash
gcloud run jobs create value-market-migrate \
  --image=me-central1-docker.pkg.dev/value-market/value-market/value-market:latest \
  --region=me-central1 \
  --set-env-vars="..." --set-secrets="..." \
  --add-cloudsql-instances=value-market:me-central1:value-market-db \
  --command="php" --args="artisan,migrate,--force" \
  --project=value-market

gcloud run jobs execute value-market-migrate --region=me-central1 --project=value-market
```

(Same env vars/secrets as the service deploy in §7.) Re-create this Job with a fresh `--image` before each
execution that needs to run against the newly-deployed code's migrations, or update it in place with
`gcloud run jobs update`.

## 10. Storage

`config/filesystems.php`'s default is `local` (`storage/app/`) — files land on the container's own
filesystem. **Cloud Run's filesystem is ephemeral and not shared across instances**: an uploaded file
written by one instance is invisible to any other instance, and everything is lost whenever an instance is
recycled (which Cloud Run does routinely, not just on deploys). This application already supports S3 as an
alternate disk (`aws/aws-sdk-php`, `league/flysystem-aws-s3-v3` in composer.json; sellers/admins can already
pick `s3` as their storage disk per the app's own `StorageType` model) — setting `FILESYSTEM_DISK=s3` (§7)
with a real S3-compatible bucket (AWS S3, or Google Cloud Storage via its S3-compatibility mode) is the
correct fix, not a redesign. This is **not** implemented as part of this deployment task — it's a required
follow-up before real user uploads go live on Cloud Run, called out explicitly here rather than silently
assumed to be fine.

## 11. Queues and the scheduler

`config/queue.php`'s default is `sync` (`QUEUE_CONNECTION=sync` in `.env.example`) — jobs run inline, no
separate worker process is required for the app to function today. If `QUEUE_CONNECTION` is changed to
`database`/`redis` in the future, a worker needs to run somewhere Cloud Run's request-driven model doesn't
provide by itself — a separate Cloud Run service running `php artisan queue:work` (with `--min-instances=1`,
since a scaled-to-zero worker never processes anything) or a Cloud Run Job triggered periodically.

`app/Console/Kernel.php` schedules two commands (`sitemap:generate` daily, `cart:send-reminders` daily at
09:00) via Laravel's Task Scheduler, which itself needs `php artisan schedule:run` invoked every minute by
something external — traditionally a system cron entry, which a Cloud Run *service* container does not
provide (it only runs while handling a request). The correct Cloud Run equivalent is Cloud Scheduler calling
a Cloud Run Job (or an authenticated HTTP endpoint) once a minute:

```bash
gcloud run jobs create value-market-schedule \
  --image=me-central1-docker.pkg.dev/value-market/value-market/value-market:latest \
  --region=me-central1 \
  --set-env-vars="..." --set-secrets="..." \
  --add-cloudsql-instances=value-market:me-central1:value-market-db \
  --command="php" --args="artisan,schedule:run" \
  --project=value-market

gcloud scheduler jobs create http value-market-schedule-trigger \
  --schedule="* * * * *" \
  --uri="https://REGION-run.googleapis.com/apis/run.googleapis.com/v1/namespaces/PROJECT_ID/jobs/value-market-schedule:run" \
  --http-method=POST \
  --oauth-service-account-email=<a service account with the Cloud Run Invoker role> \
  --project=value-market
```

Not implemented as part of this task (Task 14 explicitly says not to implement workers unless required
simply to boot the web service, which this isn't) — documented here as what's needed before those two
scheduled commands run in production on Cloud Run.

## 12. Custom domain and SSL

```bash
gcloud run domain-mappings create \
  --service=value-market \
  --domain=your-domain.example \
  --region=me-central1 \
  --project=value-market
```

Cloud Run provisions and renews the TLS certificate automatically once the domain mapping's DNS records
(shown by the command above) are added at your DNS provider. No certificate files are needed in the image
or repository.

## 13. Rollback

Every deploy creates a new, immutable revision. To roll back instantly without rebuilding:

```bash
gcloud run revisions list --service=value-market --region=me-central1 --project=value-market
gcloud run services update-traffic value-market \
  --to-revisions=<previous-revision-name>=100 \
  --region=me-central1 --project=value-market
```

Database migrations are **not** automatically rolled back by this — if the revision being rolled back to
predates a migration, that migration's `down()` needs to be run manually and deliberately, the same
discipline as any other production migration rollback.

## 14. Logs

```bash
gcloud run services logs read value-market --region=me-central1 --project=value-market --limit=100
```

Or the Cloud Logging console, filtered to `resource.type="cloud_run_revision" AND resource.labels.service_name="value-market"`.
With `LOG_CHANNEL=stderr` (§7), Laravel's own application logs (`Log::info()`, exception traces, etc.) appear
here alongside Apache's access/error logs (the Dockerfile points both at `/proc/self/fd/1`/`2`).

## 15. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Container fails to start / Cloud Run reports "container failed to start and listen on the port" | `PORT` substitution didn't apply, or Apache failed to start for an unrelated reason | Check `gcloud run services logs read` for the actual Apache/PHP error; confirm `docker/entrypoint.sh` ran (it's the image's `ENTRYPOINT`) |
| 500 error on every request, logs show a DB connection error | `DB_HOST`/Cloud SQL socket misconfigured, or `--add-cloudsql-instances` missing from the deploy | Re-check §7's exact `DB_HOST=/cloudsql/<connection-name>` value and that `--add-cloudsql-instances` matches it |
| "No application encryption key has been specified" | `APP_KEY` secret not set or not bound | Confirm the secret exists (§5) and `--set-secrets` includes `APP_KEY=...` |
| Users get logged out randomly / cache seems inconsistent | `SESSION_DRIVER`/`CACHE_DRIVER` left as `file` with more than one active instance | Switch to `database` or Redis per §7 |
| Uploaded images/documents disappear after a while | `FILESYSTEM_DISK=local` on Cloud Run's ephemeral filesystem | Switch to `s3` per §10 |
| Scheduled emails/sitemap generation never run | No Cloud Scheduler wired up | Follow §11 |
| `npm run build` / asset step fails during a fresh `gcloud builds submit` | A dependency version conflict, or a new missing Vite entry — see `docs/CLOUD_RUN_READINESS_REPORT.md` for the one already found and fixed | Reproduce locally with `npm ci --legacy-peer-deps && npm run build` and read the actual Rollup/Vite error |
| GD/Imagick-related fatal error on an image-processing request | An extension failed to load at container start | `docker exec` (or `gcloud run services proxy` + local repro) and run `php -m` to confirm `gd`/`imagick` are listed; see the Dockerfile's extension-install step |
