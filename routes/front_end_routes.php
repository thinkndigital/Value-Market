<?php

use App\Http\Controllers\Customer\AccountController;
use App\Http\Controllers\Customer\AuthController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\ProductController;
use Illuminate\Support\Facades\Route;

/*
 * Customer storefront (v1) - single global marketplace, one default theme, login-gated cart/checkout/account.
 * '/' (home) is registered separately in web.php (it has to run before this file loads, see that file's own
 * comment). Everything here inherits the outer CheckInstallation group's 'web' middleware group from web.php,
 * so browsing stays public by default; only cart/checkout/account routes add the 'customer.auth' gate below.
 */

Route::get('products', [ProductController::class, 'index'])->name('customer.products');
Route::get('products/{slug}', [ProductController::class, 'show'])->name('customer.product.show');

Route::get('login', [AuthController::class, 'showLogin'])->name('customer.login');
Route::post('login', [AuthController::class, 'login'])->name('customer.login.submit');
Route::get('register', [AuthController::class, 'showRegister'])->name('customer.register');
Route::post('register', [AuthController::class, 'register'])->name('customer.register.submit');
Route::post('logout', [AuthController::class, 'logout'])->name('customer.logout');

Route::middleware(['customer.auth'])->group(function () {
    Route::get('cart', [CartController::class, 'index'])->name('customer.cart');
    Route::post('cart/add', [CartController::class, 'add'])->name('customer.cart.add');
    Route::post('cart/remove', [CartController::class, 'remove'])->name('customer.cart.remove');

    Route::get('checkout', [CheckoutController::class, 'index'])->name('customer.checkout');
    Route::post('checkout', [CheckoutController::class, 'store'])->name('customer.checkout.store');

    Route::get('my-account', [AccountController::class, 'index'])->name('customer.account');
    Route::get('my-account/orders', [AccountController::class, 'orders'])->name('customer.account.orders');
});
