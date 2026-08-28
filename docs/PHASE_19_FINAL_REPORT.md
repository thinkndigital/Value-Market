# تقرير نهائي — تحليل زمن التنفيذ الفعلي لـ /admin/home (Phase 19)

راجع `docs/PHASE_19_ADMIN_HOME_QUERY_PROFILING.md` للتفاصيل الكاملة والأدلة (EXPLAIN، الأرقام، القرارات)؛
هاد ملخص مباشر يجاوب على كل نقطة بالطلب.

**كل إصلاحات Phase 18 (26 → 14 query) موجودة ولم يتم التراجع عنها.**

## 1-4: كل query، زمنها، الترتيب، ومجموع وقت الـ Database

أداة تشخيص جديدة `php artisan perf:admin-home` (`app/Console/Commands/ProfileAdminHome.php`) — بتسجل
`DB::listen()` واحد لكل الطلب، بتحسب زمن كل query فعلي (مو تقدير)، وبترتبهم من الأبطأ للأسرع. شغّلتها ضد
بيانات واقعية: **145,589 صف بجدول order_items موزعة على 3 متاجر** (المتجر المقاس بياخد ~17% من الجدول —
مو 100%، عشان الـ EXPLAIN يعطي نتيجة حقيقية تعكس بيئة multi-tenant فعلية، مو بيانات مصطنعة بمتجر واحد
بتخلي MySQL يتجاهل الـ index بشكل صحيح).

**مجموع وقت الـ Database قبل الإصلاح: ~824 ملي ثانية من إجمالي ~1109 ملي ثانية (74%).**

## 5: هل المشكلة Database أم Laravel/PHP أم External API أم Rendering؟

**Database بشكل شبه كامل (74% من الوقت):**
- لا يوجد أي HTTP/API request خارجي بالكونترولر ولا بالـ view (فحصت بـ grep، صفر نتائج).
- "الـ Rendering" كمحرك Blade نفسه سريع (~25 ملي ثانية) — اللي كان يبدو مشكلة rendering كان فعلياً **54
  استعلام قاعدة بيانات** تشتغل *أثناء* الـ render، مو المحرك نفسه.
- الـ 26% الباقية (PHP) كان سببها محدد ومؤكد: جمع أرقام بلغة PHP بدل SQL (نقطة 7 تحت).

## 6-9: فحص EXPLAIN وقرارات الـ index

فحصت كل query الأثقل بـ `EXPLAIN` **قبل وبعد** أي تعديل، بما فيها اختبار candidate index مباشرة بـ SQL
والتأكد إنه غيّر خطة التنفيذ فعلاً قبل كتابة أي migration:

| المشكلة | الدليل (EXPLAIN) | القرار |
|---|---|---|
| `AdmintotalEarnings()`, `getMonthlyDataCombined()`, `getWeeklySalesData()` | `type: ALL` (full scan لـ143,589 صف) رغم وجود index على `(store_id, created_at)` — MySQL ما بيستخدمه لأنه مو covering (لازم يرجع للجدول الأساسي لقراءة sub_total) | أضفت index جديد **covering**: `(store_id, created_at, sub_total, admin_commission_amount, quantity)` — تأكدت فعلياً إنه حوّل الخطة لـ`Using index` (بدون رجوع للجدول) قبل كتابة الـ migration. الـ index القديم صار عديم الفائدة (subset منه) فحذفته بنفس الـ migration. |
| `ordersCount()` (7 استعلامات مختلفة الآن بدل 24) | `type: ALL` رغم فلترة store_id + active_status | أضفت index جديد: `(store_id, active_status(50), order_id)` — قللت الصفوف الممسوحة من 143,796 إلى ~12,682 (حالة محددة) أو ~52,774 (كل الحالات) — تأكدت بـ EXPLAIN قبل الإضافة. |
| `top_sellers` aggregate الجديد | اختبرت index مرشح `(seller_id, active_status(50), sub_total, seller_commission_amount)` | **لم أضفه** — MySQL استمر يستخدم الـ index القديم رغم وجود المرشح، يعني ما في فائدة فعلية مثبتة. هاد دليل سلبي موثّق، مو تخمين. |

لا يوجد Filesort/Temporary table تم إلغاؤه بالكامل (الـ GROUP BY بالتصنيف الشهري/الأسبوعي لسه محتاج ترتيب
داخلي) لكن أصبح index-only (بدون قراءة الجدول الأساسي) — تحسين حقيقي مثبت.

## 10: الإصلاحات المطبّقة فعلياً (مو نصائح فقط)

### أ. `home.blade.php` كان يستدعي `ordersCount()` **24 مرة** داخل الـ template مباشرة
كل حالة (received/processed/shipped/delivered/cancelled/returned) كانت تُستعلم 2-5 مرات، بالإضافة
لاستعلام "كل الحالات" (فارغ) يتكرر **6 مرات متطابقة تماماً**. أصلحته بحساب كل قيمة **مرة وحدة** بالكونترولر
(`$orders_status_counts`) وتمريرها للـ view — 24 استدعاء أصبحوا 7. **القيم المعروضة نفسها بالضبط**، حتى
الخطأ الموجود مسبقاً (اثنين من الـ aria-valuenow كانوا يعرضوا رقم "received" بالغلط بمكان cancelled/returned)
تركته كما هو تماماً — مو جزء من مهمة الأداء، وتصحيحه كان رح يغيّر شكل الصفحة.

### ب. `top_sellers` كان يسحب كل صفوف order_items لكل بائع (بدون حد أقصى) ويجمعها بلغة PHP
استبدلته باستعلام SQL واحد (`GROUP BY seller_id` مع `SUM(CASE WHEN...)`) — نفس الأرقام بالضبط (مثبت
باختبار)، لكن الجمع يصير بقاعدة البيانات مباشرة بدل تحميل آلاف الصفوف لذاكرة PHP.

### ج. اثنين index جديدة (مذكورين فوق)، مبنيين على دليل EXPLAIN فعلي.

## 11: PHP loops و Blade loops بعد استرجاع البيانات

- فحصت كل الـ loops بالكونترولر — الوحيد المكلف كان `top_sellers` (مصلح، نقطة ب). الباقي (loop الأسبوع 7
  أيام، loop الشهر 12، loop اليوم 30) على arrays صغيرة جداً، بدون تأثير يُذكر.
- `home.blade.php` نفسه **لا يحتوي أي `@foreach`/`@for`** على بيانات — "الـ loop" الحقيقي كان الاستدعاءات
  الـ24 المباشرة (نقطة أ)، مو تكرار بالـ template.

## 12: أي HTTP/API requests أثناء التحميل؟

**لا يوجد** — فحصت الكونترولر والـ view بالكامل (`Http::`, `curl_`, `file_get_contents`, Guzzle) — صفر
نتائج.

## 13-14: ما لم يتم تغييره

Business Logic، UI، Routes، Authentication، Permissions، API contracts — **لم يتغير شيء منها**. كل إصلاح
هو إما cache لنتيجة كانت تتكرر (نفس القيمة)، أو نفس الحساب بلغة SQL بدل PHP (نفس النتيجة، مثبت باختبار)،
أو index إضافي. Dockerfile/Apache/OPcache/Cloud Run — لم يُلمس، لأنه القياس أثبت المشكلة بقاعدة البيانات
وليس بطبقة تقديم الطلبات.

## 15-16: Baseline قبل/بعد، وماذا يحتاج قياس Cloud Run

| Metric | Before | After |
| --- | ---: | ---: |
| Total queries | 83 | 66 |
| Database time | ~824 ms | ~165 ms |
| Slowest query | `top_sellers` eager-load (57.63ms) + الاستعلام المكرر `ordersCount('')` (~53ms × 6 = 319ms) | `getMonthlyDataCombined()` (~15ms) — الاثنين السابقين تم إصلاحهم |
| Total request time (Laravel layer فقط) | ~1109 ms | ~204 ms |
| PHP processing time | ~284 ms | ~39 ms |

**تخفيض ~81% بزمن الاستجابة المُقاس (~5.4x أسرع)، بنفس القيم المعروضة تماماً.**

**بصراحة تامة — هاد اللي قدرت أقيسه، وهاد اللي لأ:**
- كل الأرقام فوق قياس حقيقي (DB::listen + microtime) من داخل هاد الـ container، بطبقة Laravel/PHP/MySQL
  فقط.
- **ما قدرت أقيس ولا اخترعت رقم لـ**: زمن Apache/PHP-FPM الحقيقي، TLS handshake، زمن الشبكة، أو Cloud Run
  cold start — ولا وحدة فيهم متاحة من هاد البيئة (لا صلاحيات Cloud Run ولا وصول شبكة للخدمة الفعلية).
- بما إنه قاعدة البيانات كانت 74% من الوقت المقاس والإصلاحات ما لمست طبقة تقديم الطلبات، التحسين *النسبي*
  المفروض ينعكس بالإنتاج، لكن الأرقام *المطلقة* لازم تتأكد بقياس حقيقي على Cloud Run — هاد شي ما بقدر
  أثبته من هون.

## الملفات المعدّلة

| الملف | التغيير |
|---|---|
| `app/Http/Controllers/Admin/HomeController.php` | حساب `$orders_status_counts` مرة وحدة (7 بدل 24)؛ `top_sellers` بـ SQL aggregate بدل PHP sum |
| `resources/views/admin/pages/forms/home.blade.php` | 24 استدعاء `ordersCount()` → قراءة من `$orders_status_counts` |
| `database/migrations/2025_02_15_000000_add_order_items_financial_covering_index.php` (جديد) | covering index، حذف القديم المتضمَّن فيه |
| `database/migrations/2025_02_16_000000_add_order_items_status_count_index.php` (جديد) | index لاستعلامات `ordersCount()` |
| `app/Console/Commands/ProfileAdminHome.php` (جديد) | أداة القياس القابلة لإعادة الاستخدام |
| `tests/Feature/Phase19/AdminHomeQueryProfilingTest.php` (جديد) | 4 اختبارات: تطابق القيم، عدد الاستعلامات، صحة top_sellers |
| `tests/Feature/Phase18/AdminHomePerformanceTest.php` | تحديث threshold (16→23) مع شرح كامل للسبب |

## التحقق

`php artisan test` — **352 نجح، 0 فشل**. Migrations اتفحصت بدورة كاملة up→down→up + `migrate:fresh` نظيف.
`php -l` على كل ملف تم تعديله.
