@extends('customer.layout')

@section('content')
    <section class="container py-4">
        <h1 class="h3 mb-4">{{ labels('front_messages.shop_by_category', 'Shop by Category') }}</h1>
        <div class="row g-3">
            @forelse ($categories as $category)
                <div class="col-6 col-md-3 col-lg-2">
                    <a href="{{ route('customer.products', ['category_id' => $category->id]) }}" class="text-decoration-none text-dark">
                        <div class="card h-100 text-center border-0 shadow-sm">
                            @if (!empty($category->image))
                                <img src="{{ $category->image }}" class="card-img-top p-2" alt="{{ $category->name }}" style="height:100px;object-fit:contain">
                            @endif
                            <div class="card-body py-2">
                                <div class="small">{{ $category->name }}</div>
                            </div>
                        </div>
                    </a>
                </div>
            @empty
                <p class="text-muted">{{ labels('front_messages.no_categories_found', 'No categories found.') }}</p>
            @endforelse
        </div>
    </section>

    <section class="container py-4">
        <h1 class="h3 mb-4">{{ labels('front_messages.new_arrivals', 'New Arrivals') }}</h1>
        <div class="row g-3">
            @forelse ($products as $product)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('customer.products._card', ['product' => $product])
                </div>
            @empty
                <p class="text-muted">{{ labels('front_messages.no_products_found', 'No products found.') }}</p>
            @endforelse
        </div>
        <div class="text-center mt-3">
            <a href="{{ route('customer.products') }}" class="btn btn-outline-dark">{{ labels('front_messages.view_all_products', 'View All Products') }}</a>
        </div>
    </section>
@endsection
