@extends('customer.layout')

@section('title', $store->name)

@section('content')
    <section class="cust-hero py-5" @if ($store->banner) style="background-image:url('{{ app(\App\Services\MediaService::class)->getMediaImageUrl($store->banner) }}');background-size:cover;background-position:center;" @endif>
        <div class="container text-center">
            @if ($store->logo)
                <img src="{{ app(\App\Services\MediaService::class)->getMediaImageUrl($store->logo) }}" style="max-height:80px;border-radius:8px;" class="mb-3 bg-white p-1">
            @endif
            <h1>{{ $store->name }}</h1>
            @if ($store->description)
                <p class="mx-auto" style="max-width:640px;">{{ $store->description }}</p>
            @endif
        </div>
    </section>

    <div class="container py-5">
        @if ($items->isEmpty())
            <p class="text-muted text-center">{{ labels('front_messages.no_products_yet', 'No products featured yet.') }}</p>
        @else
            <div class="row g-4">
                @foreach ($items as $item)
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="card h-100 border-0 shadow-sm">
                            <a href="{{ $item['link_url'] }}">
                                <img src="{{ $item['image'] }}" class="card-img-top" alt="{{ $item['name'] }}" style="height:180px;object-fit:contain">
                            </a>
                            <div class="card-body">
                                <a href="{{ $item['link_url'] }}" class="text-decoration-none text-dark">
                                    <div class="small text-truncate">{{ $item['name'] }}</div>
                                </a>
                                <div class="text-muted small mt-1">{{ $item['seller_store_name'] }}</div>
                                <a href="{{ $item['link_url'] }}" class="btn btn-sm btn-brand w-100 mt-2">{{ labels('front_messages.view_product', 'View Product') }}</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
