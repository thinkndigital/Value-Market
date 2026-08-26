<?php

namespace App\Http\Controllers\seller;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\HandlesValidation;
use App\Services\WalletService;

class PaymentRequestController extends Controller
{
    use HandlesValidation;
    public function withdrawal_requests()
    {
        $user_id = Auth::user()->id;
        return view('seller.pages.tables.withdrawal_request', compact('user_id'));
    }

    public function get_payment_request_list(Request $request, $user_id = null)
    {
        $user_id = $user_id ?? Auth::id();

        $search = trim($request->input('search', ''));
        $offset = $search || request('pagination_offset') ? request('pagination_offset') : 0;
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $userFilter = $request->input('user_filter');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $status = $request->input('payment_request_status');

        // Base query with Eloquent and eager loading
        $query = PaymentRequest::with('user');

        // Filters
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('id', $search)
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('username', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%");
                    });
            });
        }

        if ($startDate && $endDate) {
            $query->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate);
        }

        if (isset($status)) {
            $query->where('status', intval($status));
        }

        if (!empty($userFilter)) {
            $query->where('payment_type', $userFilter);
        }

        if (!empty($user_id)) {
            $query->where('user_id', $user_id);
        }

        $total = $query->count();

        $paymentRequests = $query->orderBy($sort, $order)
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Format the response
        $rows = $paymentRequests->map(function ($row) {
            $statusMap = [
                0 => '<span class="badge bg-success">Pending</span>',
                1 => '<span class="badge bg-primary">Approved</span>',
                2 => '<span class="badge bg-danger">Rejected</span>',
            ];

            return [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'user_name' => optional($row->user)->username,
                'payment_type' => $row->payment_type,
                'amount_requested' => $row->amount_requested,
                'remarks' => $row->remarks,
                'payment_address' => $row->payment_address,
                'date_created' => Carbon::parse($row->created_at)->format('d-m-Y'),
                'status_code' => $row->status,
                'status' => $statusMap[$row->status] ?? '',
            ];
        });

        return response()->json([
            'total' => $total,
            'rows' => $rows,
        ]);
    }


    public function add_withdrawal_request(Request $request, $fromDeliveryBoyApp = false)
    {

        $rules = [
            'user_id' => 'required|numeric|exists:users,id',
            'payment_address' => 'required',
            'amount' => 'required|numeric|gt:0',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }

        $user_id = $request->input('user_id');
        $payment_address = $request->input('payment_address');
        $amount = $request->input('amount');
        $userData = fetchDetails(User::class, ['id' => $user_id], 'balance');
        if (!empty($userData)) {
            if ($amount <= $userData[0]->balance) {
                $payment_type = $fromDeliveryBoyApp == true ? 'delivery_boy' : 'seller';
                $data = [
                    'user_id' => $user_id,
                    'payment_address' => $payment_address,
                    'payment_type' => $payment_type,
                    'amount_requested' => $amount,
                ];
                if (PaymentRequest::create($data)) {
                    $lastAddedRequest = PaymentRequest::latest()->first();
                    if ($lastAddedRequest) {
                        $data = $lastAddedRequest->toArray();

                        // Change the key from 'status' to 'status_code'
                        $data['status_code'] = $data['status'];
                        $data['date_created'] = Carbon::parse($data['created_at'])->format('d-m-Y');
                        $data['updated_at'] = Carbon::parse($data['updated_at'])->format('d-m-Y');
                        unset($data['status']);
                        unset($data['created_at']);
                    }
                    app(WalletService::class)->updateBalance($amount, $user_id, 'deduct');
                    $userData = fetchDetails(User::class, ['id' => $user_id], 'balance');
                    $response['error'] = false;
                    $response['message'] =
                        labels('admin_labels.withdrawal_request_sent_successfully', 'Withdrawal Request Sent Successfully');
                    $response['amount'] = $userData[0]->balance;
                    $response['data'] = $data;
                } else {
                    $response['error'] = true;
                    $response['message'] =
                        labels('admin_labels.cannot_send_withdrawal_request', "Cannot sent Withdrawal Request.Please Try again later.");
                    $response['data'] = array();
                    $response['amount'] = 0;
                }
            } else {
                $response['error'] = true;
                $response['error_message'] =
                    labels('admin_labels.insufficient_balance_for_withdrawal', "You don't have enough balance to sent the withdraw request.");
                $response['data'] = array();
                $response['amount'] = 0;
            }
            return response()->json($response);
        }
    }
}
