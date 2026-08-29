@extends('seller/layout')
@section('title')
    {{ labels('admin_labels.withdrawal_requests', 'Withdrawal Requests') }}
@endsection
@section('content')
    <x-seller.breadcrumb :title="labels('admin_labels.withdrawal_requests', 'Withdrawal Requests')" :subtitle="labels(
        'admin_labels.track_and_manage_withdrawal_requests_with_precision',
        'Track and Manage Withdrawal Requests with Precision',
    )" :breadcrumbs="[['label' => labels('admin_labels.withdrawal_requests', 'Withdrawal Requests')]]" />

    {{-- Withdraw Money modal --}}
    <div class="modal fade" id="withdraw_money_modal" tabindex="-1" role="dialog" aria-labelledby="withdrawMoneyModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="withdrawMoneyModalLabel">
                        {{ labels('admin_labels.withdraw_money', 'Withdraw Money') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="withdraw_money_form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="user_id" value="{{ $user_id }}">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="amount" class="form-label">{{ labels('admin_labels.amount', 'Amount') }}
                                <span class='text-asterisks text-sm'>*</span></label>
                            <input type="number" class="form-control" name="amount" id="amount" min="1" step="0.01"
                                required>
                        </div>
                        <div class="form-group mt-3">
                            <label for="payment_address"
                                class="form-label">{{ labels('admin_labels.payment_address', 'Payment Address') }}
                                <span class='text-asterisks text-sm'>*</span></label>
                            <textarea class="form-control" name="payment_address" id="payment_address"
                                placeholder="Bank account / UPI / payment details" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn reset-btn" data-bs-dismiss="modal"
                            aria-label="Close">{{ labels('admin_labels.close', 'Close') }}</button>
                        <button type="submit" class="btn btn-primary">{{ labels('admin_labels.submit', 'Submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card p-4 text-center">
                <div class="mb-2"><i class='bx bx-wallet fs-1'></i></div>
                <h5 class="mb-0">{{ labels('admin_labels.wallet_balance', 'Wallet Balance') }} :
                    {{ $currency_symbol ?? '' }}{{ auth()->user()->balance ?? 0 }}</h5>
            </div>
        </div>
    </div>

    <section class="overview-data mt-4">
        <div class="card content-area p-4 ">
            <div class="row align-items-center d-flex heading mb-5">
                <div class="col-md-12">
                    <div class="row">
                        <div class="col-md-6">
                            <h4>{{ labels('admin_labels.withdrawal_requests', 'Withdrawal Requests') }}
                            </h4>
                        </div>
                        <div class="col-md-6 d-flex justify-content-end ">
                            <button type="button" class="btn btn-dark me-2" data-bs-toggle="modal"
                                data-bs-target="#withdraw_money_modal">
                                <i class='bx bx-plus-circle me-1'></i>{{ labels('admin_labels.withdraw_money', 'Withdraw Money') }}
                            </button>
                            <div class="input-group me-2 search-input-grp ">
                                <span class="search-icon"><i class='bx bx-search-alt'></i></span>
                                <input type="text" data-table="seller_withdrawal_request_table"
                                    class="form-control searchInput" placeholder="Search...">
                                <span class="input-group-text">{{ labels('admin_labels.search', 'Search') }}</span>
                            </div>
                            <a class="btn me-2" id="tableRefresh" data-table="seller_withdrawal_request_table"><i
                                    class='bx bx-refresh'></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="pt-0">
                        <div class="table-responsive">
                            <table class='table' id="seller_withdrawal_request_table" data-toggle="table"
                                data-loading-template="loadingTemplate"
                                data-url="{{ route('seller.payment_request.get_payment_request_list') }}"
                                data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                                data-page-list="[5, 10, 20, 50, 100, 200]" data-search="false" data-show-columns="false"
                                data-show-refresh="false" data-trim-on-search="false" data-sort-name="id"
                                data-sort-order="desc" data-mobile-responsive="true" data-toolbar=""
                                data-show-export="false" data-maintain-selected="true"
                                data-query-params="queryParams">
                                <thead>
                                    <tr>
                                        <th data-field="id" data-sortable="true">
                                            {{ labels('admin_labels.id', 'ID') }}
                                        <th data-field="amount_requested" data-disabled="1" data-sortable="false">
                                            {{ labels('admin_labels.amount', 'Amount') }}
                                        </th>
                                        <th data-field="payment_address" data-sortable="false">
                                            {{ labels('admin_labels.payment_address', 'Payment Address') }}
                                        </th>
                                        <th data-field="remarks" data-sortable="false">
                                            {{ labels('admin_labels.remarks', 'Remarks') }}
                                        </th>
                                        <th data-field="status" data-sortable="false">
                                            {{ labels('admin_labels.status', 'Status') }}
                                        </th>
                                        <th data-field="date_created" data-sortable="false">
                                            {{ labels('admin_labels.date', 'Date') }}
                                        </th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        $('#withdraw_money_form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            $.ajax({
                url: "{{ route('seller.payment_request.add_withdrawal_request') }}",
                method: 'PUT',
                data: $form.serialize(),
                success: function(response) {
                    if (response.error) {
                        iziToast.error({
                            title: 'Error',
                            message: response.error_message || response.message,
                            position: 'topRight'
                        });
                        return;
                    }
                    iziToast.success({
                        title: 'Success',
                        message: response.message || 'Withdrawal request submitted successfully',
                        position: 'topRight'
                    });
                    $('#withdraw_money_modal').modal('hide');
                    $form[0].reset();
                    $('#seller_withdrawal_request_table').bootstrapTable('refresh');
                },
                error: function(xhr) {
                    iziToast.error({
                        title: 'Error',
                        message: (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) ||
                            'Something went wrong! Try again.',
                        position: 'topRight'
                    });
                }
            });
        });
    </script>
@endsection
