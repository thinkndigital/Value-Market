# Queue Architecture

Changelog v1.0.10 ("Queue integration", "Faster order processing / Better UX during high traffic").

## What was found

Before this pass, `app/Jobs` didn't exist and no `ShouldQueue` implementation was found anywhere in this
codebase. `QUEUE_CONNECTION` was mentioned in `docs/CLOUD_RUN_DEPLOYMENT.md` as something a production
deploy *could* set to `database`, but nothing in the application actually dispatched a job to it — the
setting was a no-op either way. Heavy operations (order-confirmation email + PDF invoice generation being
the clearest example) ran synchronously, inline, inside the request that triggered them.

## Design constraint: no permanently-running worker

Cloud Run's default service model is request-driven and scales to zero — a container that isn't actively
handling an HTTP request can be stopped at any time, and a fresh instance never "remembers" to keep running
in the background. **`php artisan queue:work` as a long-lived process is not something a normal Cloud Run
*service* provides.** Two ways to get a real persistent worker anyway both require running a *second*,
always-on Cloud Run service with `--min-instances=1` (or an equivalent VM) — extra infrastructure, extra
cost, and exactly the kind of standing worker this feature was told explicitly not to assume.

Instead, this app reuses a pattern it already has for the same underlying problem: `CronJobController`
already exposes `settleCashbackDiscount`/`sendCartReminders` as plain HTTP GET endpoints, protected by a
shared-secret middleware (`verify_cron_secret` / `App\Http\Middleware\VerifyCronSecret`), meant to be hit by
an external scheduler (Cloud Scheduler in production; any cron in a traditional VM/hosting setup) rather than
a real logged-in user. Queue draining follows the identical shape:

```
Cloud Scheduler (or any cron) --GET--> /admin/cronjob/processQueue?cron_secret=...
                                              |
                                              v
                                   Artisan::call('queue:work', [
                                       '--stop-when-empty' => true,
                                       '--max-time' => 50,
                                       '--tries' => 3,
                                   ])
                                              |
                                              v
                                   drains the `jobs` table, one bounded run
```

`--stop-when-empty` makes the worker process exits as soon as the table is empty (never idles forever inside
the HTTP request); `--max-time=50` is a hard ceiling so a large backlog can't hold the request open past
Cloud Run's own timeout — any jobs left over just wait for the *next* scheduled hit. There is no dependency on
any process surviving between requests: each hit is a fresh, bounded worker run inside an ordinary request/
response cycle, which is exactly what Cloud Run *does* support natively.

## Local / non-Cloud-Run environments

Nothing about this design requires the HTTP-drain endpoint outside Cloud Run. On a traditional VM or any host
where a persistent process is cheap, `QUEUE_CONNECTION=database` plus a real `php artisan queue:work` daemon
(supervisor/systemd) works exactly as Laravel intends — the `processQueue` endpoint and a real worker can
both drain the same `jobs` table safely; whichever gets there first processes a given job.

This app's own default (`.env.example`, `phpunit.xml`) is `QUEUE_CONNECTION=sync`: `dispatch()` runs the job
immediately, in-process, with no queue table involved at all. This is deliberate, not an oversight — every
job in `app/Jobs` is written to behave identically whether it runs synchronously (via `sync`) or asynchronously
(via `database` + a worker/drain), so local development and this test suite need zero extra setup, while
production can opt into real deferral by changing one env var.

## What's actually queued

### `App\Jobs\SendOrderConfirmationEmailJob`

`OrderService::placeOrder()` used to generate the invoice PDF (`OrderController::generatInvoicePDF()`) and
send the confirmation email inline, inside the checkout request — the single slowest part of an already-heavy
request path, and exactly the kind of operation the changelog's "faster order processing" item calls out.
Moved into this job:

- Constructed with only the order id (`SerializesModels`-friendly, and cheap to serialize onto the `jobs`
  table when queued for real).
- `handle()` re-derives everything else (customer email, app name, order data) from the database, since a
  queued job runs in a separate process/request with no access to `placeOrder()`'s local variables.
- Every step that can legitimately fail (missing email, email not configured, PDF generation, SMTP) is a
  silent no-op or a caught-and-logged failure — never an exception that propagates back to whoever dispatched
  it. This matters most under `QUEUE_CONNECTION=sync`: since the job then runs synchronously in the same
  request as `dispatch()`, an uncaught exception here would still crash order placement, the exact bug this
  code originally had before being extracted into a job.
- `public $tries = 3` and a `failed()` handler that logs, for the real-queue (non-`sync`) case.

### The `processQueue` cron endpoint

`Admin\CronJobController::processQueue()`, routed at `GET admin/cronjob/processQueue`, guarded by the same
`demo_restriction` + `verify_cron_secret` middleware pair as the two existing cron routes. Calling it runs one
bounded `queue:work --stop-when-empty` pass and returns Artisan's own output as JSON — useful for confirming a
scheduled hit actually processed something, without needing shell access to the container.

## Adding a new queued job

1. `php artisan make:job SomeJob` (or copy `SendOrderConfirmationEmailJob`'s shape by hand — no queue
   connection is required to create the file).
2. Implement `ShouldQueue`; write `handle()` so it only depends on data it re-fetches itself (ids passed into
   the constructor), never on caller-local state — the job may run in a different process entirely.
3. Wrap anything that can throw for reasons outside the job's control (network calls, third-party APIs) in a
   try/catch that logs instead of rethrows, exactly like `SendOrderConfirmationEmailJob` — this is what keeps
   `QUEUE_CONNECTION=sync` safe for the caller.
4. `dispatch(new SomeJob(...))` from wherever the slow operation used to run inline.
5. No route/scheduler change needed — `processQueue` already drains every queue on the `database` connection,
   not just this one job's.

## Production configuration checklist

- Set `QUEUE_CONNECTION=database` in the Cloud Run service's env vars (`docs/CLOUD_RUN_DEPLOYMENT.md` §7
  already lists this).
- Set `CRON_SECRET` (Secret Manager, same as the other cron secrets already documented) — required for
  `verify_cron_secret` to accept any request at all; an unconfigured secret fails closed (403), never open.
- Add a Cloud Scheduler job hitting `https://<service-url>/admin/cronjob/processQueue?cron_secret=<secret>`
  on a short interval (every 1–2 minutes is reasonable for email-weight jobs; adjust per actual queue depth).
  This can reuse the exact same Cloud Scheduler setup `docs/CLOUD_RUN_DEPLOYMENT.md` §11 already documents
  for the Task Scheduler (`schedule:run`) — a second scheduler job pointed at a different URL, no new
  infrastructure concept.
- The `jobs`/`failed_jobs` tables already exist in this schema
  (`database/migrations/2025_01_01_000015_baseline_jobs_table.php`,
  `2025_01_01_000012_baseline_media_infra.php`) — no additional migration needed to turn this on.
