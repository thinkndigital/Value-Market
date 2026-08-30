@extends('customer.layout')

@section('content')
    @php
        $variants = data_get($product, 'variants', []);
        $variant = $variants[0] ?? null;
        $price = data_get($variant, 'price', data_get($product, 'min_max_price.min_price', 0));
        $specialPrice = data_get($variant, 'special_price', 0);
        $variantId = data_get($variant, 'id');
        $name = data_get($product, 'name');
        $image = data_get($product, 'image');
        $storeName = data_get($product, 'store_name');
        $shortDescription = data_get($product, 'short_description');
    @endphp
    <section class="container py-4">
        <div class="row g-4">
            <div class="col-md-5">
                <img src="{{ $image }}" class="img-fluid rounded" alt="{{ $name }}">
            </div>
            <div class="col-md-7">
                <h1 class="h3">{{ $name }}</h1>
                @if (!empty($storeName))
                    <p class="text-muted">{{ labels('front_messages.sold_by', 'Sold by') }}: {{ $storeName }}</p>
                @endif
                <div class="fs-4 fw-bold mb-3">
                    @if ($specialPrice > 0 && $specialPrice < $price)
                        <span>{{ $currency_symbol ?? '$' }}{{ $specialPrice }}</span>
                        <span class="text-muted text-decoration-line-through fs-6">{{ $currency_symbol ?? '$' }}{{ $price }}</span>
                    @else
                        <span>{{ $currency_symbol ?? '$' }}{{ $price }}</span>
                    @endif
                </div>
                @if (!empty($shortDescription))
                    <p>{{ $shortDescription }}</p>
                @endif

                @auth('web')
                    <form method="POST" action="{{ route('customer.cart.add') }}" class="row g-2 align-items-center">
                        @csrf
                        <input type="hidden" name="product_variant_id" value="{{ $variantId }}">
                        <div class="col-auto">
                            <input type="number" name="qty" value="1" min="1" class="form-control" style="width:90px">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-dark">{{ labels('front_messages.add_to_cart', 'Add to Cart') }}</button>
                        </div>
                    </form>
                @else
                    <a href="{{ route('customer.login') }}" class="btn btn-outline-dark">{{ labels('front_messages.sign_in_to_buy', 'Sign in to buy') }}</a>
                @endauth
            </div>
        </div>
    </section>
@endsection
