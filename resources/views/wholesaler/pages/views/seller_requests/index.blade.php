@extends('wholesaler.layout')
@section('title')
    {{ labels('wholesaler_labels.seller_requests', 'Seller Requests') }}
@endsection
@section('content')
    <x-wholesaler.breadcrumb :title="labels('wholesaler_labels.seller_requests', 'Seller Requests')" :subtitle="labels(
        'wholesaler_labels.seller_requests_subtitle',
        'Control who can buy from your marketplace listing',
    )" :breadcrumbs="[['label' => labels('wholesaler_labels.seller_requests', 'Seller Requests')]]" />

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card content-area p-4 mb-4">
                <h5 class="mb-3">{{ labels('wholesaler_labels.marketplace_visibility', 'Marketplace Visibility') }}</h5>
                <p class="text-muted small mb-3">
                    {{ labels(
                        'wholesaler_labels.marketplace_visibility_explainer',
                        'Public: any seller can browse and order your approved products. Private: a seller must first request access and you approve them.',
                    ) }}
                </p>
                <div class="d-flex align-items-center gap-3">
                    <select class="form-select w-auto" id="buyer_visibility_select">
                        <option value="public" {{ ($wholesaler->buyer_visibility ?? 'public') === 'public' ? 'selected' : '' }}>{{ labels('admin_labels.public', 'Public') }}</option>
                        <option value="private" {{ ($wholesaler->buyer_visibility ?? 'public') === 'private' ? 'selected' : '' }}>{{ labels('admin_labels.private', 'Private') }}</option>
                    </select>
                    <button class="btn btn-primary" type="button" id="save_visibility_btn">{{ labels('admin_labels.save', 'Save') }}</button>
                </div>
            </div>

            <div class="card content-area p-4">
                <h5 class="mb-3">{{ labels('wholesaler_labels.seller_requests', 'Seller Requests') }}</h5>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>{{ labels('admin_labels.seller', 'Seller') }}</th>
                                <th>{{ labels('admin_labels.status', 'Status') }}</th>
                                <th>{{ labels('admin_labels.action', 'Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($requests as $sellerRequest)
                                <tr data-request-row="{{ $sellerRequest->id }}">
                                    <td>{{ optional(optional($sellerRequest->seller)->user)->username ?? ('#' . $sellerRequest->seller_id) }}</td>
                                    <td data-request-status>
                                        <span class="badge {{ $sellerRequest->status === 'approved' ? 'bg-success' : ($sellerRequest->status === 'rejected' ? 'bg-danger' : 'bg-warning') }}">{{ ucfirst($sellerRequest->status) }}</span>
                                    </td>
                                    <td>
                                        @if ($sellerRequest->status === 'pending')
                                            <button type="button" class="btn btn-sm btn-success" data-respond-request="{{ $sellerRequest->id }}" data-status="approved">{{ labels('admin_labels.approve', 'Approve') }}</button>
                                            <button type="button" class="btn btn-sm btn-danger" data-respond-request="{{ $sellerRequest->id }}" data-status="rejected">{{ labels('admin_labels.reject', 'Reject') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted small">{{ labels('admin_labels.no_requests_yet', 'No requests yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('save_visibility_btn').addEventListener('click', function () {
            fetch('{{ route('wholesaler.seller_requests.visibility') }}', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ buyer_visibility: document.getElementById('buyer_visibility_select').value }),
            })
                .then(r => r.json())
                .then(res => iziToast[res.error ? 'error' : 'success']({ title: res.error ? 'Error' : 'Success', message: res.message, position: 'topRight' }));
        });

        document.querySelectorAll('[data-respond-request]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-respond-request');
                var status = btn.getAttribute('data-status');
                fetch('{{ route('wholesaler.seller_requests.respond') }}', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ request_id: id, status: status }),
                })
                    .then(r => r.json())
                    .then(res => {
                        iziToast[res.error ? 'error' : 'success']({ title: res.error ? 'Error' : 'Success', message: res.message, position: 'topRight' });
                        if (!res.error) {
                            var row = document.querySelector('[data-request-row="' + id + '"]');
                            row.querySelector('[data-request-status]').innerHTML = '<span class="badge ' + (status === 'approved' ? 'bg-success' : 'bg-danger') + '">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>';
                            row.querySelectorAll('[data-respond-request]').forEach(b => b.remove());
                        }
                    });
            });
        });
    </script>
@endsection
