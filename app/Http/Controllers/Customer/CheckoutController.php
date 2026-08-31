<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\App\v1\ApiController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\CartService;
use App\Services\CurrencyService;
use App\Services\StoreService;
use App\Services\TranslationService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function index()
    {
        $userId = auth('web')->id();
        $storeId = app(StoreService::class)->getStoreId();

        $totals = app(CartService::class)->getCartTotal($userId, false, 0, '', $storeId);
        $addresses = Address::where('user_id', $userId)->get();

        return view('customer.checkout.index', [
            'totals' => $totals,
            'addresses' => $addresses,
        ]);
    }

    /**
     * Reuses App\v1\ApiController::place_order() wholesale (already audited and fixed in the Phase 21 API
     * sweep - no new checkout/payment logic here). COD only for v1, matching the plan's explicit scope
     * ("whichever gateway is already wired platform-wide is enough for v1; no new payment integration").
     */
    public function store(Request $request)
    {
        $request->validate([
            // Scoped to the logged-in customer's own addresses, not a bare exists:addresses,id - this
            // request is about to be handed to ApiController::place_order() as if it came from the mobile
            // app, so an address belonging to a different user must be rejected here rather than trusted.
            'address_id' => ['required', \Illuminate\Validation\Rule::exists('addresses', 'id')->where('user_id', auth('web')->id())],
        ]);

        $storeId = app(StoreService::class)->getStoreId();
        $currencyCode = app(CurrencyService::class)->getDefaultCurrency()->code ?? 'USD';

        $apiRequest = new Request(array_filter([
            'is_wallet_used' => 0,
            'store_id' => $storeId,
            'order_payment_currency_code' => $currencyCode,
            'status' => 'awaiting',
            'address_id' => $request->input('address_id'),
            'payment_method' => 'cod',
            // Master architecture prompt Phase 7 bug fix: the "last touch" affiliate_code
            // Customer\ProductController::show() stashed in session, so OrderService::placeOrder() can
            // attribute this sale - see that controller's own comment for why session, not query string.
            'affiliate_code' => session('affiliate_code'),
        ], fn ($value) => $value !== null));
        $apiRequest->attributes->set('language_code', app(TranslationService::class)->getLanguageCode());

        $original = app('request');
        app()->instance('request', $apiRequest);

        try {
            $response = app(ApiController::class)->place_order($apiRequest, app(TransactionController::class));
            $data = $response->getData(true);
        } finally {
            app()->instance('request', $original);
        }

        if (!empty($data['error'])) {
            return back()->with('error', is_array($data['message'] ?? null) ? implode(' ', $data['message']) : ($data['message'] ?? 'Could not place order.'));
        }

        return redirect()->route('customer.account.orders')->with('status', 'Order placed successfully!');
    }
}
