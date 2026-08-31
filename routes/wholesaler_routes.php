<?php

use App\Http\Controllers\Wholesaler\HomeController;
use App\Http\Controllers\Wholesaler\ProductController;
use Illuminate\Support\Facades\Route;

Route::group(
    ['middleware' => ['auth', 'role:wholesaler', 'CheckPurchaseCode']],
    function () {
        Route::get('wholesaler/home', [HomeController::class, 'index'])->name('wholesaler.home');

        Route::get('wholesaler/products', [ProductController::class, 'index'])->name('wholesaler.products.index');
        Route::get('wholesaler/products/list', [ProductController::class, 'list'])->name('wholesaler.products.list');
        Route::post('wholesaler/products', [ProductController::class, 'store'])->name('wholesaler.products.store')->middleware(['demo_restriction']);
        Route::post('wholesaler/products/{id}', [ProductController::class, 'store'])->name('wholesaler.products.update')->middleware(['demo_restriction']);
        Route::get('wholesaler/products/{id}/edit', [ProductController::class, 'edit'])->name('wholesaler.products.edit');
        Route::delete('wholesaler/products/{id}', [ProductController::class, 'destroy'])->name('wholesaler.products.destroy')->middleware(['demo_restriction']);

        // Language switcher (public/assets/admin/custom/custom.js's shared `.changeLang` handler builds
        // this URL from the current panel prefix, same as every other panel).
        Route::get('wholesaler/settings/languages/change', [\App\Http\Controllers\Delivery_boy\LanguageController::class, 'change'])->name('wholesaler.changeLang');
    }
);
