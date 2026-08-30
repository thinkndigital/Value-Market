<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Services\TranslationService;

class AccountController extends Controller
{
    public function index()
    {
        return view('customer.account.index', ['user' => auth('web')->user()]);
    }

    /**
     * Reuses OrderService::fetchOrders() - the same call the mobile API's order-history endpoints already
     * make, scoped to the logged-in customer via $user_id.
     */
    public function orders()
    {
        $languageCode = app(TranslationService::class)->getLanguageCode();

        $result = app(OrderService::class)->fetchOrders(
            null,
            auth('web')->id(),
            null,
            null,
            20,
            0,
            'o.id',
            'DESC',
            false,
            null,
            null,
            null,
            null,
            null,
            null,
            '',
            false,
            null,
            $languageCode
        );

        return view('customer.account.orders', ['orders' => $result['order_data'] ?? collect()]);
    }
}
