@extends('affiliate.layout')

@section('title', $productName)

@section('content')
    <a href="{{ route('affiliate.products.page') }}" class="d-inline-block mb-3 small text-decoration-none">
        <i class='bx bx-chevron-left'></i> {{ labels('admin_labels.back_to_products', 'Back to Products') }}
    </a>

    <div class="panel-card">
        <div class="row g-4">
            <div class="col-12 col-md-4">
                <img src="{{ $imageUrl }}" class="img-fluid rounded" style="width:100%; object-fit:cover; aspect-ratio:1;">
            </div>
            <div class="col-12 col-md-8">
                <h4 class="mb-1">{{ $productName }}</h4>
                <p class="text-muted mb-2">{{ $storeName }}</p>

                @if ($price !== null)
                    <div class="h5 mb-3">
                        {{ app(\App\Services\CurrencyService::class)->formateCurrency(formatePriceDecimal($price)) }}
                    </div>
                @endif

                @if ($shortDescription)
                    <p class="mb-3">{{ $shortDescription }}</p>
                @endif

                <div class="mb-3">
                    <span class="status-badge approved">
                        {{ labels('admin_labels.commission', 'Commission') }}:
                        {{ $commissionRateType === 'percentage' ? $commissionRateValue . '%' : $commissionRateValue }}
                    </span>
                </div>

                <label class="form-label small text-muted mb-1">{{ labels('admin_labels.your_affiliate_link', 'Your Affiliate Link') }}</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="product_link_input" value="{{ $linkUrl }}" readonly>
                    <button class="btn btn-primary" type="button" onclick="copyToClipboard('{{ $linkUrl }}')">
                        <i class='bx bx-copy'></i> {{ labels('admin_labels.copy', 'Copy') }}
                    </button>
                </div>
            </div>
        </div>

        @if ($description)
            <hr class="my-4">
            <h6>{{ labels('admin_labels.description', 'Description') }}</h6>
            <div>{!! $description !!}</div>
        @endif
    </div>
@endsection
