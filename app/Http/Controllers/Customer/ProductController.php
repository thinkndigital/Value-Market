<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use App\Services\StoreService;
use App\Services\TranslationService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Product listing - reuses ProductService::fetchProduct() (the same call
     * App\v1\ApiController::get_products() already makes, confirmed healthy by the Phase 21 API audit).
     */
    public function index(Request $request)
    {
        $storeId = app(StoreService::class)->getStoreId();
        $languageCode = app(TranslationService::class)->getLanguageCode();
        $limit = 24;
        $offset = (max((int) $request->input('page', 1), 1) - 1) * $limit;

        $filters = [];
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $filters['search'] = $search;
        }
        $categoryId = $request->filled('category_id') ? [(int) $request->input('category_id')] : null;

        $result = app(ProductService::class)->fetchProduct(
            auth()->id(),
            !empty($filters) ? $filters : null,
            null,
            $categoryId,
            $limit,
            $offset,
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

        return view('customer.products.index', [
            'products' => $result['product'] ?? collect(),
            'total' => $result['total'] ?? 0,
            'page' => max((int) $request->input('page', 1), 1),
            'perPage' => $limit,
            'search' => $search,
        ]);
    }

    /**
     * Product detail - reuses the same ProductService::fetchProduct($id=...) call the mobile API's
     * get_products()/get_product_details() path already makes.
     */
    public function show($slug)
    {
        $storeId = app(StoreService::class)->getStoreId();
        $languageCode = app(TranslationService::class)->getLanguageCode();

        $product = \App\Models\Product::where('slug', $slug)->where('status', 1)->firstOrFail();

        $result = app(ProductService::class)->fetchProduct(
            auth()->id(),
            null,
            $product->id,
            null,
            1,
            0,
            'products.id',
            'DESC',
            null,
            null,
            null,
            null,
            $storeId,
            1,
            '',
            0,
            $languageCode
        );

        $productData = collect($result['product'] ?? [])->first();

        abort_if(!$productData, 404);

        return view('customer.products.show', ['product' => $productData]);
    }
}
