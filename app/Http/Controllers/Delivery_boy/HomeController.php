<?php

namespace App\Http\Controllers\Delivery_boy;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{

    public function index()
    {
        $currencyDetails = fetchDetails(Currency::class, ['is_default' => 1], 'symbol');
        $currency = !$currencyDetails->isEmpty() ? $currencyDetails[0]->symbol : '';
        $deliveryBoyId = Auth::id();

        $user_res = fetchDetails(User::class, ['id' => $deliveryBoyId], ['balance', 'bonus']);
        $bonus = $user_res[0]->bonus;
        $balance = $user_res[0]->balance;

        return view('delivery_boy.pages.forms.home', compact('currency', 'deliveryBoyId', 'bonus', 'balance'));
    }
}
