<?php

namespace App\Services;
use App\Models\User;
use App\Models\Transaction;
use App\Http\Controllers\Admin\TransactionController;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function getUserBalance($user_id)
    {
        $user = User::where('id', $user_id)->select('balance')->first();

        return $user ? $user->balance : 0;
    }

    /**
     * Phase 1 (docs/PHASE_1_TRANSACTION_BOUNDARIES.md): previously this read $user->balance, mutated it in
     * PHP, then called save() with no locking - two concurrent calls for the same user (e.g. two commission
     * payouts processed by overlapping requests) could both read the same starting balance and the second
     * save() would silently overwrite the first, losing an update. lockForUpdate() inside a transaction
     * makes this a real atomic read-modify-write.
     */
    public function updateBalance($amount, $deliveryBoyId, $action)
    {
        /**
         * action = add / deduct
         */

        return DB::transaction(function () use ($amount, $deliveryBoyId, $action) {
            $user = User::where('id', $deliveryBoyId)->lockForUpdate()->first();

            if (!$user) {
                return false; // User not found
            }

            if ($action == "add") {
                $user->balance += $amount;
            } else {
                $user->balance -= $amount;
            }
            return $user->save();
        });
    }

    /**
     * Phase 1: same read-modify-write race as updateBalance() above, fixed the same way.
     */
    public function updateCashReceived($amount, $deliveryBoyId, $action)
    {
        /**
         * action = add / deduct
         */

        return DB::transaction(function () use ($amount, $deliveryBoyId, $action) {
            $user = User::where('id', $deliveryBoyId)->lockForUpdate()->first();
            if (!$user) {
                return false; // User not found
            }

            if ($action == "add") {
                $user->cash_received += $amount;
            } elseif ($action == "deduct") {
                $user->cash_received -= $amount;
            }
            return $user->save();
        });
    }
    /**
     * Phase 1 (docs/PHASE_1_TRANSACTION_BOUNDARIES.md): the balance mutation (user->save()) and the
     * transaction-log write (transactionController->store()) used to be two separate, non-atomic writes -
     * if the transaction-log insert failed after the balance was already saved, the user's balance would
     * have changed with no transaction record explaining why (untraceable balance change). They are now
     * one atomic unit under a row lock, so either both happen or neither does, and two concurrent calls for
     * the same user can no longer race on the balance check/update. Business logic (validation order,
     * messages, the existing razorpay special-case) is unchanged from the original.
     */
    public function updateWalletBalance($operation, $user_id, $amount, $message = "Balance Debited", $order_item_id = "", $is_refund = 0, $transaction_type = 'wallet')
    {
        return DB::transaction(function () use ($operation, $user_id, $amount, $message, $order_item_id, $is_refund, $transaction_type) {
            $user = User::where('id', $user_id)->lockForUpdate()->first();

            if (!$user) {
                $response['error'] = true;
                $response['error_message'] = "User does not exist";
                $response['data'] = [];
                return $response;
            }

            if ($operation == 'debit' && $amount > $user->balance) {
                $response['error'] = true;
                $response['error_message'] = "Debited amount can't exceed the user balance!";
                $response['data'] = [];
                return $response;
            }

            if ($amount == 0) {
                $response['error'] = true;
                $response['error_message'] = "Amount can't be zero!";
                $response['data'] = [];
                return $response;
            }

            if ($user->balance >= 0) {
                $data = [
                    'transaction_type' => $transaction_type,
                    'user_id' => $user_id,
                    'type' => $operation,
                    'amount' => $amount,
                    'message' => $message,
                    'order_item_id' => $order_item_id,
                    'is_refund' => $is_refund,
                ];

                $payment_data = Transaction::where('order_item_id', $order_item_id)->pluck('type')->first();

                if ($operation == 'debit') {
                    $data['message'] = $message ?: 'Balance Debited';
                    $data['type'] = 'debit';
                    $data['status'] = 'success';
                    $user->balance -= $amount;
                } else if ($operation == 'credit') {
                    $data['message'] = $message ?? 'Balance Credited';
                    $data['type'] = 'credit';
                    $data['status'] = 'success';
                    $data['order_id'] = $order_item_id;
                    if ($payment_data != 'razorpay') {
                        $user->balance += $amount;
                    }
                } else {
                    $data['message'] = $message ?: 'Balance refunded';
                    $data['type'] = 'refund';
                    $data['status'] = 'success';
                    $data['order_id'] = $order_item_id;
                    if ($payment_data != 'razorpay') {
                        $user->balance += $amount;
                    }
                }

                $user->save();

                $request = new \Illuminate\Http\Request($data);
                $transactionController = app(TransactionController::class);

                $transactionController->store($request);
                $response['error'] = false;
                $response['message'] = "Balance Update Successfully";
                $response['data'] = [];
            } else {
                $response['error'] = true;
                $response['error_message'] = ($user->balance != 0) ? "User's Wallet balance less than {$user->balance} can be used only" : "Doesn't have sufficient wallet balance to proceed further.";
                $response['data'] = [];
            }

            return $response;
        });
    }
}