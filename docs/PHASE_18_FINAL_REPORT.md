# Final Report — /admin/home Performance Pass

See `docs/PHASE_18_PERFORMANCE_ADMIN_HOME.md` for the full diagnosis and reasoning; this is the requested
final-deliverables index.

## 1. السبب الأساسي للبطء

سببين مؤكدين بقياس فعلي (مو تخمين):
1. استعلام ثقيل (GROUP BY على `order_items`) بيتكرر **7 مرات متطابقة** بسبب loop بيستدعيه من جوا بدل مرة وحدة قبله.
2. أعمدة `order_items.store_id`, `orders.store_id`, `combo_products.store_id`, `users.role_id` بدون index خالص — كل استعلامات هاي الصفحة full table scan.

## 2. جميع المشاكل المكتشفة

انظر `PHASE_18_PERFORMANCE_ADMIN_HOME.md` §2 (5 نقاط، مرتبة بالخطورة، كل وحدة مع الملف والسبب).

## 3. جميع المشاكل التي تم إصلاحها

- التكرار السباعي (Finding 1) ✅
- الاستعلامات الشهرية الثلاثة المدمجة بواحد (Finding 2) ✅
- الـ4 indexes المفقودة (Finding 3) ✅
- استعلامات countNewUsers الخمسة المدمجة بواحد (Finding 4) ✅
- `route:cache` **لم يُصلح عمداً** — يتطلب تغيير أسماء routes، وهاد ممنوع صراحة بقواعد هاد الطلب (Finding 5، موثقة بالكامل مسبقاً بـ`docs/DEPLOYMENT.md`)

## 4. الملفات المعدّلة

| الملف | التغيير |
|---|---|
| `app/Http/Controllers/Admin/HomeController.php` | نقل `getWeeklySalesData()` بره الـ loop؛ استبدال `getMonthlyData()` (3 نداءات) بـ `getMonthlyDataCombined()` (نداء واحد) |
| `app/function_helper.php` | `countNewUsers()`: 5 استعلامات → استعلام واحد بـ`CASE WHEN` |
| `database/migrations/2025_02_14_000000_add_admin_home_performance_indexes.php` (جديد) | 4 indexes |
| `tests/Feature/Phase18/AdminHomePerformanceTest.php` (جديد) | 3 اختبارات تثبت نفس النتائج + عدد استعلامات أقل |

## 5. Database indexes المضافة

| الجدول | الـ index | السبب |
|---|---|---|
| `order_items` | `(store_id, created_at)` composite | استعلامات المبيعات الشهرية/الأسبوعية/اليومية بتفلتر store_id وبتجمع/تفلتر created_at بنفس الاستعلام |
| `orders` | `store_id` | `countNewOrders()` |
| `combo_products` | `store_id` | عداد منتجات الكومبو بالصفحة الرئيسية |
| `users` | `role_id` | مستخدم بعشرات الأماكن بلوحة الأدمن، مؤكد بـ`grep`، ليس مجرد هاي الصفحة |

كل index تحقق بـ`SHOW INDEX` بعد الترحيل + دورة `up()`/`down()`/`up()` كاملة على قاعدة بيانات حقيقية.

## 6. إعدادات OPcache النهائية

`docker/opcache.ini` — لم تُعدَّل (كانت صحيحة أصلاً من عمل سابق بهاد المشروع):
```ini
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=192
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.jit=off
```

## 7. Dockerfile النهائي

لم يُعدَّل — تم فحصه بالكامل (multi-stage، PHP 8.4 مثبّت بكل المراحل، كل الإضافات المطلوبة موجودة بما فيها `imagick`، `composer install --no-dev --optimize-autoloader`، `.dockerignore` يستثني `tests/`/`vendor/`/`node_modules/`) ولقيته مضبوط صح أصلاً من عمل سابق موثّق بـ`docs/CLOUD_RUN_DEPLOYMENT.md`.

## 8. Apache configuration النهائي

لم يُعدَّل — `docker/apache-cloud-run.conf` بسيط وصحيح (`DocumentRoot` صحيح، `AllowOverride All` يسمح بـrewrite rules تبع Laravel، اللوجز على stdout/stderr). النقطة الوحيدة الجديرة بالذكر: هاد الـ image بيستخدم `mod_php` (مو PHP-FPM)، يعني كل طلب متزامن بياخد process كامل من Apache — هاد بيأثر مباشرة على قرار `--concurrency` بـCloud Run (انظر قسم 10).

## 9. Cloud Run settings المقترحة

**ملاحظة مهمة: ما عندي وصول مباشر لإعدادات Cloud Run الحالية الفعلية (لا gcloud credentials بهاد الجلسة) — هاي بداية معقولة تحتاج ضبط بعد مراقبة ترافيك حقيقي، مش رقم نهائي.**

| الإعداد | القيمة المقترحة | السبب |
|---|---|---|
| Min instances | 1 | لوحة أدمن تفاعلية — تفادي cold start على كل جلسة |
| Max instances | يعتمد على الترافيك الفعلي — ما عندي بيانات | — |
| Startup CPU Boost | مفعّل | الـentrypoint بينفذ `config:cache`/`view:cache` كل إقلاع container |
| Concurrency | معتدل (20-40 كبداية، مش الافتراضي 80) | `mod_php` بياخد process كامل لكل طلب متزامن — تركيز عالي ممكن يستهلك ذاكرة الـcontainer بسرعة |
| CPU/Memory | **لم أرفعهم** — ما فيه سبب مقاس يستدعي هيك | تحسين الكود (26→14 استعلام) بيقلل الوقت المنتظر على قاعدة البيانات، مش استهلاك CPU/Memory تبع Laravel نفسه |

## 10. أوامر gcloud جاهزة

```bash
# استبدل YOUR_CLOUD_RUN_SERVICE باسم الخدمة الفعلي — ما عندي وصول لمعرفته
gcloud run services update YOUR_CLOUD_RUN_SERVICE \
  --region=me-central1 \
  --min-instances=1 \
  --cpu-boost \
  --concurrency=30 \
  --no-cpu-throttling=false

# لمراقبة استهلاك الذاكرة الفعلي بعد التطبيق (لتحديد إذا concurrency=30 مناسب فعلاً)
gcloud run services describe YOUR_CLOUD_RUN_SERVICE --region=me-central1 --format="yaml(status)"
```

## 11. أوامر البناء والنشر

```bash
# بناء الصورة (نفس الـDockerfile الموجود، لم يتغير)
docker build -t me-central1-docker.pkg.dev/YOUR_PROJECT/YOUR_REPO/value-market:latest .

# رفعها
docker push me-central1-docker.pkg.dev/YOUR_PROJECT/YOUR_REPO/value-market:latest

# نشر على Cloud Run
gcloud run deploy YOUR_CLOUD_RUN_SERVICE \
  --image=me-central1-docker.pkg.dev/YOUR_PROJECT/YOUR_REPO/value-market:latest \
  --region=me-central1

# تشغيل الترحيلات (migrations) — منفصل عن النشر، متل ما موثق بـdocs/CLOUD_RUN_DEPLOYMENT.md §9
gcloud run jobs execute value-market-migrate --region=me-central1 --project=YOUR_PROJECT
```

## 12. طريقة اختبار /admin/home

```bash
# قبل: قسّت 26 استعلام فعلياً بهاد الجلسة عبر DB::listen() على الكونترولر الحقيقي
# بعد: 14 استعلام — مثبتة بـ tests/Feature/Phase18/AdminHomePerformanceTest.php

php artisan test --filter=AdminHomePerformanceTest
```
أو يدوياً: سجّل دخول كأدمن، افتح `/admin/home`، راقب query log (Laravel Debugbar بالتطوير، أو Cloud Logging بالإنتاج).

## 13. النتيجة المتوقعة من كل تحسين

- إصلاح التكرار السباعي: أكبر أثر لحاله — إزالة 6 استعلامات ثقيلة بالكامل.
- دمج الاستعلامات الشهرية: -2 استعلام.
- دمج countNewUsers: -4 استعلامات.
- الـindexes: ما بتقلل عدد الاستعلامات (كانت أصلاً مقاسة بقاعدة بيانات فاضية) — بس بتمنع full table scan لما تكبر البيانات فعلياً بالإنتاج، وهاد الأثر الحقيقي الأكبر مع نمو البيانات بمرور الوقت.
- Cloud Run (min-instances=1, cpu-boost): تقليل زمن أول طلب بعد فترة خمول (cold start)، مش أثر على استعلامات قاعدة البيانات.

## 14. مشاكل متبقية لم يتم حلها (وذكرت بوضوح ليش)

1. **`route:cache` لسا معطّل** — يحتاج تغيير أسماء routes، ممنوع بقواعد هاد الطلب. موثق بالكامل (`docs/DEPLOYMENT.md`)، جاهز يتصلح بجلسة منفصلة إذا سمحت.
2. **إعدادات Cloud Run الفعلية غير معروفة** — الأرقام المقترحة أعلاه بداية معقولة، مش قياس حقيقي، لأنه ما عندي وصول مباشر للخدمة الحية.
3. **لا يوجد اختبار تحميل (load testing) حقيقي** — تم فحص عدد الاستعلامات، مش زمن الاستجابة الفعلي تحت ضغط حقيقي (يحتاج بنية تحتية غير متوفرة بهاد بيئة التطوير).
4. **`APP_DEBUG=true`/`APP_ENV=local`** بـ`.env.example` — موثقة سابقاً كتذكير تشغيلي، لازم تتأكد إنها `false`/`production` فعلياً بالـCloud Run الحي (ما بقدر أتحقق من هاد مباشرة).
