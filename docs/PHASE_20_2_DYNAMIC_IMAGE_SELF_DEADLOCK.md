# Phase 20.2 — dynamic_image() self-deadlock was blocking the entire dashboard

## Context

Follow-up to `docs/PHASE_20_DASHBOARD_RENDERING_BUG.md`'s other flagged item: "Revenue Analytics and Customer
Statistics charts don't render - ApexCharts never loads... likely a bundling/architecture fix, bigger than
this phase." That guess was wrong too (like the Orders Overview numbers guess in
`docs/PHASE_20_1_STORE_SCOPE_FALLBACK.md`) - investigated properly this time instead of leaving it flagged.

## Root cause

Captured every in-flight network request during a real page load and found something stranger than "slow
loading": every request that started after a certain point - roughly 50 separate `<script src>` requests,
several `fonts.googleapis.com` requests, and two `/admin/media/image?...` requests - all sat "pending" with
the exact same start timestamp and never completed, no matter how long the test waited (tried up to 45s).
That's not consistent with slow sequential loading; it's consistent with the server itself being stuck.

`php artisan serve` runs PHP's built-in development server, which handles one request at a time (no worker
pool). Testing the `/admin/media/image` endpoint directly (`curl`) confirmed it hangs forever - never returns,
never times out on its own. Reading `Admin\MediaController::dynamic_image()` (and the near-identical
`Seller\MediaController::dynamic_image()`) explained why: it decided "is this image local or remote" by
string-matching the request's `url` parameter against `config('app.url')`. This app's `.env` has
`APP_URL=http://localhost`, but `asset()` (used everywhere images are linked) builds URLs from the actual
request host - `http://127.0.0.1:8123` when running via `artisan serve` on that port, or whatever host a
real deployment is actually reached at. Those two essentially never matched. When they didn't,
`dynamic_image()` fell through to `file_get_contents($url)` as a "remote" fetch - for an image that was, in
fact, sitting right there on the same server's local disk.

Under `php artisan serve`'s one-request-at-a-time server, that's a guaranteed deadlock: the single worker
handling the `dynamic_image` request blocks on `file_get_contents()` waiting for an HTTP response from
`127.0.0.1:8123` - but the only thing that could ever answer that request is the very same worker, which is
already busy waiting. Neither side can proceed. Every other request queued up behind it (that's why 50+
unrelated script requests all appeared to start-and-freeze at once - they weren't actually being processed at
all; the server was still stuck on the very first request in Chrome's fetch order that happened to hit this
endpoint), which is what made ApexCharts (and jQuery UI plugins, and everything else loaded via
`admin.include_script.blade.php`) never actually finish loading, however long the wait.

This also explains why the fix landed in `docs/PHASE_20_DASHBOARD_RENDERING_BUG.md` for the jQuery-CDN
ordering issue didn't fully resolve it either - that fix made `$`/`jQuery` available early and correctly, but
the dashboard's own `<img>` tags (`MediaService::getImageUrl()`/`dynamic_image()` calls in the sidebar,
header, Top Sellers list, etc.) still triggered this same endpoint and the same server-wide stall.

**Does this reproduce outside `artisan serve`?** In production (php-fpm with multiple workers), a single
`dynamic_image()` call resolving as "remote" wouldn't deadlock the whole server - a different worker answers
the self-request. But it's still a real, avoidable problem there too: every locally-hosted image resize pays
a full unnecessary HTTP round trip instead of a local disk read, and under enough concurrent traffic
(imagine a page with a dozen `dynamic_image()`-backed thumbnails, each one holding a worker hostage waiting on
another worker) this is exactly the shape of a self-inflicted worker-pool exhaustion / soft DoS. Any
deployment where the actual serving host doesn't textually match `APP_URL` - behind a load balancer, a CDN, a
different scheme (http vs https), a bare IP vs a domain, or simply `APP_URL` never having been updated to
match production - hits the "always remote" branch for literally every image, all the time.

## Fix

`Admin\MediaController::dynamic_image()` and `Seller\MediaController::dynamic_image()`: instead of comparing
the URL string to `config('app.url')`, resolve the URL's own path (`parse_url($url, PHP_URL_PATH)`) against
`public_path()` and check whether a real file exists there via `realpath()`. This doesn't care what
scheme/host/port the URL string happens to use - if the path corresponds to a real file under `public/`, it's
read directly off disk (fast, no network round trip, no self-deadlock risk). Only URLs that don't resolve to
a real local file fall through to an actual remote fetch, gated by the pre-existing domain/AWS-bucket check
(also tightened: an empty `AWS_BUCKET` env value no longer neutralizes that check entirely, which it
previously did - `strpos($url, '') === false` is always `false`, i.e. never restricted).

**Path traversal**: since this resolves an attacker-controlled URL's path against the local filesystem,
`realpath()` is used specifically because it both resolves any `../` traversal and returns `false` for a
path that doesn't exist. The resolved path is additionally checked to actually start with the public
directory's own real path before ever being treated as "local" - this is a public, unauthenticated-reachable
endpoint (`routes/web.php`'s `/media/image` alias has no auth middleware), so it must never be able to read
or leak a file (`.env`, `storage/`, etc.) from outside the public webroot, even via a crafted `url` parameter
or a symlink. Verified: a `../../../.env` traversal attempt and an `/etc/passwd` attempt both correctly fall
through to "Domain is restricted" rather than reading anything.

## Verification

- `curl` against `/admin/media/image?url=...&width=110&quality=90` for a real local image: was hanging
  indefinitely (tested up to 15s with no response); now responds in ~0.08s with the real resized image.
- Two path-traversal attempts (`../../../.env`, `/etc/passwd`) both correctly rejected with "Domain is
  restricted", nothing leaked.
- Real headless-browser screenshot of `/admin/home`: Revenue Analytics (a real ApexCharts line chart with
  Sales/Revenue/Commission series) and Customer Statistics (a real ApexCharts donut chart) both render
  correctly now, no hang. Combined with `docs/PHASE_20_1_STORE_SCOPE_FALLBACK.md`'s fix, every number on the
  dashboard is now internally consistent (Sellers: 12, Orders: 25000, Products: 600, Orders Overview's
  Delivered/Cancelled/Returned all in the same few-thousand range as the 25,000 total - not the previous
  "0 sellers but 29,646 orders" contradiction).
- Full suite: 386/386 passing (no regressions; no new automated test added here, since the bug is a live-
  server request-handling deadlock under `php artisan serve` specifically - PHPUnit's HTTP test client
  doesn't go through a real socket/server and so can't reproduce or verify this class of bug).

## What this did not touch

`Admin\MediaController::dynamic_image_old()` has the same "always fetch by URL" shape as the pre-fix
`Seller\MediaController::dynamic_image()` - but it isn't registered on any route (grepped), so it's dead code
already and was left alone rather than fixed blind.
