@extends('affiliate.layout')

@section('title', labels('admin_labels.private_stores', 'Private Stores'))

@section('content')
    <div class="panel-card">
        <h6>{{ labels('admin_labels.private_stores', 'Private Stores') }}</h6>
        <p class="small text-muted mb-3">
            {{ labels('admin_labels.private_stores_explainer', 'These sellers approve affiliates before their products show up in your catalog.') }}
        </p>
        <div id="private_stores_list"></div>
    </div>
@endsection

@section('scripts')
    <script>
        var privateStoreStatusLabels = {
            approved: { text: '{{ labels('admin_labels.approved', 'Approved') }}', cls: 'approved' },
            pending: { text: '{{ labels('admin_labels.pending', 'Pending') }}', cls: 'pending' },
            rejected: { text: '{{ labels('admin_labels.rejected', 'Rejected') }}', cls: 'rejected' },
        };

        function requestStoreAccess(storeId, btn) {
            btn.disabled = true;
            fetch('{{ route('affiliate.stores.request') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ store_id: storeId }),
                })
                .then(res => res.json())
                .then(res => {
                    if (res.error) {
                        iziToast.error({ title: 'Error', message: res.message, position: 'topRight' });
                        btn.disabled = false;
                        return;
                    }
                    iziToast.success({ title: 'Success', message: res.message, position: 'topRight' });
                    loadPrivateStores();
                });
        }

        function loadPrivateStores() {
            fetch('{{ route('affiliate.stores.list') }}')
                .then(res => res.json())
                .then(res => {
                    var container = document.getElementById('private_stores_list');
                    var stores = res.data || [];

                    if (!stores.length) {
                        container.innerHTML = '<p class="text-muted small mb-0">{{ labels('admin_labels.no_private_stores_yet', 'No private stores yet.') }}</p>';
                        return;
                    }

                    container.innerHTML = stores.map(function(store) {
                        var action;
                        if (!store.request_status) {
                            action = '<button class="btn btn-sm btn-outline-primary" onclick="requestStoreAccess(' + store.store_id + ', this)">' +
                                '{{ labels('admin_labels.request_to_join', 'Request to Join') }}</button>';
                        } else {
                            var status = privateStoreStatusLabels[store.request_status] || { text: store.request_status, cls: 'pending' };
                            action = '<span class="status-badge ' + status.cls + '">' + status.text + '</span>';
                        }
                        return '<div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">' +
                            '<span class="small">' + store.store_name + '</span>' + action + '</div>';
                    }).join('');
                });
        }

        loadPrivateStores();
    </script>
@endsection
