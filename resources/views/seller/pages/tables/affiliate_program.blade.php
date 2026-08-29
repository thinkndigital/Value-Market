@php
    use App\Services\MediaService;
    use App\Models\CommissionRule;
@endphp
@extends('seller/layout')
@section('title')
    {{ labels('admin_labels.affiliate_program', 'Affiliate Program') }}
@endsection
@section('content')
    <section class="main-content">
        <div class="row">
            <x-seller.breadcrumb :title="labels('admin_labels.affiliate_program', 'Affiliate Program')" :subtitle="labels(
                'admin_labels.let_affiliates_sell_your_products_on_commission',
                'Let affiliates sell your products on commission',
            )" :breadcrumbs="[['label' => labels('admin_labels.affiliate_program', 'Affiliate Program')]]" />

            <div id="affiliate_program_alert"></div>

            <section class="overview-data">
                <div class="card content-area p-4 mb-4">
                    <h5 class="mb-3">{{ labels('admin_labels.catalog_visibility', 'Catalog Visibility') }}</h5>
                    <p class="text-muted small mb-3">
                        {{ labels(
                            'admin_labels.affiliate_visibility_explainer',
                            'Public: any affiliate can see and generate links for your commission-enabled products automatically. Private: an affiliate must first request access and you approve them.',
                        ) }}
                    </p>
                    <div class="d-flex align-items-center gap-3">
                        <select class="form-select w-auto" id="affiliate_visibility_select">
                            <option value="public" {{ ($store->affiliate_visibility ?? 'public') === 'public' ? 'selected' : '' }}>
                                {{ labels('admin_labels.public', 'Public') }}</option>
                            <option value="private" {{ ($store->affiliate_visibility ?? 'public') === 'private' ? 'selected' : '' }}>
                                {{ labels('admin_labels.private', 'Private') }}</option>
                        </select>
                        <button class="btn btn-primary" type="button" id="save_visibility_btn">
                            {{ labels('admin_labels.save', 'Save') }}
                        </button>
                    </div>
                </div>

                <div class="card content-area p-4 mb-4">
                    <h5 class="mb-3">{{ labels('admin_labels.your_products', 'Your Products') }}</h5>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>{{ labels('admin_labels.image', 'Image') }}</th>
                                    <th>{{ labels('admin_labels.name', 'Name') }}</th>
                                    <th>{{ labels('admin_labels.affiliate_status', 'Affiliate Status') }}</th>
                                    <th>{{ labels('admin_labels.commission', 'Commission') }}</th>
                                    <th>{{ labels('admin_labels.action', 'Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($products as $product)
                                    @php
                                        $rule = $rules->get($product->id);
                                        $enabled = $rule && (int) $rule->status === CommissionRule::STATUS_ACTIVE;
                                        $productName = json_decode($product->name, true)['en'] ?? $product->name;
                                    @endphp
                                    <tr data-product-row="{{ $product->id }}">
                                        <td><img src="{{ app(MediaService::class)->getMediaImageUrl($product->image) }}"
                                                width="44" height="44" style="object-fit:cover;border-radius:6px;"></td>
                                        <td>{{ $productName }}</td>
                                        <td>
                                            <span class="badge {{ $enabled ? 'bg-success' : 'bg-secondary' }}"
                                                data-status-badge>
                                                {{ $enabled ? labels('admin_labels.enabled', 'Enabled') : labels('admin_labels.disabled', 'Disabled') }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm" style="max-width: 220px;">
                                                <select class="form-select" data-rate-type>
                                                    <option value="percentage" {{ ($rule->rate_type ?? 'percentage') === 'percentage' ? 'selected' : '' }}>%</option>
                                                    <option value="flat" {{ ($rule->rate_type ?? '') === 'flat' ? 'selected' : '' }}>{{ labels('admin_labels.flat', 'Flat') }}</option>
                                                </select>
                                                <input type="number" min="0.01" step="0.01" class="form-control"
                                                    data-rate-value value="{{ $rule->rate_value ?? '' }}">
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button"
                                                class="btn btn-sm {{ $enabled ? 'btn-outline-danger' : 'btn-outline-primary' }}"
                                                data-toggle-product="{{ $product->id }}" data-enabled="{{ $enabled ? '1' : '0' }}">
                                                {{ $enabled ? labels('admin_labels.disable', 'Disable') : labels('admin_labels.enable', 'Enable') }}
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-muted small">{{ labels('admin_labels.no_active_products', 'No active products yet.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card content-area p-4">
                    <h5 class="mb-3">{{ labels('admin_labels.affiliate_join_requests', 'Affiliate Join Requests') }}</h5>
                    <p class="text-muted small mb-3">
                        {{ labels('admin_labels.only_relevant_when_private', 'Only relevant while your catalog is set to Private above.') }}
                    </p>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>{{ labels('admin_labels.affiliate', 'Affiliate') }}</th>
                                    <th>{{ labels('admin_labels.status', 'Status') }}</th>
                                    <th>{{ labels('admin_labels.action', 'Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($requests as $affiliateRequest)
                                    <tr data-request-row="{{ $affiliateRequest->id }}">
                                        <td>{{ $affiliateRequest->user->username ?? ('#' . $affiliateRequest->user_id) }}</td>
                                        <td data-request-status>
                                            @php
                                                $badgeClass = match ($affiliateRequest->status) {
                                                    'approved' => 'bg-success',
                                                    'rejected' => 'bg-danger',
                                                    default => 'bg-warning',
                                                };
                                            @endphp
                                            <span class="badge {{ $badgeClass }}">{{ ucfirst($affiliateRequest->status) }}</span>
                                        </td>
                                        <td>
                                            @if ($affiliateRequest->status === 'pending')
                                                <button type="button" class="btn btn-sm btn-outline-primary me-1"
                                                    data-respond-request="{{ $affiliateRequest->id }}" data-status="approved">
                                                    {{ labels('admin_labels.approve', 'Approve') }}
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-respond-request="{{ $affiliateRequest->id }}" data-status="rejected">
                                                    {{ labels('admin_labels.reject', 'Reject') }}
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-muted small">{{ labels('admin_labels.no_requests_yet', 'No requests yet.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </section>

    <script>
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || '{{ csrf_token() }}';

        function affiliateAlert(type, message) {
            document.getElementById('affiliate_program_alert').innerHTML =
                '<div class="alert alert-' + type + ' py-2">' + message + '</div>';
        }

        document.getElementById('save_visibility_btn').addEventListener('click', function() {
            fetch('{{ route('seller.affiliate_program.visibility') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ affiliate_visibility: document.getElementById('affiliate_visibility_select').value }),
                })
                .then(res => res.json())
                .then(res => affiliateAlert(res.error ? 'danger' : 'success', res.message));
        });

        document.querySelectorAll('[data-toggle-product]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var row = btn.closest('tr');
                var productId = btn.getAttribute('data-toggle-product');
                var currentlyEnabled = btn.getAttribute('data-enabled') === '1';
                var body = { product_id: productId, enabled: currentlyEnabled ? 0 : 1 };

                if (!currentlyEnabled) {
                    body.rate_type = row.querySelector('[data-rate-type]').value;
                    body.rate_value = row.querySelector('[data-rate-value]').value;
                    if (!body.rate_value || Number(body.rate_value) <= 0) {
                        affiliateAlert('danger', '{{ labels('admin_labels.enter_a_valid_commission_rate', 'Enter a valid commission rate first.') }}');
                        return;
                    }
                }

                fetch('{{ route('seller.affiliate_program.products.toggle') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: JSON.stringify(body),
                    })
                    .then(res => res.json())
                    .then(res => {
                        affiliateAlert(res.error ? 'danger' : 'success', res.message);
                        if (!res.error) {
                            setTimeout(() => window.location.reload(), 600);
                        }
                    });
            });
        });

        document.querySelectorAll('[data-respond-request]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                fetch('{{ route('seller.affiliate_program.requests.respond') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: JSON.stringify({ request_id: btn.getAttribute('data-respond-request'), status: btn.getAttribute('data-status') }),
                    })
                    .then(res => res.json())
                    .then(res => {
                        affiliateAlert(res.error ? 'danger' : 'success', res.message);
                        if (!res.error) {
                            setTimeout(() => window.location.reload(), 600);
                        }
                    });
            });
        });
    </script>
@endsection
