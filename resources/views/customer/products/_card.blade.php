@php
    $variants = data_get($product, 'variants', []);
    $variant = $variants[0] ?? null;
    $price = data_get($variant, 'price', data_get($product, 'min_max_price.min_price', 0));
    $specialPrice = data_get($variant, 'special_price', 0);
    $slug = data_get($product, 'slug');
    $name = data_get($product, 'name');
    $image = data_get($product, 'image');
    $variantId = data_get($variant, 'id');
@endphp
<div class="card h-100 border-0 shadow-sm">
    <a href="{{ route('customer.product.show', $slug) }}">
        <img src="{{ $image }}" class="card-img-top" alt="{{ $name }}" style="height:180px;object-fit:contain">
    </a>
    <div class="card-body">
        <a href="{{ route('customer.product.show', $slug) }}" class="text-decoration-none text-dark">
            <div class="small text-truncate">{{ $name }}</div>
        </a>
        <div class="fw-bold mt-1">
            @if ($specialPrice > 0 && $specialPrice < $price)
                <span>{{ $currency_symbol ?? '$' }}{{ $specialPrice }}</span>
                <span class="text-muted text-decoration-line-through small">{{ $currency_symbol ?? '$' }}{{ $price }}</span>
            @else
                <span>{{ $currency_symbol ?? '$' }}{{ $price }}</span>
            @endif
        </div>
        @auth('web')
            <form method="POST" action="{{ route('customer.cart.add') }}" class="mt-2">
                @csrf
                <input type="hidden" name="product_variant_id" value="{{ $variantId }}">
                <input type="hidden" name="qty" value="1">
                <button type="submit" class="btn btn-sm btn-dark w-100">{{ labels('front_messages.add_to_cart', 'Add to Cart') }}</button>
            </form>
        @else
            <a href="{{ route('customer.login') }}" class="btn btn-sm btn-outline-dark w-100 mt-2">{{ labels('front_messages.sign_in_to_buy', 'Sign in to buy') }}</a>
        @endauth
    </div>
</div>
