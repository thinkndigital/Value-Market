@extends('affiliate.layout')

@section('title', labels('admin_labels.my_store', 'My Store'))

@section('content')
    <div class="panel-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">{{ labels('admin_labels.my_store', 'My Store') }}</h6>
            @if ($store)
                <span class="badge {{ $store->status ? 'bg-success' : 'bg-secondary' }}">
                    {{ $store->status ? labels('admin_labels.published', 'Published') : labels('admin_labels.draft', 'Draft') }}
                </span>
            @endif
        </div>
        <p class="text-muted small mb-3">{{ labels('admin_labels.my_store_explainer', 'A public landing page featuring products you promote - share it anywhere, every click still counts toward your commission.') }}</p>

        @if ($store && $store->status)
            <div class="mb-3">
                <a href="{{ route('affiliate.store.show', $store->slug) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                    {{ labels('admin_labels.view_live', 'View Live') }} <i class="anm anm-external-link-l"></i>
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('{{ route('affiliate.store.show', $store->slug) }}')">{{ labels('admin_labels.copy_link', 'Copy Link') }}</button>
            </div>
        @endif

        <form id="storeSettingsForm" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">{{ labels('admin_labels.name', 'Name') }}</label>
                <input type="text" name="name" class="form-control" value="{{ $store->name ?? '' }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ labels('admin_labels.description', 'Description') }}</label>
                <textarea name="description" class="form-control" rows="3">{{ $store->description ?? '' }}</textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">{{ labels('admin_labels.logo', 'Logo') }}</label>
                    <input type="file" name="logo" class="form-control" accept="image/*">
                    @if (!empty($store?->logo))
                        <img src="{{ app(\App\Services\MediaService::class)->getMediaImageUrl($store->logo) }}" class="mt-2" style="max-height:60px;">
                    @endif
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">{{ labels('admin_labels.banner', 'Banner') }}</label>
                    <input type="file" name="banner" class="form-control" accept="image/*">
                    @if (!empty($store?->banner))
                        <img src="{{ app(\App\Services\MediaService::class)->getMediaImageUrl($store->banner) }}" class="mt-2" style="max-height:60px;">
                    @endif
                </div>
            </div>
            <button type="submit" class="btn btn-sm btn-primary">{{ labels('admin_labels.save', 'Save') }}</button>
            @if ($store)
                <button type="button" id="togglePublishBtn" class="btn btn-sm {{ $store->status ? 'btn-outline-danger' : 'btn-outline-success' }}" data-status="{{ $store->status ? 0 : 1 }}">
                    {{ $store->status ? labels('admin_labels.unpublish', 'Unpublish') : labels('admin_labels.publish', 'Publish') }}
                </button>
            @endif
        </form>
    </div>

    <div class="panel-card">
        <h6 class="mb-2">{{ labels('admin_labels.featured_products', 'Featured Products') }}</h6>
        <p class="text-muted small mb-3">{{ labels('admin_labels.featured_products_explainer', 'Pick which of your tracked products (from My Products) appear on your store page.') }}</p>
        <div class="table-responsive">
            <table class="affiliate-table">
                <thead>
                    <tr>
                        <th>{{ labels('admin_labels.product', 'Product') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($myProducts as $item)
                        <tr data-link-row="{{ $item['link_id'] }}">
                            <td>
                                @if ($item['image'])
                                    <img src="{{ $item['image'] }}" width="28" height="28" style="object-fit:cover;border-radius:4px;" class="me-2">
                                @endif
                                {{ $item['name'] }}
                            </td>
                            <td>
                                @if ($item['featured'])
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-featured" data-id="{{ $item['link_id'] }}">{{ labels('admin_labels.remove', 'Remove') }}</button>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-primary add-featured" data-id="{{ $item['link_id'] }}">{{ labels('admin_labels.add_to_store', 'Add to Store') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-muted small">{{ labels('admin_labels.no_products_saved_yet', "You haven't added any products yet - copy a link from the Marketplace to add one here.") }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.getElementById('storeSettingsForm').addEventListener('submit', function (e) {
            e.preventDefault();
            var formData = new FormData(this);
            fetch('{{ route('affiliate.my_store.update') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: formData,
            })
                .then(res => res.json())
                .then(res => {
                    iziToast[res.error ? 'error' : 'success']({ title: res.error ? 'Error' : 'Success', message: res.message, position: 'topRight' });
                    if (!res.error) setTimeout(() => location.reload(), 800);
                });
        });

        var publishBtn = document.getElementById('togglePublishBtn');
        if (publishBtn) {
            publishBtn.addEventListener('click', function () {
                fetch('{{ route('affiliate.my_store.publish') }}', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ status: publishBtn.getAttribute('data-status') }),
                })
                    .then(res => res.json())
                    .then(res => {
                        iziToast[res.error ? 'error' : 'success']({ title: res.error ? 'Error' : 'Success', message: res.message, position: 'topRight' });
                        if (!res.error) setTimeout(() => location.reload(), 800);
                    });
            });
        }

        document.querySelectorAll('.add-featured, .remove-featured').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-id');
                var adding = btn.classList.contains('add-featured');
                fetch('{{ route('affiliate.my_store.products.add') }}', {
                    method: adding ? 'POST' : 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ link_id: id }),
                })
                    .then(res => res.json())
                    .then(res => {
                        iziToast[res.error ? 'error' : 'success']({ title: res.error ? 'Error' : 'Success', message: res.message, position: 'topRight' });
                        if (!res.error) location.reload();
                    });
            });
        });
    </script>
@endsection
