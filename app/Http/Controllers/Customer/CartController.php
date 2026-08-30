<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\App\v1\ApiController;
use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Services\StoreService;
use App\Services\TranslationService;
use Illuminate\Http\Request;

/**
 * Thin storefront wrapper over the existing cart layer - no new cart business logic. add()/remove() forward
 * into App\v1\ApiController::manage_cart()/remove_from_cart() (already audited and fixed in the Phase 21 API
 * sweep). Those methods read fields via the global request()/auth() helpers rather than their injected
 * $request parameter, so callWithRebuiltRequest() temporarily swaps the container's bound 'request' instance
 * for a request shaped the way the API layer expects, then restores the real one - the same request the
 * mobile app would have sent, just built here instead of parsed from a client. auth()/session state is
 * untouched, so the already-authenticated web-session customer is recognized exactly as-is.
 * index() reads via the same read-only CartController::get_user_cart()/CartService::getCartTotal() calls
 * the checkout page also uses.
 */
class CartController extends Controller
{
    public function index()
    {
        $userId = auth('web')->id();
        $storeId = app(StoreService::class)->getStoreId();
        $languageCode = app(TranslationService::class)->getLanguageCode();

        $items = app(\App\Http\Controllers\CartController::class)->get_user_cart($userId, 0, '', $storeId, $languageCode);
        $totals = app(CartService::class)->getCartTotal($userId, false, 0, '', $storeId);

        return view('customer.cart.index', [
            'items' => $items,
            'totals' => $totals,
        ]);
    }

    public function add(Request $request)
    {
        $data = $this->callWithRebuiltRequest([
            'product_variant_id' => $request->input('product_variant_id'),
            'qty' => $request->input('qty', 1),
            'store_id' => app(StoreService::class)->getStoreId(),
            'product_type' => 'regular',
        ], fn(Request $apiRequest) => app(ApiController::class)->manage_cart($apiRequest, app(\App\Http\Controllers\CartController::class)));

        return back()->with(($data['error'] ?? true) ? 'error' : 'status', $data['message'] ?? 'Could not update cart.');
    }

    public function remove(Request $request)
    {
        $data = $this->callWithRebuiltRequest([
            'product_variant_id' => $request->input('product_variant_id'),
            'store_id' => app(StoreService::class)->getStoreId(),
            'product_type' => 'regular',
            'is_saved_for_later' => 0,
        ], fn(Request $apiRequest) => app(ApiController::class)->remove_from_cart($apiRequest));

        return back()->with(($data['error'] ?? true) ? 'error' : 'status', $data['message'] ?? 'Could not update cart.');
    }

    /**
     * Builds a Request shaped like the mobile API expects, binds it as the app's current request (so the
     * legacy controller's request()/attributes helper calls resolve against it), runs $callback, then
     * restores the real request - regardless of outcome.
     */
    private function callWithRebuiltRequest(array $payload, \Closure $callback): array
    {
        $apiRequest = new Request($payload);
        $apiRequest->attributes->set('language_code', app(TranslationService::class)->getLanguageCode());

        $original = app('request');
        app()->instance('request', $apiRequest);

        try {
            $response = $callback($apiRequest);
            return $response->getData(true);
        } finally {
            app()->instance('request', $original);
        }
    }
}
