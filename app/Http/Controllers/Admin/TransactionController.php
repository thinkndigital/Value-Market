<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Traits\HandlesValidation;
class TransactionController extends Controller
{
    use HandlesValidation;
    public function store(Request $request)
    {

        $transaction_type = empty($request['transaction_type']) ? 'transaction' : $request['transaction_type'];

        $trans_data = [
            'transaction_type' => $transaction_type,
            'user_id' => $request['user_id'] ?? null,
            'order_id' => $request['order_id'] ?? '',
            'order_item_id' => $request['order_item_id'] ?? null,
            'type' => strtolower($request['type'] ?? ''),
            'txn_id' => $request['txn_id'] ??  '',
            'amount' => $request['amount'] ?? 0,
            'status' => $request['status'] ?? '',
            'message' => $request['message'] ?? '',
        ];

        Transaction::create($trans_data);
    }

    public function get_transactions($id = '', $userId = '', $transaction_type = '', $search = '', $offset = 0, $limit = 25, $sort = 'id', $order = 'DESC', $type = '')
    {
        $query = Transaction::query();

        if (!empty($userId)) {
            $query->where('user_id', $userId);
        }

        if ($transaction_type !== '') {
            $query->where('transaction_type', $transaction_type);
        }

        if ($type !== '') {
            $query->where('type', $type);
        }

        if ($id !== '') {
            $query->where('id', $id);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->orWhere('id', 'LIKE', "%{$search}%")
                    ->orWhere('transaction_type', 'LIKE', "%{$search}%")
                    ->orWhere('type', 'LIKE', "%{$search}%")
                    ->orWhere('order_id', 'LIKE', "%{$search}%")
                    ->orWhere('txn_id', 'LIKE', "%{$search}%")
                    ->orWhere('amount', 'LIKE', "%{$search}%")
                    ->orWhere('status', 'LIKE', "%{$search}%")
                    ->orWhere('message', 'LIKE', "%{$search}%")
                    ->orWhere('transaction_date', 'LIKE', "%{$search}%")
                    ->orWhere('created_at', 'LIKE', "%{$search}%");
            });
        }

        // Clone query to get total count
        $total_count = (clone $query)->count();

        // Fetch paginated transactions
        $transactions = $query->orderBy($sort, $order)
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Format results
        $formatted = $transactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'user_id' => $transaction->user_id,
                'transaction_type' => $transaction->transaction_type,
                'type' => $transaction->type,
                'order_id' => $transaction->order_id ?? '',
                'order_item_id' => $transaction->order_item_id ?? '',
                'txn_id' => $transaction->txn_id ?? '',
                'payu_txn_id' => $transaction->payu_txn_id ?? '',
                'amount' => $transaction->amount,
                'status' => $transaction->status ?? '',
                'message' => $transaction->message,
                'currency_code' => $transaction->currency_code ?? '',
                'payer_email' => $transaction->payer_email ?? '',
                'transaction_date' => $transaction->transaction_date,
                'is_refund' => $transaction->is_refund,
                'created_at' => Carbon::parse($transaction->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::parse($transaction->updated_at)->format('Y-m-d H:i:s'),
            ];
        });

        return [
            'data' => $formatted,
            'total' => $total_count,
        ];
    }

    public function edit_transactions(Request $request)
    {

        $rules = [
            'status' => 'required',
            'txn_id' => 'required',
            'id' => 'required|exists:transactions,id',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }
        // Retrieve the current status of the transaction
        $currentStatus = Transaction::where('id', $request->id)->value('status');

        // Check if the new status is greater than or equal to the current status
        $statuses = ['awaiting', 'success', 'failed'];
        if (array_search($currentStatus, $statuses) <= array_search($request->status, $statuses)) {
            $t_data = [
                'id' => $request->id,
                'status' => $request->status,
                'txn_id' => $request->txn_id,
                'message' => $request->message,
            ];
            if (updateDetails($t_data, ['id' => $request->id], Transaction::class)) {
                return response()->json([
                    'error' => false,
                    'message' => labels('admin_labels.transaction_updated_successfully', 'Transaction Updated Successfully')
                ]);
            } else {
                return response()->json([
                    'errors' => true,
                    'message' => labels('admin_labels.something_went_wrong', 'Something went wrong')
                ]);
            }
        } else {
            return response()->json([
                'errors' => true,
                'message' => labels('admin_labels.can_not_update_to_lower_status', 'Cannot update to a lower status.')
            ]); // HTTP status code 422 for Unprocessable Entity
        }
    }
}
