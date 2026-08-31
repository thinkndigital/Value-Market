<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Admin\HomeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\Webhook;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\Seller\MediaController as SellerMediaController;
use App\Http\Controllers\Seller\AreaController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Seller\CategoryController;
use App\Http\Controllers\Customer\HomeController as CustomerHomeController;

use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

// ---------------------------------------------------------------------------------------------------------------------------
Route::get('/sitemap', function () {
    Artisan::call('sitemap:generate');
    return redirect()->back()->with('message', 'Sitemap generated successfully!');
});
Route::get('/clear-cache', function () {
    Artisan::call('optimize:clear');
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    return redirect()->back()->with('message', 'Cache cleared successfully.');
});
Route::get('/version', function () {

    return app()->version();
});

// Cloud Run deployment (docs/CLOUD_RUN_DEPLOYMENT.md): minimal health check, no auth/DB dependency, no
// sensitive data - Laravel 10 has no built-in equivalent (the `/up` route is a Laravel 11+ feature).
Route::get('/up', function () {
    return response()->json(['status' => 'ok']);
});

Route::get('storage-link', function () {
    Artisan::call('storage:link');
});

Route::get('/install', [InstallerController::class, 'index'])->middleware('guest');

Route::post('/installer/config-db', [InstallerController::class, 'config_db'])->middleware('guest');

Route::post('/installer/install', [InstallerController::class, 'install'])->middleware('guest');

Route::get('admin/web_product_card_style', [StoreController::class, 'webProductCardStyle']);
Route::get('admin/web_categories_style', [StoreController::class, 'webCategoriesStyle']);
Route::get('admin/web_brands_style', [StoreController::class, 'webBrandsStyle']);
Route::get('admin/web_wishlist_style', [StoreController::class, 'webWishlistStyle']);
Route::get('admin/web_home_page_theme', [StoreController::class, 'webHomePageTheme']);

Route::get('/manifest', function () {
    return response()->json(config('manifest'));
})->name('manifest');

Route::middleware(['CheckInstallation'])->group(function () {
    Route::get('/', [CustomerHomeController::class, 'index'])->name('home');
    Route::get('admin/register', [UserController::class, 'create']);

    Route::post('admin/users', [UserController::class, 'store']);

    Route::get('admin/logout', [UserController::class, 'logout'])->name('admin.logout');

    Route::post('/admin/users/authenticate', [UserController::class, 'authenticate'])->name('admin.authenticate');

    Route::get('admin/login', [UserController::class, 'login'])->name('admin.login');

    // ->middleware('auth'): registered here (inside CheckInstallation but outside the `auth`-gated
    // admin_routes.php include below) with no auth check at all - confirmed via a real deploy, an
    // unauthenticated visit to this URL reaches HomeController::index(), whose view assumes a logged-in
    // admin session and fatal-errors without one instead of redirecting to login.
    Route::get('admin/home', [HomeController::class, 'index'])->name('admin.home')->middleware('auth');

    // Routs for forgot password and reset password

    Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password-mail', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [ForgotPasswordController::class, 'ResetPassword'])->name('admin.password.update');

    Route::get('/admin', function () {
        if (Auth::check()) {
            // User is logged in, redirect to the admin home page
            return redirect()->route('admin.home');
            // return redirect()->route('admin.login');
        } else {
            // User is not logged in, redirect to the admin login page
            return redirect()->route('admin.login');
        }
    });



    // seller routes
    Route::get('/seller', function () {
        return redirect()->route('seller.login');
    });

    Route::get('seller/login', [UserController::class, 'seller_login'])->name('seller.login');
    Route::get('seller/register', [UserController::class, 'seller_register'])->name('seller.register');
    Route::get('seller/get_zones', [AreaController::class, 'get_zones'])->name('seller.get_zones');
    Route::get('seller/logout', [UserController::class, 'seller_logout'])->name('seller.logout');
    Route::get('seller/categories/get_category_details', [CategoryController::class, 'getCategoryDetails']);
    Route::post('seller/store', [UserController::class, 'sellerStore'])->name('seller.register.store')->middleware(['demo_restriction']);

    // delivery boy routes
    Route::get('/delivery_boy', function () {
        return redirect()->route('delivery_boy.login');
    });

    Route::get('delivery_boy/login', [UserController::class, 'delivery_boy_login'])->name('delivery_boy.login');
    Route::get('delivery_boy/logout', [UserController::class, 'delivery_boy_logout'])->name('delivery_boy.logout');

    // wholesaler routes (SaaS re-architecture brief - see database/migrations/2025_02_21_000000_create_wholesaler_module.php)
    Route::get('/wholesaler', function () {
        return redirect()->route('wholesaler.login');
    });

    Route::get('wholesaler/login', [\App\Http\Controllers\Wholesaler\AuthController::class, 'login'])->name('wholesaler.login');
    Route::get('wholesaler/register', [\App\Http\Controllers\Wholesaler\AuthController::class, 'register'])->name('wholesaler.register');
    Route::post('wholesaler/authenticate', [\App\Http\Controllers\Wholesaler\AuthController::class, 'authenticate'])->name('wholesaler.authenticate');
    Route::post('wholesaler/store', [\App\Http\Controllers\Wholesaler\AuthController::class, 'store'])->name('wholesaler.register.store')->middleware(['demo_restriction']);
    Route::get('wholesaler/logout', [\App\Http\Controllers\Wholesaler\AuthController::class, 'logout'])->name('wholesaler.logout');

    // affiliate portal - unlike the panels above, open to any active user (a customer or a seller can both
    // be an affiliate), so its own AffiliateAuthController::authenticate() doesn't branch on role.
    Route::get('affiliate/login', [\App\Http\Controllers\AffiliateAuthController::class, 'login'])->name('affiliate.login');
    Route::post('affiliate/authenticate', [\App\Http\Controllers\AffiliateAuthController::class, 'authenticate'])->name('affiliate.authenticate');
    Route::get('affiliate/logout', [\App\Http\Controllers\AffiliateAuthController::class, 'logout'])->name('affiliate.logout');



    // system policies pages
    Route::get("settings/seller_privacy_policy", [SettingController::class, 'sellerPrivacyPolicy'])->name('seller_privacy_policy.view');

    Route::get("admin/privacy_policy/privacy_policy_page", [SettingController::class, 'privacy_policy'])->name('privacy_policy.view');

    Route::get("admin/terms_and_conditions/terms_and_condition_page", [SettingController::class, 'terms_and_conditions'])->name('terms_and_conditions.view');

    Route::get("admin/shipping_policy/shipping_policy_page", [SettingController::class, 'shipping_policy'])->name('shipping_policy.view');

    Route::get("admin/return_policy/return_policy_page", [SettingController::class, 'return_policy'])->name('return_policy.view');

    //admin & seller policies page

    Route::get("admin/privacy_policy/seller_privacy_policy_page", [SettingController::class, 'seller_privacy_policy']);

    Route::get("admin/terms_and_condition/seller_terms_and_condition_page", [SettingController::class, 'seller_terms_and_condition'])->name('seller_terms_and_conditions.view');

    // delivery boy policies page

    Route::get("admin/privacy_policy/delivery_boy_privacy_page", [SettingController::class, 'delivery_boy_privacy_policy'])->name('delivery_boy_privacy_policy.view');

    Route::get("admin/terms_and_conditions/delivery_boy_terms_and_condition_page", [SettingController::class, 'delivery_boy_terms_and_conditions'])->name('delivery_boy_terms_and_conditions.view');

    // admin routes file

    Route::group(['middleware' => ['auth']], function () {
        // Routes that only admins can access
        // Phase 2 (docs/PHASE_2_IDOR_AUDIT.md, Tasks 8-9): include_once() only executes a file's top-level
        // code on its FIRST load within a PHP process. Traditional PHP-FPM (one process per request) never
        // notices, but any persistent-process context that boots this application more than once in the
        // same process - PHPUnit running this file's own regression tests, Laravel Octane, a queue worker -
        // does: every route in admin_routes.php/seller_routes.php/delivery_boy_routes.php silently vanishes
        // from every Application boot after the first in that process. Found while writing a test that
        // called route('seller.orders...') and got RouteNotFoundException only when run after another test
        // in the same run had already triggered these includes once. include() re-executes every time, as
        // intended here (each fresh Application boot should re-register these routes).
        include("admin_routes.php");
        include("seller_routes.php");
        include("delivery_boy_routes.php");
        include("wholesaler_routes.php");

        // Phase 7 (docs/PHASE_7_AFFILIATE_ENGINE.md): self-service affiliate links - any authenticated
        // user, not scoped to one panel the way the includes above are.
        Route::get('affiliate/links', [AffiliateController::class, 'list'])->name('affiliate.links.list');
        Route::post('affiliate/links', [AffiliateController::class, 'store'])->name('affiliate.links.store');
        Route::get('affiliate/dashboard', [AffiliateController::class, 'dashboard'])->name('affiliate.dashboard');

        // Changelog v1.0.7 ("Generate unique product referral links"): a minimal product search for the
        // affiliate portal's own link-generation widget - this repo has no customer-facing web storefront
        // to browse products from otherwise.
        Route::get('affiliate/products/search', [AffiliateController::class, 'searchProducts'])->name('affiliate.products.search');

        // Changelog v1.0.7 ("Admin can process affiliate payouts"): affiliate-facing withdrawal
        // self-service, mirroring Seller\PaymentRequestController's pattern.
        Route::post('affiliate/withdrawal', [AffiliateController::class, 'requestWithdrawal'])->name('affiliate.withdrawal.request')->middleware(['demo_restriction']);
        Route::get('affiliate/withdrawal/history', [AffiliateController::class, 'withdrawalHistory'])->name('affiliate.withdrawal.history');

        // Per-conversion breakdown behind the dashboard's aggregate approved/pending totals - an affiliate
        // asking "which order paid me what" has had no way to see that beyond the two summary numbers.
        Route::get('affiliate/conversions', [AffiliateController::class, 'conversionsHistory'])->name('affiliate.conversions.list');

        // Seller-managed product catalog (2025_02_09_000000 migration) - ready-to-copy links for every
        // commission-enabled product, plus the private-store request/approval flow behind it.
        Route::get('affiliate/available_products', [AffiliateController::class, 'availableProducts'])->name('affiliate.available_products.list');
        Route::get('affiliate/stores', [AffiliateController::class, 'browsableStores'])->name('affiliate.stores.list');
        Route::post('affiliate/stores/request', [AffiliateController::class, 'requestStoreAccess'])->name('affiliate.stores.request')->middleware(['demo_restriction']);

        // Multi-page portal shell (sidebar, matching the admin/seller/delivery_boy panels) - each of these
        // just renders a Blade page; the JSON endpoints above feed them via fetch(). affiliate/products/
        // {product} must stay registered after affiliate/products/search above, or "search" would match the
        // {product} wildcard instead.
        Route::get('affiliate/products', [AffiliateController::class, 'productsPage'])->name('affiliate.products.page');
        Route::get('affiliate/products/{product}', [AffiliateController::class, 'productShow'])->name('affiliate.product.show');
        Route::get('affiliate/commissions', [AffiliateController::class, 'commissionsPage'])->name('affiliate.commissions.page');
        Route::get('affiliate/withdrawals', [AffiliateController::class, 'withdrawalsPage'])->name('affiliate.withdrawals.page');
        Route::get('affiliate/private_stores', [AffiliateController::class, 'storesPage'])->name('affiliate.stores.page');
    });

    // Public - the link a visitor actually clicks, no account required to be tracked and redirected.
    // Security audit finding (docs/SECURITY_AUDIT.md §6, Finding 12): unthrottled, this endpoint let anyone
    // script arbitrary clicks_count inflation for any affiliate link (gaming performance metrics) or grow
    // link_clicks without bound. 60/minute per IP is generous for a real visitor clicking a link, not for a
    // script.
    Route::get('r/{code}', [AffiliateController::class, 'trackAndRedirect'])->name('affiliate.track')->middleware('throttle:60,1');

    Route::get('admin/media/image', [MediaController::class, 'dynamic_image'])->name('admin.dynamic_image');
    Route::get('/media/image', [MediaController::class, 'dynamic_image'])->name('front_end.dynamic_image');

    // media route

    Route::get('/admin/media/list', [MediaController::class, 'list'])->name('admin.media.list');

    Route::get('/seller/media/list', [SellerMediaController::class, 'list']);

    include("front_end_routes.php");

    //webhook route

    // Razorpay/Paystack/PhonePe all deliver webhooks as a POST with a JSON body (read via php://input in
    // each handler) - these were registered as GET, so any real webhook call from the gateway would have
    // hit Laravel's routing as a 405 Method Not Allowed before ever reaching the handler. Fixed to POST;
    // stripe_webhook was already correctly POST.
    Route::post('admin/webhook/razorpay_webhook', [Webhook::class, 'razorpay_webhook'])->name('admin.razorpay_webhook');
    Route::post('admin/webhook/paystack_webhook', [Webhook::class, 'paystack_webhook'])->name('admin.paystack_webhook');
    Route::post('admin/webhook/stripe_webhook', [Webhook::class, 'stripe_webhook'])->name('admin.stripe_webhook');
    Route::post('admin/webhook/phonepe_webhook', [Webhook::class, 'phonepe_webhook'])->name('admin.phonepe_webhook');
    // Shiprocket delivers this webhook as a POST with a JSON body (see Webhook::spr_webhook() for the
    // token-verification + tracking-sync logic that used to be entirely missing here) - was registered as
    // GET, same bug class as the three payment gateways above; a real call would have 405'd.
    Route::post('admin/webhook/spr_webhook', [Webhook::class, 'spr_webhook'])->name('admin.spr_webhook');
});
// Phase 2 (docs/PHASE_2_IDOR_AUDIT.md, Tasks 8-9): this used to duplicate
// admin_routes.php:541's route (identical name, URI, and method), registered outside any auth group. Since
// Laravel's RouteCollection keys routes by method+URI, this later registration silently replaced the
// properly `auth`+`role:super_admin,admin,editor`-gated one entirely - route:list showed only one entry,
// with `web` as its only middleware. Confirmed: any unauthenticated visitor could fetch any order's
// invoice PDF (customer name, address, mobile number, items, pricing) by guessing an order id. Removed
// here; the correctly-gated declaration in admin_routes.php:541 is now the only registration.
// admin.stores.index / admin.stores.store: previously duplicated here unguarded (same
// RouteCollection-keyed-by-method+URI shadowing as the invoice-PDF bug noted above) over the properly
// `auth`+`role:super_admin,admin,editor`-gated `Route::resource("admin/store", ...)` declaration in
// admin_routes.php, which was commented out - confirmed via a real deploy: any visitor (including an
// unauthenticated one) hitting `/admin/stores` reached this route, whose view (via x-admin.header /
// x-admin.side-bar) assumes an authenticated admin session and fatal-errors without one. Removed here;
// admin_routes.php's declarations (now uncommented) are the only registration.
// admin.system_registration / admin.system_register: previously duplicated here unguarded (same
// RouteCollection-keyed-by-method+URI shadowing as the invoice-PDF bug noted above) over the properly
// `auth`+`role:super_admin,admin,editor`-gated declarations in admin_routes.php, which were commented out
// - confirmed via a real deploy: any visitor (including an unauthenticated one) hitting `/settings/
// registration` reached this route, whose view assumes an authenticated admin session and fatal-errors
// without one. Removed here; admin_routes.php's declarations (now uncommented) are the only registration.
Route::post("settings/web_system_registration", [SettingController::class, 'WebsystemRegister'])->name('admin.web_system_register')->middleware(['demo_restriction']);
