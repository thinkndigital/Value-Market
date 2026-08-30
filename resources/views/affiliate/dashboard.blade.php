@extends('affiliate.layout')

@section('title', labels('admin_labels.my_affiliate_account', 'My Affiliate Account'))

@section('content')
    <div class="panel-card">
        <h6>{{ labels('admin_labels.your_affiliate_link', 'Your Affiliate Link') }}</h6>
        <div class="input-group mb-2">
            <input type="text" class="form-control" id="affiliate_link_input" value="{{ $shareUrl }}" readonly>
            <button class="btn btn-primary" type="button" onclick="copyAffiliateLink()">
                <i class='bx bx-copy'></i> {{ labels('admin_labels.copy', 'Copy') }}
            </button>
        </div>
        <small class="text-muted">{{ labels('admin_labels.affiliate_code', 'Affiliate Code') }}:
            {{ $link->code }}</small>
    </div>

    <div class="row g-3">
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-value">{{ $link->clicks_count }}</div>
                <small class="text-muted">{{ labels('admin_labels.clicks', 'Clicks') }}</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-value">{{ $link->conversions_count }}</div>
                <small class="text-muted">{{ labels('admin_labels.conversions', 'Conversions') }}</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-value success">
                    {{ app(\App\Services\CurrencyService::class)->formateCurrency(formatePriceDecimal($approvedCommission)) }}
                </div>
                <small class="text-muted">{{ labels('admin_labels.approved_commission', 'Approved Commission') }}</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card text-center">
                <div class="stat-value warning">
                    {{ app(\App\Services\CurrencyService::class)->formateCurrency(formatePriceDecimal($pendingCommission)) }}
                </div>
                <small class="text-muted">{{ labels('admin_labels.pending_commission', 'Pending Commission') }}</small>
            </div>
        </div>
    </div>

    <div class="panel-card mt-3">
        <p class="text-muted small mb-0">
            {{ labels(
                'admin_labels.affiliate_home_pointer',
                'Browse the sidebar to see available products (with ready-to-copy links), your commission history, withdrawals, and private stores.',
            ) }}
        </p>
    </div>
@endsection

@section('scripts')
    <script>
        function copyAffiliateLink() {
            var input = document.getElementById('affiliate_link_input');
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value).then(function() {
                iziToast.success({
                    title: 'Copied',
                    message: 'Affiliate link copied to clipboard',
                    position: 'topRight'
                });
            });
        }
    </script>
@endsection
