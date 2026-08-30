@extends('affiliate.layout')

@section('title', labels('admin_labels.commission_history', 'Commission History'))

@section('content')
    <div class="panel-card">
        <h6>{{ labels('admin_labels.commission_history', 'Commission History') }}</h6>
        <div class="table-responsive">
            <table class="affiliate-table">
                <thead>
                    <tr>
                        <th>{{ labels('admin_labels.order', 'Order') }}</th>
                        <th>{{ labels('admin_labels.order_total', 'Order Total') }}</th>
                        <th>{{ labels('admin_labels.commission', 'Commission') }}</th>
                        <th>{{ labels('admin_labels.status', 'Status') }}</th>
                        <th>{{ labels('admin_labels.date', 'Date') }}</th>
                    </tr>
                </thead>
                <tbody id="conversions_history_body">
                    <tr>
                        <td colspan="5" class="text-muted small">{{ labels('admin_labels.loading', 'Loading…') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        var conversionStatusLabels = {
            approved: { text: '{{ labels('admin_labels.approved', 'Approved') }}', cls: 'approved' },
            pending: { text: '{{ labels('admin_labels.pending', 'Pending') }}', cls: 'pending' },
            rejected: { text: '{{ labels('admin_labels.rejected', 'Rejected') }}', cls: 'rejected' },
            reversed: { text: '{{ labels('admin_labels.reversed', 'Reversed') }}', cls: 'reversed' },
        };

        function loadConversionsHistory() {
            fetch('{{ route('affiliate.conversions.list') }}')
                .then(res => res.json())
                .then(res => {
                    var body = document.getElementById('conversions_history_body');
                    var rows = res.data || [];

                    if (!rows.length) {
                        body.innerHTML = '<tr><td colspan="5" class="text-muted small">' +
                            '{{ labels('admin_labels.no_commission_history_yet', 'No commission history yet.') }}</td></tr>';
                        return;
                    }

                    body.innerHTML = rows.map(function(row) {
                        var status = conversionStatusLabels[row.status] || { text: row.status, cls: 'pending' };
                        return '<tr>' +
                            '<td>#' + row.order_id + '</td>' +
                            '<td>' + Number(row.order_total).toFixed(2) + '</td>' +
                            '<td>' + Number(row.commission_amount).toFixed(2) + '</td>' +
                            '<td><span class="status-badge ' + status.cls + '">' + status.text + '</span></td>' +
                            '<td>' + (row.created_at || '').substring(0, 10) + '</td>' +
                            '</tr>';
                    }).join('');
                });
        }

        loadConversionsHistory();
    </script>
@endsection
