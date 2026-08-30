@extends('customer.layout')

@section('content')
    <section class="container py-4">
        <form method="GET" action="{{ route('customer.products') }}" class="row g-2 mb-4">
            <div class="col-8 col-md-4">
                <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="{{ labels('front_messages.search_products', 'Search products...') }}">
            </div>
            <div class="col-4 col-md-2">
                <button type="submit" class="btn btn-dark w-100">{{ labels('front_messages.search', 'Search') }}</button>
            </div>
        </form>

        <div class="row g-3">
            @forelse ($products as $product)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('customer.products._card', ['product' => $product])
                </div>
            @empty
                <p class="text-muted">{{ labels('front_messages.no_products_found', 'No products found.') }}</p>
            @endforelse
        </div>

        @if ($total > $perPage)
            <nav class="mt-4">
                <ul class="pagination">
                    @for ($i = 1; $i <= ceil($total / $perPage); $i++)
                        <li class="page-item {{ $i == $page ? 'active' : '' }}">
                            <a class="page-link" href="{{ route('customer.products', array_merge(request()->query(), ['page' => $i])) }}">{{ $i }}</a>
                        </li>
                    @endfor
                </ul>
            </nav>
        @endif
    </section>
@endsection
