@extends('seller.layout')
@section('title')
    {{ labels('wholesaler_labels.supplier_requests', 'Supplier Requests') }}
@endsection
@section('content')
    <x-seller.breadcrumb :title="labels('wholesaler_labels.supplier_requests', 'Supplier Requests')" :subtitle="labels(
        'wholesaler_labels.supplier_requests_subtitle',
        'Some suppliers require approval before you can order from them',
    )" :breadcrumbs="[['label' => labels('wholesaler_labels.supplier_requests', 'Supplier Requests')]]" />

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card content-area p-4">
                <div class="table-responsive">
                    <table class="table align-middle" id="browsable_wholesalers_table">
                        <thead>
                            <tr>
                                <th>{{ labels('wholesaler_labels.wholesaler', 'Supplier') }}</th>
                                <th>{{ labels('admin_labels.status', 'Status') }}</th>
                                <th>{{ labels('admin_labels.action', 'Action') }}</th>
                            </tr>
                        </thead>
                        <tbody id="browsable_wholesalers_body">
                            <tr><td colspan="3" class="text-muted small">{{ labels('admin_labels.loading', 'Loading...') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function statusBadge(status) {
            if (status === 'approved') return '<span class="badge bg-success">Approved</span>';
            if (status === 'pending') return '<span class="badge bg-warning">Pending</span>';
            if (status === 'rejected') return '<span class="badge bg-danger">Rejected</span>';
            return '<span class="text-muted">' + "{{ labels('wholesaler_labels.no_request_yet', 'No request yet') }}" + '</span>';
        }

        function loadBrowsableWholesalers() {
            fetch('{{ route('seller.wholesaler_marketplace.requests.browse') }}')
                .then(r => r.json())
                .then(res => {
                    var body = document.getElementById('browsable_wholesalers_body');
                    if (!res.data || !res.data.length) {
                        body.innerHTML = '<tr><td colspan="3" class="text-muted small">{{ labels('admin_labels.no_data_found', 'No data found.') }}</td></tr>';
                        return;
                    }
                    body.innerHTML = res.data.map(function (row) {
                        var action = (!row.request_status || row.request_status === 'rejected')
                            ? '<button type="button" class="btn btn-sm btn-primary request-wholesaler-access" data-id="' + row.wholesaler_id + '">' + "{{ labels('wholesaler_labels.request_access', 'Request Access') }}" + '</button>'
                            : '';
                        return '<tr><td>' + row.business_name + '</td><td>' + statusBadge(row.request_status) + '</td><td>' + action + '</td></tr>';
                    }).join('');
                });
        }
        loadBrowsableWholesalers();

        document.getElementById('browsable_wholesalers_body').addEventListener('click', function (e) {
            var btn = e.target.closest('.request-wholesaler-access');
            if (!btn) return;
            fetch('{{ route('seller.wholesaler_marketplace.requests.store') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ wholesaler_id: btn.getAttribute('data-id') }),
            })
                .then(r => r.json())
                .then(res => {
                    iziToast[res.error ? 'error' : 'success']({ title: res.error ? 'Error' : 'Success', message: res.message, position: 'topRight' });
                    if (!res.error) loadBrowsableWholesalers();
                });
        });
    </script>
@endsection
