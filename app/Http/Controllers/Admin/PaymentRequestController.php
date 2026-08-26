<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use App\Traits\HandlesValidation;
use App\Services\CurrencyService;
use App\Services\WalletService;
class PaymentRequestController extends Controller
{
    use HandlesValidation;
    public function index()
    {
        return view('admin.pages.tables.payment_request');
    }

    public function list()
    {
        $search = trim(request()->input('search'));
        $offset = $search || request('pagination_offset') ? request('pagination_offset') : 0;
        $limit = request()->input('limit', 10);
        $sort = request()->input('sort', 'id');
        $order = request()->input('order', 'ASC');
        $startDate = request()->input('start_date');
        $endDate = request()->input('end_date');
        $payment_request_status = request()->input('payment_request_status');
        $userFilter = request()->input('user_filter');

        $query = PaymentRequest::with('user')->whereHas('user');

        // Full-text like search
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%$search%")
                    ->orWhere('payment_type', 'like', "%$search%")
                    ->orWhere('amount_requested', 'like', "%$search%")
                    ->orWhere('payment_address', 'like', "%$search%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('username', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%")
                            ->orWhere('mobile', 'like', "%$search%");
                    });
            });
        }

        // Filter by date range
        if ($startDate && $endDate) {
            $query->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate);
        }

        // Filter by status
        if (isset($payment_request_status)) {
            $query->where('status', intval($payment_request_status));
        }

        // Filter by payment type
        if (!empty($userFilter)) {
            $query->where('payment_type', $userFilter);
        }

        $total = $query->count();

        // Get paginated results
        $results = $query->orderBy($sort, $order)
            ->offset($offset)
            ->limit($limit)
            ->get();

        $rows = [];

        foreach ($results as $row) {
            $action = '<div class="d-flex align-items-center">
            <a class="single_action_button edit_request edit_return_request" href="javascript:void(0)" data-bs-target="#payment_request_modal" data-bs-toggle="modal">
                <i class="bx bx-pencil mx-2"></i>
            </a>
        </div>';

            $rows[] = [
                'id' => $row->id,
                'user_name' => optional($row->user)->username,
                'payment_type' => $row->payment_type,
                'payment_address' => $row->payment_address,
                'amount_requested' => app(CurrencyService::class)->formateCurrency(formatePriceDecimal($row->amount_requested)),
                'remarks' => $row->remarks,
                'status_digit' => $row->status,
                'status' => match ((string) $row->status) {
                    '0' => '<span class="badge bg-success">Pending</span>',
                    '1' => '<span class="badge bg-primary">Approved</span>',
                    '2' => '<span class="badge bg-danger">Rejected</span>',
                    default => '<span class="badge bg-secondary">Unknown</span>',
                },
                'date_created' => Carbon::parse($row->created_at)->format('Y-m-d'),
                'operate' => $action,
            ];
        }

        return response()->json([
            "rows" => $rows,
            "total" => $total,
        ]);
    }
    public function update(Request $request)
    {
        $rules = [
            'payment_request_id' => 'required|numeric',
            'status' => 'required|numeric',
            'update_remarks' => 'required',
        ];

        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        } else {
            $status = fetchDetails(PaymentRequest::class, ['id' => $request['payment_request_id']], 'status')[0]->status;
            if ($status == 2) {
                $response['error'] = true;
                $response['message'] = labels('admin_labels.you_have_already_rejected_amount', 'You have already rejected the amount');
                return response()->json($response);
            } else {

                $data = $request->all();
                $update_status = $data['status'];
                $updateRemarks = $request->input('update_remarks', null);
                $paymentRequestId = $data['payment_request_id'];

                // Fetch the amount_requested and user_id from the "payment_requests" table
                $paymentRequest = PaymentRequest::select('amount_requested', 'user_id')
                    ->where('id', $paymentRequestId)
                    ->first();

                if ($update_status == 2) {
                    // Update the balance based on the condition
                    app(WalletService::class)->updateBalance($paymentRequest->amount_requested, $paymentRequest->user_id, "add");
                }

                // Update the "payment_requests" table
                PaymentRequest::where('id', $paymentRequestId)
                    ->update([
                        'status' => $update_status,
                        'remarks' => $updateRemarks
                    ]);
                $response['error'] = false;
                $response['message'] =
                    labels('admin_labels.payment_request_updated_successfully', 'Payment request updated successfully');
                return response()->json($response);
            }
        }
    }
}
