<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\ComboProduct;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Store;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnostic-only tool (docs/PHASE_19_ADMIN_HOME_QUERY_PROFILING.md): measures real execution time - not
 * just query count - for /admin/home against a production-like volume of data. Never run against a real
 * customer database; --seed writes synthetic rows into whatever connection is currently configured, meant
 * for a disposable dev/benchmark database only.
 */
class ProfileAdminHome extends Command
{
    protected $signature = 'perf:admin-home {--seed : Seed synthetic order/order_items volume first} {--rows=25000 : Number of order_items/orders rows to seed for the profiled store} {--other-rows=0 : Also seed this many rows PER OTHER existing store, so store_id has real selectivity for EXPLAIN (a single-store seed makes store_id match 100% of the table, which is not representative)} {--explain : Run EXPLAIN on every captured query}';

    protected $description = 'Profile real execution time (not just query count) for /admin/home';

    public function handle()
    {
        $primaryStoreId = null;
        if ($this->option('seed')) {
            $primaryStoreId = $this->seedVolume((int) $this->option('rows'));
            $otherRows = (int) $this->option('other-rows');
            if ($otherRows > 0 && $primaryStoreId) {
                $this->seedOtherStores($primaryStoreId, $otherRows);
            }
        }

        $storeId = (int) ($primaryStoreId ?? Store::query()->value('id'));
        if (!$storeId) {
            $this->error('No store found. Run with --seed first.');
            return 1;
        }

        $admin = User::where('role_id', Role::SUPER_ADMIN)->first();
        if (!$admin) {
            $admin = User::forceCreate([
                'username' => 'perf_admin_' . uniqid(), 'password' => bcrypt('x'), 'disk' => 'public',
                'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN,
            ]);
        }
        Auth::login($admin);
        session(['store_id' => $storeId]);

        // Single listener for the whole command's lifetime, every captured query tagged with whichever
        // phase is currently running (DB::listen() has no "unlisten" - registering a fresh one per phase
        // silently double(triple/...)-counts every later phase into the earlier ones' totals too, since all
        // registered listeners keep firing for the rest of the process).
        $phase = 'startup';
        $allQueries = [];
        DB::listen(function ($event) use (&$phase, &$allQueries) {
            $allQueries[] = ['phase' => $phase, 'sql' => $event->sql, 'bindings' => $event->bindings, 'time_ms' => $event->time];
        });
        // Deliberately a `function` with an explicit `use (&$allQueries)`, not an `fn()` arrow function -
        // arrow functions auto-capture by VALUE at definition time, which would freeze this on the empty
        // array $allQueries held at that point and make every later call return nothing.
        $queriesFor = function (string $p) use (&$allQueries) {
            return array_values(array_filter($allQueries, fn ($q) => $q['phase'] === $p));
        };

        // AppServiceProvider::boot() shares system_settings/web_settings/currency_* to every view, but only
        // when `!runningInConsole()` (a deliberate, pre-existing guard - see the comment at that call site -
        // so artisan commands with no DB, like package:discover during the Docker build, don't crash). This
        // command has a DB and needs to render the real view, so it replicates just the view-data half of
        // that block (not its config()->set() mail/OAuth side effects, which are irrelevant to rendering
        // /admin/home and shouldn't be mutated by a diagnostic command) rather than touching the provider.
        $phase = 'bootstrap';
        $bootstrapStart = microtime(true);
        $systemSettings = json_decode(app(\App\Services\SettingService::class)->getSettings('system_settings', true) ?? '[]', true);
        $webSettings = json_decode(app(\App\Services\SettingService::class)->getSettings('web_settings', true) ?? '[]', true);
        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'system_settings' => $systemSettings, 'web_settings' => $webSettings,
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'version' => rand(0, 9999),
        ]);
        $bootstrapWallMs = (microtime(true) - $bootstrapStart) * 1000;
        $bootstrapQueries = $queriesFor('bootstrap');

        $this->info("Profiling /admin/home (store_id={$storeId}, admin user_id={$admin->id})");
        $this->comment('(view-data bootstrap that AppServiceProvider::boot() normally shares on every real request: ' . count($bootstrapQueries) . ' queries, ' . round($bootstrapWallMs, 2) . ' ms - counted separately below, not attributed to the controller)');
        $this->line('');

        // ---- Pass 1: direct controller call, per-query timing (matches the existing 14-query baseline -
        // no web middleware, no view rendering) ----
        $phase = 'controller';
        $controllerStart = microtime(true);
        $view = app(\App\Http\Controllers\Admin\HomeController::class)->index();
        $controllerWallMs = (microtime(true) - $controllerStart) * 1000;
        $queries = $queriesFor('controller');

        $phase = 'render';
        $renderStart = microtime(true);
        $html = $view->render();
        $renderWallMs = (microtime(true) - $renderStart) * 1000;
        $renderQueries = $queriesFor('render');

        $dbTotalMs = array_sum(array_column($queries, 'time_ms'));
        $phpOnlyMs = $controllerWallMs - $dbTotalMs;

        $this->info('=== Pass 1: HomeController::index() direct call (controller logic only) ===');
        $this->table(['Metric', 'Value'], [
            ['Query count', count($queries)],
            ['DB time (sum of query times)', round($dbTotalMs, 2) . ' ms'],
            ['Controller wall time (incl. DB)', round($controllerWallMs, 2) . ' ms'],
            ['PHP-only time (wall - DB)', round($phpOnlyMs, 2) . ' ms'],
            ['Blade render time (separate ->render() call)', round($renderWallMs, 2) . ' ms'],
            ['Rendered HTML size', number_format(strlen($html)) . ' bytes'],
        ]);

        $this->line('');
        $this->info('Queries ranked slowest to fastest:');
        usort($queries, fn ($a, $b) => $b['time_ms'] <=> $a['time_ms']);
        $rows = [];
        foreach ($queries as $i => $q) {
            $label = $this->labelQuery($q['sql']);
            $rows[] = [$i + 1, $label, round($q['time_ms'], 3) . ' ms', \Illuminate\Support\Str::limit($q['sql'], 90)];
        }
        $this->table(['#', 'Origin (matched by SQL shape)', 'Time', 'SQL'], $rows);

        // ---- Blade render itself also runs real queries (view composers + anything the template calls
        // inline) - the old 14-query baseline never saw these, because it only ever called ::index()
        // directly, never ->render(). This is frequently the actual dominant cost, not the controller. ----
        $renderDbMs = array_sum(array_column($renderQueries, 'time_ms'));
        $this->line('');
        $this->info('=== Queries fired DURING Blade rendering (not counted in the controller\'s own query count) ===');
        $this->table(['Metric', 'Value'], [
            ['Query count', count($renderQueries)],
            ['DB time', round($renderDbMs, 2) . ' ms'],
            ['Non-DB render time (wall - DB)', round($renderWallMs - $renderDbMs, 2) . ' ms'],
        ]);
        $renderByShape = [];
        foreach ($renderQueries as $q) {
            $shape = preg_replace('/\d+/', '?', preg_replace('/\s+/', ' ', $q['sql']));
            $renderByShape[$shape]['count'] = ($renderByShape[$shape]['count'] ?? 0) + 1;
            $renderByShape[$shape]['total_ms'] = ($renderByShape[$shape]['total_ms'] ?? 0) + $q['time_ms'];
            $renderByShape[$shape]['label'] = $this->labelQuery($q['sql']);
        }
        uasort($renderByShape, fn ($a, $b) => $b['total_ms'] <=> $a['total_ms']);
        $rows = [];
        foreach ($renderByShape as $shape => $agg) {
            $rows[] = [$agg['label'], $agg['count'], round($agg['total_ms'], 3) . ' ms', \Illuminate\Support\Str::limit($shape, 80)];
        }
        $this->table(['Origin', 'Repeats', 'Total time', 'SQL shape'], $rows);

        // ---- Pass 2: route-specific middleware actually gated on 'admin/home' (role:..., CheckPurchaseCode,
        // CheckStoreNotEmpty), timed directly in-process. NOTE: this deliberately does NOT dispatch through
        // the full HTTP Kernel - doing so would start a *second*, disconnected session (StartSession builds
        // a fresh one for any Request::create() call with no cookie), breaking the Auth::login()/store_id
        // state this script already set up, and would mostly be re-measuring Laravel's generic
        // cookie/session/CSRF bootstrapping rather than anything specific to /admin/home. What's measured
        // here is real: the actual handle() methods of every middleware this route's own route-group lists,
        // executed with the same session/auth state the controller call above used.
        $phase = 'middleware';
        $mwRequest = \Illuminate\Http\Request::create('/admin/home', 'GET');
        $mwRequest->setRouteResolver(fn () => \Illuminate\Support\Facades\Route::getRoutes()->getByName('admin.home'));
        $mwRequest->setLaravelSession(app('session.store'));

        // Same order these actually run in for a real request (Kernel.php's 'web' group, then this route's
        // own group) - minus the pure cookie/CSRF/session-bootstrap middleware (EncryptCookies,
        // AddQueuedCookiesToResponse, StartSession, ShareErrorsFromSession, VerifyCsrfToken - a no-op on GET
        // anyway, SubstituteBindings - no route-model-binding on this route, LogoutMiddleware - only touches
        // response headers after the controller runs) and 'auth' itself (already satisfied via the
        // Auth::login() call above) - none of those do anything DB- or business-logic-relevant to time here.
        $middlewareChain = [
            new \App\Http\Middleware\LanguageManager(),
            new \App\Http\Middleware\GetDefaultData(),
            new \App\Http\Middleware\SetDefaultStore(),
            new \App\Http\Middleware\CheckPurchaseCode(),
            new \App\Http\Middleware\CheckStoreNotEmpty(),
        ];
        $roleMiddleware = new \App\Http\Middleware\RoleMiddleware();

        $mwStart = microtime(true);
        $pipelineResult = null;
        $roleMiddleware->handle($mwRequest, function ($req) use (&$pipelineResult, $middlewareChain) {
            $next = function ($r) use (&$pipelineResult) {
                $pipelineResult = 'reached-controller';
                return response('ok');
            };
            foreach (array_reverse($middlewareChain) as $mw) {
                $inner = $next;
                $next = fn ($r) => $mw->handle($r, $inner);
            }
            return $next($req);
        }, 'super_admin', 'admin', 'editor');
        $mwWallMs = (microtime(true) - $mwStart) * 1000;
        $mwQueries = $queriesFor('middleware');
        $mwDbMs = array_sum(array_column($mwQueries, 'time_ms'));

        $this->line('');
        $this->info('=== Pass 2: route-group middleware for admin/home, timed directly (role: + CheckPurchaseCode + CheckStoreNotEmpty) ===');
        $this->table(['Metric', 'Value'], [
            ['Reached controller (middleware chain passed)', $pipelineResult === 'reached-controller' ? 'yes' : 'no - see below'],
            ['Middleware queries', count($mwQueries)],
            ['Middleware DB time', round($mwDbMs, 2) . ' ms'],
            ['Middleware wall time (incl. its own DB)', round($mwWallMs, 2) . ' ms'],
        ]);

        $totalReconstructed = $bootstrapWallMs + $mwWallMs + $controllerWallMs + $renderWallMs;
        $totalDb = array_sum(array_column($bootstrapQueries, 'time_ms')) + $mwDbMs + $dbTotalMs + $renderDbMs;
        $this->line('');
        $this->info('=== Reconstructed total (view-data bootstrap + middleware + controller + Blade render, same process) ===');
        $this->table(['Metric', 'Value'], [
            ['View-data bootstrap (every admin page, not home-specific)', round($bootstrapWallMs, 2) . ' ms (' . count($bootstrapQueries) . ' queries)'],
            ['Route middleware (role/CheckPurchaseCode/CheckStoreNotEmpty)', round($mwWallMs, 2) . ' ms (' . count($mwQueries) . ' queries)'],
            ['HomeController::index()', round($controllerWallMs, 2) . ' ms (' . count($queries) . ' queries)'],
            ['Blade render', round($renderWallMs, 2) . ' ms (' . count($renderQueries) . ' queries)'],
            ['Total DB time (all phases)', round($totalDb, 2) . ' ms'],
            ['TOTAL reconstructed wall time', round($totalReconstructed, 2) . ' ms'],
        ]);
        $this->warn('This total does NOT include: Apache/PHP-FPM process overhead, TLS, network round-trip, or Cloud Run cold start - those can only be measured against the real deployed service.');

        if ($this->option('explain')) {
            $this->line('');
            $this->info('=== EXPLAIN for each distinct SELECT captured (controller + render phases) ===');
            $seen = [];
            foreach (array_merge($queries, $renderQueries) as $q) {
                $normalized = preg_replace('/\s+/', ' ', $q['sql']);
                if (stripos($normalized, 'select') !== 0 || isset($seen[$normalized])) {
                    continue;
                }
                $seen[$normalized] = true;
                $this->line('');
                $this->comment($this->labelQuery($q['sql']) . ':');
                $this->line($normalized);
                try {
                    $bound = $this->interpolate($q['sql'], $q['bindings']);
                    $plan = DB::select('EXPLAIN ' . $bound);
                    $this->table(array_keys((array) $plan[0]), array_map(fn ($r) => array_values((array) $r), $plan));
                } catch (\Throwable $e) {
                    $this->error('EXPLAIN failed: ' . $e->getMessage());
                }
            }
        }

        return 0;
    }

    private function labelQuery(string $sql): string
    {
        $map = [
            'from `order_items` where `store_id` = ? group by YEAR(CURDATE()), MONTH(created_at)' => 'getMonthlyDataCombined() [monthly chart]',
            "DATE_FORMAT(created_at, '%d-%b')" => 'getWeeklySalesData() [weekly chart]',
            'DAY(created_at) as date' => 'getDailySalesData() [daily chart, 30d]',
            'from `order_items` where `store_id` = ?' => 'AdmintotalEarnings() [lifetime SUM, unbounded]',
            'from `orders` where `store_id` = ?' => 'countNewOrders()',
            'from `products` where `store_id` = ? and exists' => 'product_counter [whereHas productVariants]',
            'from `combo_products` where `store_id` = ?' => 'combo_product_counter',
            'from `stores`' => 'total_store / store_details / top_sellers Store::find()',
            'from `sellers` inner join `seller_store`' => 'total_seller [whereHas stores]',
            'from `users` where `role_id` = ?' => 'countNewUsers() / countDeliveryBoys()',
            'from `seller_data` inner join `seller_store`' => 'top_sellers [Store::sellers()]',
            'from `order_items` where `order_items`.`seller_id` in' => 'top_sellers eager-loaded order_items (unbounded, summed in PHP)',
            "count(distinct `order_id`) as aggregate from `order_items` where exists" => 'ordersCount() [home.blade.php inline, called 24x]',
        ];
        foreach ($map as $needle => $label) {
            if (stripos($sql, $needle) !== false) {
                return $label;
            }
        }
        return '(middleware/session/other)';
    }

    private function interpolate(string $sql, array $bindings): string
    {
        foreach ($bindings as $binding) {
            $value = is_numeric($binding) ? $binding : DB::connection()->getPdo()->quote((string) $binding);
            $sql = preg_replace('/\?/', (string) $value, $sql, 1);
        }
        return $sql;
    }

    private function seedVolume(int $rows): ?int
    {
        $this->info("Seeding {$rows} synthetic orders/order_items rows into the CURRENT connection ({$this->laravel['config']->get('database.default')}) for benchmarking...");

        $store = Store::first();
        if (!$store) {
            $this->error('No store exists to seed against.');
            return null;
        }
        $storeId = $store->id;

        $category = Category::first() ?? Category::forceCreate([
            'name' => json_encode(['en' => 'Perf Category']), 'slug' => 'perf-cat-' . uniqid(),
            'image' => '', 'banner' => '',
        ]);

        $sellerIds = [];
        for ($i = 0; $i < 6; $i++) {
            $user = User::forceCreate([
                'username' => 'perf_seller_' . uniqid(), 'password' => bcrypt('x'), 'disk' => 'public',
                'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
            ]);
            $seller = Seller::forceCreate(['user_id' => $user->id, 'disk' => 'public', 'status' => 1]);
            SellerStore::forceCreate([
                'seller_id' => $seller->id, 'user_id' => $user->id, 'store_id' => $storeId,
                'slug' => 'perf-store-' . uniqid(), 'store_name' => 'Perf Store', 'store_description' => 'Perf',
                'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
                'permissions' => json_encode(['require_products_approval' => 0]),
            ]);
            $sellerIds[] = $seller->id;
        }

        // Modest product volume (product_counter's whereHas is cheap regardless; included for realism).
        $bar = $this->output->createProgressBar(300);
        for ($i = 0; $i < 300; $i++) {
            $product = Product::forceCreate([
                'category_id' => $category->id, 'seller_id' => $sellerIds[$i % count($sellerIds)],
                'store_id' => $storeId, 'name' => json_encode(['en' => "Perf Product $i"]),
                'slug' => 'perf-product-' . $i . '-' . uniqid(), 'image' => '', 'deliverable_cities' => '',
                'stock_type' => '0', 'stock' => 100, 'availability' => 1, 'status' => 1,
            ]);
            Product_variants::forceCreate(['product_id' => $product->id, 'price' => 25, 'status' => 1]);
            $bar->advance();
        }
        $bar->finish();
        $this->line('');

        $customer = User::forceCreate([
            'username' => 'perf_customer_' . uniqid(), 'password' => bcrypt('x'), 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'mobile' => (string) random_int(6000000000, 6999999999),
        ]);

        // Spread created_at across ~400 days so monthly/weekly/daily grouping all have real data, not just
        // a single bucket - otherwise GROUP BY timing wouldn't reflect a mature store's actual distribution.
        $daysSpan = 400;
        $statuses = ['delivered', 'pending', 'cancelled', 'returned'];
        $chunkSize = 2000;
        $now = now();

        $bar = $this->output->createProgressBar($rows);
        for ($start = 0; $start < $rows; $start += $chunkSize) {
            $count = min($chunkSize, $rows - $start);
            $orderRows = [];
            $itemRows = [];
            for ($i = 0; $i < $count; $i++) {
                $createdAt = $now->copy()->subDays(random_int(0, $daysSpan))->subSeconds(random_int(0, 86400));
                $subTotal = random_int(500, 20000) / 100;
                $commission = round($subTotal * 0.1, 4);
                $orderId = $start + $i + 1;

                $orderRows[] = [
                    'user_id' => $customer->id, 'store_id' => $storeId, 'mobile' => $customer->mobile,
                    'total' => $subTotal, 'delivery_charge' => 0, 'payment_method' => 'cod',
                    'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD',
                    'base_currency_code' => 'USD', 'order_payment_currency_conversion_rate' => 1,
                    'created_at' => $createdAt, 'updated_at' => $createdAt,
                ];
                $itemRows[] = [
                    'user_id' => $customer->id, 'store_id' => $storeId, 'order_id' => $orderId,
                    'seller_id' => $sellerIds[array_rand($sellerIds)], 'product_variant_id' => 1,
                    'quantity' => random_int(1, 5), 'price' => $subTotal, 'sub_total' => $subTotal,
                    'admin_commission_amount' => $commission, 'seller_commission_amount' => $subTotal - $commission,
                    'status' => 'delivered', 'active_status' => $statuses[array_rand($statuses)],
                    'order_type' => 'regular_order', 'created_at' => $createdAt, 'updated_at' => $createdAt,
                ];
            }
            DB::table('orders')->insert($orderRows);
            DB::table('order_items')->insert($itemRows);
            $bar->advance($count);
        }
        $bar->finish();
        $this->line('');
        $this->info('Seeding complete.');

        return $storeId;
    }

    /**
     * Seeds order_items/orders volume into every OTHER existing store, so the profiled store's store_id
     * has real selectivity in EXPLAIN. A single-store seed makes `WHERE store_id = ?` match 100% of the
     * table, which is not representative of a real multi-tenant deployment and makes MySQL correctly (not
     * buggily) skip the store_id index in favor of a full scan - that's a property of the benchmark data,
     * not evidence about whether the index helps in production.
     */
    private function seedOtherStores(int $excludeStoreId, int $rowsPerStore): void
    {
        $otherStoreIds = Store::where('id', '!=', $excludeStoreId)->pluck('id')->all();
        if (empty($otherStoreIds)) {
            $this->comment('No other stores exist - skipping --other-rows (store_id selectivity will stay 100% in EXPLAIN).');
            return;
        }

        $customer = User::forceCreate([
            'username' => 'perf_customer_other_' . uniqid(), 'password' => bcrypt('x'), 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'mobile' => (string) random_int(6000000000, 6999999999),
        ]);
        $sellerId = Seller::first()->id ?? 1;
        $daysSpan = 400;
        $statuses = ['delivered', 'pending', 'cancelled', 'returned'];
        $chunkSize = 2000;
        $now = now();

        foreach ($otherStoreIds as $otherStoreId) {
            $this->info("Seeding {$rowsPerStore} rows for other store_id={$otherStoreId}...");
            $bar = $this->output->createProgressBar($rowsPerStore);
            for ($start = 0; $start < $rowsPerStore; $start += $chunkSize) {
                $count = min($chunkSize, $rowsPerStore - $start);
                $orderRows = [];
                $itemRows = [];
                for ($i = 0; $i < $count; $i++) {
                    $createdAt = $now->copy()->subDays(random_int(0, $daysSpan))->subSeconds(random_int(0, 86400));
                    $subTotal = random_int(500, 20000) / 100;
                    $commission = round($subTotal * 0.1, 4);
                    $orderId = $start + $i + 1;

                    $orderRows[] = [
                        'user_id' => $customer->id, 'store_id' => $otherStoreId, 'mobile' => $customer->mobile,
                        'total' => $subTotal, 'delivery_charge' => 0, 'payment_method' => 'cod',
                        'order_payment_currency_id' => 1, 'order_payment_currency_code' => 'USD',
                        'base_currency_code' => 'USD', 'order_payment_currency_conversion_rate' => 1,
                        'created_at' => $createdAt, 'updated_at' => $createdAt,
                    ];
                    $itemRows[] = [
                        'user_id' => $customer->id, 'store_id' => $otherStoreId, 'order_id' => $orderId,
                        'seller_id' => $sellerId, 'product_variant_id' => 1,
                        'quantity' => random_int(1, 5), 'price' => $subTotal, 'sub_total' => $subTotal,
                        'admin_commission_amount' => $commission, 'seller_commission_amount' => $subTotal - $commission,
                        'status' => 'delivered', 'active_status' => $statuses[array_rand($statuses)],
                        'order_type' => 'regular_order', 'created_at' => $createdAt, 'updated_at' => $createdAt,
                    ];
                }
                DB::table('orders')->insert($orderRows);
                DB::table('order_items')->insert($itemRows);
                $bar->advance($count);
            }
            $bar->finish();
            $this->line('');
        }
        $this->info('Other-store seeding complete.');
    }
}
