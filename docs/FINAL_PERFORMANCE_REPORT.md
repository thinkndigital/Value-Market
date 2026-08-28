# /admin/home — Final Performance Report

Consolidates Phase 18 (`docs/PHASE_18_PERFORMANCE_ADMIN_HOME.md`) and Phase 19
(`docs/PHASE_19_ADMIN_HOME_QUERY_PROFILING.md`), plus this closing pass: Cloud Run configuration review,
cold/warm measurement, log review, a final regression pass, and the production-readiness call.

**No Laravel code was changed in this pass.** This environment has no `gcloud` CLI and no GCP credentials
(verified: `which gcloud` → not found, `gcloud auth list` → command not found) - sections 9-13 below state
plainly what could and could not be measured, with no invented numbers anywhere.

---

## 1. المشكلة الأصلية

`/admin/home` كان بطيء. القياس الفعلي (مو تخمين) أثبت:
- Phase 18: 26 استعلام قاعدة بيانات لكل تحميل صفحة، بسبب استعلامات متكررة داخل loop وindexes مفقودة.
- Phase 19: حتى بعد تخفيضها لـ14-16، القياس الحقيقي (شامل الـ Blade render، اللي Phase 18 ما كانت تقيسه) كشف
  إنه الصفحة كانت تشغّل **83 استعلام** فعلياً لكل تحميل، وإنه الجزء الأبطأ ما كان الكونترولر - كان الـ
  **render نفسه** (54 استعلام، أكتر من الكونترولر).

## 2. السبب الحقيقي

سببين مختلفين، الاثنين مثبتين بالقياس المباشر (`DB::listen()` + `EXPLAIN`)، مو تخمين:

1. **`home.blade.php` كانت تستدعي `OrderService::ordersCount()` 24 مرة مباشرة جوا الـ template** - كل حالة
   طلب (received/processed/shipped/delivered/cancelled/returned) تُستعلم 2-5 مرات، بالإضافة لاستعلام "كل
   الحالات" يتكرر 6 مرات متطابقة تماماً. هاد وحده كان أكبر مصدر وقت بالصفحة كلها.
2. **`top_sellers` كانت تسحب كل صفوف `order_items` لكل بائع** (بدون حد أقصى، بدون فلترة store_id أو تاريخ)
   وتجمعها بلغة PHP (`Collection::sum()`) بدل قاعدة البيانات.

بالإضافة لـ Phase 18's الأصلية: استعلام متكرر جوا loop، وindexes مفقودة تماماً على `store_id`/`role_id`.

## 3. جميع الإصلاحات

| # | المشكلة | الملف | الإصلاح |
|---|---|---|---|
| 1 | `getWeeklySalesData()` يُستدعى 7 مرات متطابقة جوا loop | `HomeController.php` | نُقل بره الـ loop، يُستدعى مرة وحدة (Phase 18) |
| 2 | `getMonthlyData()` 3 استعلامات منفصلة | `HomeController.php` | دُمجت بـ`getMonthlyDataCombined()` استعلام واحد (Phase 18) |
| 3 | `countNewUsers()` 5 استعلامات | `app/function_helper.php` | استعلام واحد بـ`CASE WHEN` (Phase 18) |
| 4 | `store_id`/`role_id` بدون index | migration جديد | 4 indexes (Phase 18) |
| 5 | `ordersCount()` 24 استدعاء جوا الـ template | `HomeController.php` + `home.blade.php` | حساب مرة وحدة (7 بدل 24)، الـ view يقرأ من array (Phase 19) |
| 6 | `top_sellers` جمع بلغة PHP | `HomeController.php` | استعلام SQL واحد `GROUP BY seller_id` (Phase 19) |
| 7 | استعلامات مالية full table scan | migration جديد | covering index `(store_id, created_at, sub_total, admin_commission_amount, quantity)`، حذف القديم الزائد (Phase 19) |
| 8 | `ordersCount()` full table scan | migration جديد | index `(store_id, active_status(50), order_id)` (Phase 19) |

## 4. Query Reduction

| المرحلة | عدد الاستعلامات |
|---|---:|
| الأصلي (قبل أي إصلاح) | 26 (مقاسة بكونترولر فقط - Phase 18's baseline) |
| بعد Phase 18 | 14-16 (كونترولر فقط - الـ render ما كانت تُقاس) |
| **الحقيقي الكامل قبل Phase 19** (كونترولر + render + middleware) | **83** |
| **بعد Phase 19** (كونترولر + render + middleware) | **66** |

## 5. Render Optimization

قبل Phase 19: الـ Blade render كانت تشغّل 54 استعلام (669ms). بعدها: 30 استعلام (~25-28ms). الفرق (24
استعلام) هو بالضبط استدعاءات `ordersCount()` اللي انتقلت للكونترولر (تُحسب مرة وحدة الآن) - الـ 30 المتبقية
كلها من الـ layout/view-composer المشتركة (permissions، roles، stores، languages) وموجودة بكل صفحة أدمن، مو
خاصة بـ`/admin/home`.

## 6. Database Optimization

مجموع وقت قاعدة البيانات (كل المراحل): **~824ms → ~165-190ms** (قياس على نفس الداتا: 145,589 صف order_items
موزعة على 3 متاجر، المتجر المقاس ياخذ ~17% من الجدول).

## 7. Indexes المُضافة + دليل EXPLAIN

كل index تم التحقق منه بـ`EXPLAIN` **قبل** كتابة الـ migration - وindex مرشح تم رفضه لأن EXPLAIN ما أثبت فائدة:

| Migration | Index | قبل (EXPLAIN) | بعد (EXPLAIN) |
|---|---|---|---|
| `2025_02_14_...add_admin_home_performance_indexes.php` | `order_items(store_id, created_at)`, `orders(store_id)`, `combo_products(store_id)`, `users(role_id)` | `type: ALL` على الأربعة | `type: ref`/`range` |
| `2025_02_15_...add_order_items_financial_covering_index.php` | `order_items(store_id, created_at, sub_total, admin_commission_amount, quantity)` - يستبدل القديم | `type: ALL`, 143,589 صف (`AdmintotalEarnings`/`getMonthlyDataCombined`/`getWeeklySalesData`) | `type: ref`, `Using index` (بدون رجوع للجدول) |
| `2025_02_16_...add_order_items_status_count_index.php` | `order_items(store_id, active_status(50), order_id)` | `type: ALL`, 143,796 صف (`ordersCount()`) | `type: ref`, ~12,682-52,774 صف |
| **مرفوض** - `(seller_id, active_status(50), sub_total, seller_commission_amount)` لـ`top_sellers` | - | تم اختباره مباشرة بـSQL، MySQL استمر يستخدم index قديم رغم وجود المرشح - **لم يُضف** |

## 8. الاختبارات

`php artisan test` (آخر تشغيل، اليوم): **360 نجح، 0 فشل** (652 اختبار في `docs/PHASE_18/19` كانت مبنية على
352، زاد العدد بسبب 8 اختبارات إضافية غير مرتبطة بالأداء - ثغرة أمنية حرجة اكتُشفت وأُصلحت بين المرحلتين،
موثقة بـ`docs/SECURITY_AUDIT.md §6.5`، غير مرتبطة بهاد الملف). التفاصيل الكاملة بقسم 18 تحت (المراجعة
النهائية للكود).

---

## 9. Cloud Run — Current Configuration

**No live access from this environment** (no `gcloud` CLI, no credentials) - I am not claiming to have
inspected a running service. What follows is what this **repository's own deployment documentation**
(`cloudbuild.yaml`, `docs/CLOUD_RUN_DEPLOYMENT.md`) specifies as the *intended* configuration for the first
deploy - not a confirmed live state. `docs/CLOUD_RUN_DEPLOYMENT.md` itself notes its own build was "not yet
re-verified against an actual Cloud Build run" as of the last deployment pass.

| Setting | Documented value | Source |
|---|---|---|
| Service name | `value-market` | `cloudbuild.yaml` (`_SERVICE_NAME`) |
| Region | `me-central1` | `cloudbuild.yaml` (`_REGION`), matches this task's stated region |
| Project ID | `value-market` (assumed - `docs/CLOUD_RUN_DEPLOYMENT.md` itself says "adjust if different") | `docs/CLOUD_RUN_DEPLOYMENT.md` §1 |
| CPU | **Not explicitly set** in the documented `gcloud run deploy` command → Cloud Run platform default (1 vCPU) applies if deployed as documented | inferred from absence in §7's command |
| Memory | **Not explicitly set** → platform default (512 MiB) applies if deployed as documented | same |
| Concurrency | **Not explicitly set** → platform default (80) applies if deployed as documented | same |
| Request timeout | **Not explicitly set** → platform default (300s) applies if deployed as documented | same |
| Startup CPU boost | **Not explicitly set** → off by default if deployed as documented | same |
| Min instances | `0` (explicit `--min-instances=0`) | `docs/CLOUD_RUN_DEPLOYMENT.md` §7, §8 |
| Max instances | **Not explicitly set** → platform default (100) applies if deployed as documented | same |
| Port | `8080` | `docs/CLOUD_RUN_DEPLOYMENT.md` §7 |
| Authentication | Public (`--allow-unauthenticated`) | same |

**None of the CPU/memory/concurrency/timeout/startup-boost rows above are a confirmed live value** - they
are what happens if the documented command was run with no further overrides. The actual live service may
differ if it was deployed differently or reconfigured since. Confirm with the commands in §10 before relying
on any of this for a capacity decision.

## 10. Ready-to-Run gcloud Commands

Copy-paste, adjusting `PROJECT_ID` if your project differs from the documented `value-market` assumption.
Service name/region are `value-market`/`me-central1` per §9 - if that's wrong for your actual deployment,
substitute your real values.

```bash
# --- Service configuration (full YAML - CPU, memory, concurrency, timeout, startup boost, env vars, all of it) ---
gcloud run services describe value-market \
  --region=me-central1 \
  --project=YOUR_PROJECT_ID \
  --format=yaml

# --- Just the fields this report needs, formatted for a quick read ---
gcloud run services describe value-market \
  --region=me-central1 \
  --project=YOUR_PROJECT_ID \
  --format="value(
    spec.template.spec.containers[0].resources.limits.cpu,
    spec.template.spec.containers[0].resources.limits.memory,
    spec.template.spec.containerConcurrency,
    spec.template.spec.timeoutSeconds,
    spec.template.metadata.annotations['run.googleapis.com/startup-cpu-boost'],
    spec.template.metadata.annotations['autoscaling.knative.dev/minScale'],
    spec.template.metadata.annotations['autoscaling.knative.dev/maxScale']
  )"

# --- Current serving revision ---
gcloud run services describe value-market \
  --region=me-central1 \
  --project=YOUR_PROJECT_ID \
  --format="value(status.latestReadyRevisionName, status.traffic)"

# --- Recent revisions (history) ---
gcloud run revisions list \
  --service=value-market \
  --region=me-central1 \
  --project=YOUR_PROJECT_ID \
  --limit=10

# --- Traffic allocation across revisions ---
gcloud run services describe value-market \
  --region=me-central1 \
  --project=YOUR_PROJECT_ID \
  --format="table(status.traffic[].revisionName, status.traffic[].percent)"

# --- Container startup time for the latest revision (look for "Started container" / readiness probe timing) ---
gcloud logging read \
  'resource.type="cloud_run_revision" AND resource.labels.service_name="value-market" AND textPayload:"Started"' \
  --project=YOUR_PROJECT_ID --limit=20 --format=json
```

## 11. Suggested Configuration (NOT applied)

A conservative starting point, evaluated but **not changed** - nothing here was applied, and none of it
should be applied without first running §10's commands against the real service and confirming current
values.

| Setting | Suggested | Problem it solves | Expected impact | Cost impact |
|---|---|---|---|---|
| `min-instances` | `1` (up from documented `0`) | Cold starts: `min-instances=0` means every request after an idle period pays full container boot + PHP/Apache startup + Laravel bootstrap on top of the ~165-190ms measured in this report. A public admin dashboard used throughout business hours is a reasonable case for eliminating that. | Removes cold-start latency for the vast majority of real requests | One container billed continuously instead of scale-to-zero - real, ongoing cost. Only worth it if traffic doesn't already keep an instance warm naturally; check §10's revision/traffic history for actual request frequency before deciding. |
| CPU | `1` (keep the platform default) | Nothing measured in this report points at CPU being a bottleneck - the dominant cost was database round-trips (§6), not compute-bound PHP work | No expected change | None if left at default |
| Memory | Platform default (512 MiB) unless real OOM/high-memory-usage is observed in Cloud Run metrics (unmeasurable from here - see §13) | Nothing measured here indicates a memory problem - if anything, this pass's `top_sellers` fix (§3, item 6) *reduces* peak memory (no longer loading thousands of `order_items` rows into a PHP collection) | Possible reduction, unconfirmed without live metrics | None if left at default |
| Concurrency | Platform default (80) unless Cloud Run metrics (§13) show request queuing under load | Not evaluated - no load-test data from this environment | Unknown | None if left at default |
| Startup CPU boost | Worth enabling (`--cpu-boost`) alongside `min-instances=1` above | Reduces the time a cold container takes to become ready to serve, which matters most when `min-instances=0` or during scale-up under a traffic spike | Faster cold starts specifically (complements, doesn't replace, the min-instances fix) | Startup CPU boost costs are typically negligible next to running an extra always-on instance - the min-instances change dominates the cost delta |

**Nothing here is applied.** These are default-conservative options informed by what the Laravel/PHP/MySQL
layer alone can tell us (§4-6); the request-serving-layer half of this decision (§12-13) could not be
measured from this environment and should be checked before spending money on any of the above.

## 12. Cold Start vs Warm Request

**Not measurable from current environment.** This requires an HTTP request against the actual deployed
Cloud Run URL; this environment has no network path to it and no confirmed URL to target. What *is*
separated and already measured (§4-6, and `docs/PHASE_19_ADMIN_HOME_QUERY_PROFILING.md` in full) is the
breakdown *within* a single warm PHP process: bootstrap / middleware / controller / database / Blade render
- none of that includes container cold-start time, which only exists at the Cloud Run request-serving layer,
outside what this environment can reach.

To get this yourself:

```bash
# Cold: force a fresh container by hitting right after a deploy or a long idle period
time curl -o /dev/null -s -w "%{time_total}\n" https://YOUR_CLOUD_RUN_URL/admin/home

# Warm: immediately repeat the same request 5x
for i in 1 2 3 4 5; do
  curl -o /dev/null -s -w "%{time_total}\n" https://YOUR_CLOUD_RUN_URL/admin/home
done
```

(Needs a valid admin session cookie to reach `/admin/home` past its `auth` middleware - either `curl -b`
with a real cookie, or measure against a public, unauthenticated route for a rougher cold-vs-warm signal.)

## 13. Cloud Run Logs

**Not measurable from current environment** - no `gcloud`/Cloud Logging access. What to check once you have
it:

```bash
# Recent errors/5xx
gcloud logging read \
  'resource.type="cloud_run_revision" AND resource.labels.service_name="value-market" AND severity>=ERROR' \
  --project=YOUR_PROJECT_ID --limit=50 --format=json

# Request latency, status codes (Cloud Run's own request logs)
gcloud logging read \
  'resource.type="cloud_run_revision" AND resource.labels.service_name="value-market" AND httpRequest.status>=500' \
  --project=YOUR_PROJECT_ID --limit=50 --format=json

# Or the equivalent read command from docs/CLOUD_RUN_DEPLOYMENT.md §14:
gcloud run services logs read value-market --region=me-central1 --project=YOUR_PROJECT_ID --limit=100
```

Look specifically for: 502/503 (container crashed or failed to start in time), request timeouts (>300s
default), memory-limit kills, and CPU throttling warnings - any of these would point at a bottleneck outside
Laravel (the request-serving/container layer), which nothing measured in this report rules out or confirms.

## 14. Production Baseline

| Metric | Before Optimization | After Optimization |
| --- | ---: | ---: |
| Laravel queries (full page load: bootstrap+middleware+controller+render) | 83 | 66 |
| Laravel internal response (reconstructed wall time) | ~1109 ms | ~204-228 ms (~5.4x faster) |
| Database time | ~824 ms | ~165-190 ms |
| Cloud Run warm latency | `Not measurable from current environment` | `Not measurable from current environment` |
| Cloud Run cold latency | `Not measurable from current environment` | `Not measurable from current environment` |
| HTTP errors (5xx/502/503) | `Not measurable from current environment` | `Not measurable from current environment` |

## 15. Browser / User Experience (TTFB)

**Not measurable from current environment** - no network access to a live URL to separate TTFB from
Laravel processing time. To get this yourself, from a machine with network access to the deployed service:

```bash
curl -o /dev/null -s -w "DNS: %{time_namelookup}s | Connect: %{time_connect}s | TLS: %{time_appconnect}s | TTFB: %{time_starttransfer}s | Total: %{time_total}s\n" \
  https://YOUR_CLOUD_RUN_URL/admin/home
```

`TTFB - Laravel-internal-time-from-this-report` gives an estimate of network + container + Apache/PHP-FPM
overhead sitting on top of what's measured here (with the caveat that the two aren't measured from the same
process, so this is an estimate, not an exact decomposition).

---

## 16. Final Code Review (post-measurement)

Every item the task asked to check, reviewed against the actual Phase 19 diff (`git show
7b41cc2 -- app/Http/Controllers/Admin/HomeController.php resources/views/admin/pages/forms/home.blade.php`)
and the two new migrations:

| Check | Finding |
|---|---|
| N+1 queries | None introduced. `top_sellers`' new aggregate is a single `GROUP BY seller_id` query, joined against the already-loaded seller collection in memory (no per-seller query in the `->map()`). |
| Memory increase | None - if anything, memory usage *decreased*: `top_sellers` no longer loads every matching `order_items` row into a PHP collection, only one aggregated row per seller. |
| Incorrect results | Checked by test, not by inspection alone: `tests/Feature/Phase19/AdminHomeQueryProfilingTest.php` proves `orders_status_counts` matches direct `ordersCount()` calls per status, and `top_sellers` totals match the original PHP-side sum semantics exactly (including the asymmetric filtering - `total_sales` only counts `delivered`, `total_commission` sums every status - preserved deliberately). |
| Race conditions | None - every changed query is a read (`SELECT`); no write/update logic was touched. |
| Cache bugs | Not applicable - no caching (`Cache::remember()` or similar) was introduced in this phase. |
| Pagination bugs | Not touched - `/admin/home` has no pagination. |
| SQL compatibility | Both new indexes use MySQL/MariaDB-specific prefix-length syntax (`active_status(50)`), matching the project's existing convention (`order_items_active_status_prefix_index`, Phase 2) and the project's own stated position that only MySQL/MariaDB is targeted (`docs/CLOUD_RUN_DEPLOYMENT.md` §Prerequisites: "PostgreSQL support exists in Laravel's config but is not what this application's migrations/queries actually use"). Both migrations verified with a full `up()` → `down()` → `up()` cycle and a clean `migrate:fresh` on the test database. |
| Full test suite | `php artisan test`: **360 passed, 0 failed** (re-run today, current HEAD). |

No regressions found. No code changes were made in this closing pass.

## 17. What Was Not Touched, and Why

Per this task's own instruction: Dockerfile, Apache config, OPcache settings, Laravel architecture, routes,
UI, and business logic were **not touched** in this closing pass. Nothing measured in §4-8 or §16 points at
any of them as a bottleneck - the measured bottleneck was entirely within the database query layer (§6),
already fixed. Whether the request-serving layer (Apache/PHP-FPM/container) has its own separate bottleneck
is explicitly **unknown** from this environment (§12-13) - not ruled out, not confirmed, genuinely
unmeasured. That's the one open question left for whoever has live Cloud Run access.

## 18. Deployment Commands

```bash
# Build and push (or let a Cloud Build trigger do this automatically on push to main)
gcloud builds submit --config=cloudbuild.yaml --project=YOUR_PROJECT_ID

# Manual deploy of a specific already-pushed image tag (cloudbuild.yaml's `deploy` step does this
# automatically after a successful build/push)
gcloud run deploy value-market \
  --image=me-central1-docker.pkg.dev/YOUR_PROJECT_ID/value-market/value-market:latest \
  --region=me-central1 \
  --platform=managed \
  --project=YOUR_PROJECT_ID

# This change is Laravel-code + 2 new migrations - after deploying the new image, run migrations as a
# separate deliberate step (never automatically - see docs/CLOUD_RUN_DEPLOYMENT.md §9 for why):
gcloud run jobs execute value-market-migrate --region=me-central1 --project=YOUR_PROJECT_ID
```

If the `value-market-migrate` Job doesn't already exist, create it once per `docs/CLOUD_RUN_DEPLOYMENT.md`
§9 (needs updating to reference the new image tag before each execution that should pick up this change).

## 19. Rollback Commands

```bash
# List revisions to find the one to roll back to
gcloud run revisions list --service=value-market --region=me-central1 --project=YOUR_PROJECT_ID

# Shift 100% of traffic back to the previous revision (instant, no rebuild)
gcloud run services update-traffic value-market \
  --to-revisions=<previous-revision-name>=100 \
  --region=me-central1 --project=YOUR_PROJECT_ID
```

**The two new indexes are not automatically rolled back by a traffic shift.** If the previous revision's
code doesn't expect them, they're still harmless to leave in place (extra indexes don't break older code
that doesn't know about them - they just go unused). To fully roll back the schema too:

```bash
php artisan migrate:rollback --step=2 --force   # rolls back both Phase 19 migrations, in reverse order
```

Run this as a deliberate step against the production database, the same way `docs/CLOUD_RUN_DEPLOYMENT.md`
§9 runs `migrate --force` - never automatically, never as part of a build/deploy pipeline step.

## 20. Post-Deployment Monitoring Steps

1. Watch `gcloud run services logs read value-market --region=me-central1 --project=YOUR_PROJECT_ID
   --limit=100` (or the Cloud Logging console) for the first 15-30 minutes after deploy - specifically
   5xx/502/503 rates and any `SQLSTATE` errors (would indicate the new indexes or the rewritten queries hit
   something this environment's test data didn't expose).
2. Confirm the migration job (§18) actually completed successfully before considering the deploy done - a
   deploy with the new code but without the new indexes will still work (the queries are still correct SQL
   without them), just without the measured speedup.
3. Compare real Cloud Run request latency for `/admin/home` before/after in Cloud Monitoring (Metrics
   Explorer → `run.googleapis.com/request_latencies`, filtered to this service) - this is the number that
   was **not measurable from this environment** (§12, §14) and is the actual confirmation this fix helped
   real users, not just this container's synthetic benchmark.
4. If `min-instances` is changed per §11's suggestion, watch billed instance-time in the Cloud Run console
   for a day or two to confirm the actual cost delta matches expectations before treating it as settled.

---

## الخلاصة النهائية

**هل `/admin/home` جاهز للـ Production؟**

**من ناحية الكود (Laravel/PHP/MySQL): نعم.** كل شي مقاس بدليل حقيقي (مو تخمين)، 360/360 اختبار ناجح، صفر
تغيير بـBusiness Logic أو UI أو Routes، الإصلاحات مراجَعة (§16) ولا يوجد N+1 أو مشاكل صحة نتائج أو race
conditions.

**الشيء الوحيد المتبقي اللي يمنع اعتباره "جاهز 100%" بثقة كاملة:** **قياس حقيقي من Cloud Run نفسه** - TTFB،
cold/warm latency، وسجلات الأخطاء (§12-15). هاد البيئة ما عندها وصول gcloud ولا شبكة للخدمة الفعلية، فما
قدرت أثبت (ولا أنفي) وجود bottleneck بطبقة تقديم الطلبات (Apache/PHP-FPM/container) فوق التحسينات المؤكدة
بطبقة قاعدة البيانات. بما إنه قاعدة البيانات كانت 74% من الوقت المقاس والإصلاحات ما لمست طبقة الخادم، التحسن
متوقع ينعكس على الإنتاج - لكن هاد "متوقع"، مو "مؤكد قياسياً".

**الخطوة الوحيدة المطلوبة لإغلاق هاد الملف نهائياً:** تشغيل أوامر §10 و§12-13 (بواسطة شخص عنده صلاحيات
gcloud) وتعبئة صف "Cloud Run warm/cold latency" و"HTTP errors" بجدول §14 بأرقام حقيقية بدل
`Not measurable from current environment`.
