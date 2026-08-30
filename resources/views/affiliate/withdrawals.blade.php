@extends('affiliate.layout')

@section('title', labels('admin_labels.withdrawals', 'Withdrawals'))

@section('content')
    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="panel-card">
                <h6>{{ labels('admin_labels.request_withdrawal', 'Request Withdrawal') }}</h6>
                <p class="small text-muted mb-2">
                    {{ labels('admin_labels.available_balance', 'Available balance') }}:
                    <b id="withdrawal_available_balance">{{ app(\App\Services\CurrencyService::class)->formateCurrency(formatePriceDecimal($balance)) }}</b>
                </p>
                <div class="mb-2">
                    <input type="number" min="1" step="0.01" class="form-control form-control-sm mb-2"
                        id="withdrawal_amount_input"
                        placeholder="{{ labels('admin_labels.amount', 'Amount') }}">
                    <input type="text" class="form-control form-control-sm mb-2" id="withdrawal_address_input"
                        placeholder="{{ labels('admin_labels.payment_address', 'Payment address (e.g. bank/IBAN or wallet)') }}">
                    <button class="btn btn-sm btn-primary w-100" type="button" id="withdrawal_submit_btn">
                        {{ labels('admin_labels.send_request', 'Send Request') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="panel-card">
                <h6>{{ labels('admin_labels.withdrawal_history', 'Withdrawal History') }}</h6>
                <div class="table-responsive">
                    <table class="affiliate-table">
                        <thead>
                            <tr>
                                <th>{{ labels('admin_labels.date', 'Date') }}</th>
                                <th>{{ labels('admin_labels.amount', 'Amount') }}</th>
                                <th>{{ labels('admin_labels.status', 'Status') }}</th>
                            </tr>
                        </thead>
                        <tbody id="withdrawal_history_body">
                            <tr>
                                <td colspan="3" class="text-muted small">{{ labels('admin_labels.loading', 'Loading…') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        var withdrawalStatusLabels = {
            0: { text: '{{ labels('admin_labels.pending', 'Pending') }}', cls: 'pending' },
            1: { text: '{{ labels('admin_labels.approved', 'Approved') }}', cls: 'approved' },
            2: { text: '{{ labels('admin_labels.rejected', 'Rejected') }}', cls: 'rejected' },
        };

        function loadWithdrawalHistory() {
            fetch('{{ route('affiliate.withdrawal.history') }}')
                .then(res => res.json())
                .then(res => {
                    var body = document.getElementById('withdrawal_history_body');
                    var rows = res.data || [];

                    if (!rows.length) {
                        body.innerHTML = '<tr><td colspan="3" class="text-muted small">' +
                            '{{ labels('admin_labels.no_withdrawal_requests_yet', 'No withdrawal requests yet.') }}</td></tr>';
                        return;
                    }

                    body.innerHTML = rows.map(function(row) {
                        var status = withdrawalStatusLabels[row.status] || { text: row.status, cls: 'pending' };
                        return '<tr>' +
                            '<td>' + (row.created_at || '').substring(0, 10) + '</td>' +
                            '<td>' + Number(row.amount_requested).toFixed(2) + '</td>' +
                            '<td><span class="status-badge ' + status.cls + '">' + status.text + '</span></td>' +
                            '</tr>';
                    }).join('');
                });
        }

        document.getElementById('withdrawal_submit_btn').addEventListener('click', function() {
            var amount = document.getElementById('withdrawal_amount_input').value;
            var address = document.getElementById('withdrawal_address_input').value;

            if (!amount || Number(amount) <= 0 || !address.trim()) {
                iziToast.error({
                    title: 'Error',
                    message: '{{ labels('admin_labels.enter_amount_and_address', 'Enter a valid amount and payment address.') }}',
                    position: 'topRight'
                });
                return;
            }

            fetch('{{ route('affiliate.withdrawal.request') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ amount: amount, payment_address: address }),
                })
                .then(res => res.json())
                .then(res => {
                    if (res.error) {
                        iziToast.error({ title: 'Error', message: res.message, position: 'topRight' });
                        return;
                    }
                    iziToast.success({ title: 'Success', message: res.message, position: 'topRight' });
                    document.getElementById('withdrawal_amount_input').value = '';
                    document.getElementById('withdrawal_address_input').value = '';
                    if (res.balance !== undefined) {
                        document.getElementById('withdrawal_available_balance').textContent = Number(res.balance).toFixed(2);
                    }
                    loadWithdrawalHistory();
                });
        });

        loadWithdrawalHistory();
    </script>
@endsection
