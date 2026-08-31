<?php

namespace App\Http\Controllers\Wholesaler;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Master architecture prompt Phase 6 (Supplier architecture, section 65 "Finance": Wallet/Transactions/
 * Withdrawals) - reuses the exact same wallet infrastructure every other role runs on (`users.balance` +
 * `Transaction` rows via App\Services\WalletService, and the existing `PaymentRequest` withdrawal flow
 * already shared across Seller/Delivery Boy - see routes/wholesaler_routes.php for how withdrawal
 * submission is wired the same way delivery_boy's routes already reuse Seller\PaymentRequestController).
 * A wholesaler's wallet is credited when it marks an order paid (Wholesaler\OrderController::markPaid()).
 */
class FinanceController extends Controller
{
    public function wallet()
    {
        $user_id = Auth::id();
        $wallet_balance = Auth::user()->balance;

        return view('wholesaler.pages.views.finance.wallet', compact('user_id', 'wallet_balance'));
    }

    public function transactionList(Request $request)
    {
        $user_id = Auth::id();
        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');

        $query = Transaction::where('user_id', $user_id)->whereIn('type', ['credit', 'debit']);

        $total = (clone $query)->count();
        $rows = $query->select('*')->orderBy($sort, $order)->skip($offset)->take($limit)->get()->map(function ($row) {
            return [
                'id' => $row->id,
                'type' => $row->type,
                'amount' => $row->amount,
                'message' => $row->message,
                'status' => $row->status,
                'created_at' => Carbon::parse($row->created_at)->format('Y-m-d H:i'),
            ];
        });

        return response()->json(['total' => $total, 'rows' => $rows]);
    }
}
