<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Controller;
use App\Services\ProductService;
use App\Services\StoreService;
use App\Services\TranslationService;

class HomeController extends Controller
{
    /**
     * Storefront home page. Reuses the same CategoryController::get_categories()/ProductService::fetchProduct()
     * calls the mobile API's get_categories()/get_products() endpoints already use (Phase 21 API audit
     * confirmed both are healthy) - no new query logic, just a Blade page calling the existing service layer.
     */
    public function index(CategoryController $categoryController)
    {
        $storeId = app(StoreService::class)->getStoreId();
        $languageCode = app(TranslationService::class)->getLanguageCode();

        $categoriesResponse = $categoryController->get_categories(null, 12, 0, 'row_order', 'ASC', 'false', '', '', '', $storeId, '', '', $languageCode, true);
        $categories = $categoriesResponse->original['categories'] ?? collect();

        $products = app(ProductService::class)->fetchProduct(
            auth()->id(),
            null,
            null,
            null,
            12,
            0,
            'products.id',
            'DESC',
            null,
            null,
            null,
            null,
            $storeId,
            0,
            '',
            0,
            $languageCode
        );

        return view('customer.home', [
            'categories' => $categories,
            'products' => $products['product'] ?? collect(),
        ]);
    }
}
