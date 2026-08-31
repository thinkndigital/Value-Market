@extends('wholesaler.layout')
@section('title')
    {{ labels('wholesaler_labels.wallet', 'Wallet') }}
@endsection
@section('content')
    <x-wholesaler.breadcrumb :title="labels('wholesaler_labels.wallet', 'Wallet')" :subtitle="labels(
        'wholesaler_labels.wallet_subtitle',
        'Your balance is credited automatically when you mark an order as paid',
    )" :breadcrumbs="[['label' => labels('wholesaler_labels.wallet', 'Wallet')]]" />

    <div class="modal fade" id="withdraw_money_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ labels('admin_labels.withdraw_money', 'Withdraw Money') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="withdraw_money_form">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ labels('admin_labels.amount', 'Amount') }}<span class='text-asterisks'>*</span></label>
                            <input type="number" name="amount" id="withdraw_amount" class="form-control" min="1" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ labels('admin_labels.payment_address', 'Payment Address') }}<span class='text-asterisks'>*</span></label>
                            <textarea name="payment_address" id="withdraw_payment_address" class="form-control" placeholder="Bank account / payment details" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ labels('admin_labels.cancel', 'Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ labels('admin_labels.submit', 'Submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-4">
            <div class="card p-4 text-center">
                <div class="mb-2"><i class='bx bx-wallet fs-1'></i></div>
                <h5 class="mb-0">{{ labels('admin_labels.wallet_balance', 'Wallet Balance') }}: {{ $currency_symbol ?? '' }}{{ $wallet_balance }}</h5>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card content-area p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">{{ labels('admin_labels.wallet_transaction', 'Wallet Transaction') }}</h5>
                    <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#withdraw_money_modal">
                        <i class="bx bx-plus-circle"></i> {{ labels('admin_labels.withdraw_money', 'Withdraw Money') }}
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table" id="wholesaler_wallet_table" data-toggle="table"
                        data-url="{{ route('wholesaler.wallet.transactions') }}" data-side-pagination="server"
                        data-pagination="true" data-page-list="[10, 20, 50]" data-sort-name="id" data-sort-order="desc"
                        data-mobile-responsive="true">
                        <thead>
                            <tr>
                                <th data-field="id" data-sortable="true">{{ labels('admin_labels.id', 'ID') }}</th>
                                <th data-field="type">{{ labels('admin_labels.transaction_type', 'Transaction Type') }}</th>
                                <th data-field="amount">{{ labels('admin_labels.amount', 'Amount') }}</th>
                                <th data-field="message">{{ labels('admin_labels.note', 'Note') }}</th>
                                <th data-field="created_at" data-sortable="true">{{ labels('admin_labels.date', 'Date') }}</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        $('#withdraw_money_form').on('submit', function (e) {
            e.preventDefault();
            $.ajax({
                url: "{{ route('wholesaler.wallet.withdraw') }}",
                type: 'PUT',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    amount: $('#withdraw_amount').val(),
                    payment_address: $('#withdraw_payment_address').val(),
                },
                success: function (res) {
                    iziToast.success({ title: 'Success', message: res.message || 'Withdrawal request submitted.', position: 'topRight' });
                    $('#withdraw_money_modal').modal('hide');
                    $('#withdraw_money_form')[0].reset();
                },
                error: function (xhr) {
                    var message = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0]))) || 'An error occurred.';
                    iziToast.error({ title: 'Error', message: message, position: 'topRight' });
                }
            });
        });
    </script>
@endsection
