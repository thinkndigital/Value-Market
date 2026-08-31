<?php

use App\Http\Controllers\Seller\PaymentRequestController;
use App\Http\Controllers\Wholesaler\ClientController;
use App\Http\Controllers\Wholesaler\FinanceController;
use App\Http\Controllers\Wholesaler\HomeController;
use App\Http\Controllers\Wholesaler\SellerRequestController;
use App\Http\Controllers\Wholesaler\OrderController;
use App\Http\Controllers\Wholesaler\PricingController;
use App\Http\Controllers\Wholesaler\ProductController;
use App\Http\Controllers\Wholesaler\ReportController;
use App\Http\Controllers\Wholesaler\StockController;
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

        // Orders (v2 - docs/WHOLESALER_MODULE.md): the incoming purchase-order queue + POS-style quick
        // "create order on a seller's behalf" entry point.
        Route::get('wholesaler/orders', [OrderController::class, 'index'])->name('wholesaler.orders.index');
        Route::get('wholesaler/orders/list', [OrderController::class, 'list'])->name('wholesaler.orders.list');
        Route::get('wholesaler/orders/{id}/transition', [OrderController::class, 'transition'])->name('wholesaler.orders.transition')->middleware(['demo_restriction']);
        Route::get('wholesaler/orders/{id}/mark_paid', [OrderController::class, 'markPaid'])->name('wholesaler.orders.mark_paid')->middleware(['demo_restriction']);
        Route::get('wholesaler/orders/create', [OrderController::class, 'createPage'])->name('wholesaler.orders.create');
        Route::post('wholesaler/orders', [OrderController::class, 'store'])->name('wholesaler.orders.store')->middleware(['demo_restriction']);

        // Wholesale Pricing (master architecture Phase 6, section 18): quantity-break / seller-specific
        // pricing tiers on the wholesaler's own listings - see WholesalerProduct::priceFor().
        Route::get('wholesaler/pricing', [PricingController::class, 'index'])->name('wholesaler.pricing.index');
        Route::get('wholesaler/pricing/{productId}/tiers', [PricingController::class, 'tiersList'])->name('wholesaler.pricing.tiers.list');
        Route::post('wholesaler/pricing/{productId}/tiers', [PricingController::class, 'store'])->name('wholesaler.pricing.tiers.store')->middleware(['demo_restriction']);
        Route::delete('wholesaler/pricing/{productId}/tiers/{tierId}', [PricingController::class, 'destroy'])->name('wholesaler.pricing.tiers.destroy')->middleware(['demo_restriction']);

        // Stock ("مخزون")
        Route::get('wholesaler/stock', [StockController::class, 'index'])->name('wholesaler.stock.index');
        Route::get('wholesaler/stock/list', [StockController::class, 'list'])->name('wholesaler.stock.list');
        Route::post('wholesaler/stock/{id}/adjust', [StockController::class, 'adjust'])->name('wholesaler.stock.adjust')->middleware(['demo_restriction']);

        // Sales report ("مبيعات")
        Route::get('wholesaler/reports/sales', [ReportController::class, 'index'])->name('wholesaler.reports.sales');

        // Wallet / Finance (master architecture Phase 6, section 65) - the wallet is credited when an
        // order is marked paid (OrderController::markPaid()); withdrawal reuses the exact same
        // PaymentRequest flow Seller/Delivery Boy already share, same pattern delivery_boy's own routes
        // use to call into Seller\PaymentRequestController.
        Route::get('wholesaler/wallet', [FinanceController::class, 'wallet'])->name('wholesaler.wallet.index');
        Route::get('wholesaler/wallet/transactions', [FinanceController::class, 'transactionList'])->name('wholesaler.wallet.transactions');
        Route::put('wholesaler/wallet/withdraw', function (\Illuminate\Http\Request $request) {
            return app(PaymentRequestController::class)->add_withdrawal_request($request, false, 'wholesaler');
        })->name('wholesaler.wallet.withdraw')->middleware(['demo_restriction']);

        // Seller Requests / marketplace visibility (master architecture Phase 6, section 18 "Sellers"
        // group) - a wholesaler can gate its marketplace listing behind approval, mirroring the seller's
        // own private-affiliate-store request flow one level up.
        Route::get('wholesaler/seller_requests', [SellerRequestController::class, 'index'])->name('wholesaler.seller_requests.index');
        Route::put('wholesaler/seller_requests/visibility', [SellerRequestController::class, 'updateVisibility'])->name('wholesaler.seller_requests.visibility')->middleware(['demo_restriction']);
        Route::put('wholesaler/seller_requests/respond', [SellerRequestController::class, 'respond'])->name('wholesaler.seller_requests.respond')->middleware(['demo_restriction']);

        // My Buyers ("عملاء" / CRM)
        Route::get('wholesaler/clients', [ClientController::class, 'index'])->name('wholesaler.clients.index');
        Route::get('wholesaler/clients/list', [ClientController::class, 'list'])->name('wholesaler.clients.list');

        // Language switcher (public/assets/admin/custom/custom.js's shared `.changeLang` handler builds
        // this URL from the current panel prefix, same as every other panel).
        Route::get('wholesaler/settings/languages/change', [\App\Http\Controllers\Delivery_boy\LanguageController::class, 'change'])->name('wholesaler.changeLang');
    }
);
