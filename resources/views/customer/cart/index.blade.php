@extends('customer.layout')

@section('content')
    <section class="container py-4">
        <h1 class="h3 mb-4">{{ labels('front_messages.my_cart', 'My Cart') }}</h1>

        @if ($items->isEmpty())
            <p class="text-muted">{{ labels('front_messages.cart_is_empty', 'Your cart is empty.') }}</p>
            <a href="{{ route('customer.products') }}" class="btn btn-dark">{{ labels('front_messages.continue_shopping', 'Continue Shopping') }}</a>
        @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>{{ labels('front_messages.product', 'Product') }}</th>
                            <th>{{ labels('front_messages.price', 'Price') }}</th>
                            <th>{{ labels('front_messages.quantity', 'Qty') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td class="d-flex align-items-center gap-2">
                                    <img src="{{ $item->image }}" alt="{{ $item->name }}" style="width:50px;height:50px;object-fit:contain">
                                    <span>{{ $item->name }}</span>
                                </td>
                                <td>{{ $currency_symbol ?? '$' }}{{ $item->special_price > 0 ? $item->special_price : $item->price }}</td>
                                <td>{{ $item->qty }}</td>
                                <td>
                                    <form method="POST" action="{{ route('customer.cart.remove') }}">
                                        @csrf
                                        <input type="hidden" name="product_variant_id" value="{{ $item->product_variant_id }}">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ labels('front_messages.remove', 'Remove') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row justify-content-end">
                <div class="col-md-4">
                    <div class="d-flex justify-content-between"><span>{{ labels('front_messages.sub_total', 'Sub Total') }}</span><span>{{ $currency_symbol ?? '$' }}{{ $totals['sub_total'] ?? 0 }}</span></div>
                    <div class="d-flex justify-content-between"><span>{{ labels('front_messages.delivery_charge', 'Delivery') }}</span><span>{{ $currency_symbol ?? '$' }}{{ $totals['delivery_charge'] ?? 0 }}</span></div>
                    <div class="d-flex justify-content-between fw-bold"><span>{{ labels('front_messages.total', 'Total') }}</span><span>{{ $currency_symbol ?? '$' }}{{ $totals['overall_amount'] ?? ($totals['sub_total'] ?? 0) }}</span></div>
                    <a href="{{ route('customer.checkout') }}" class="btn btn-dark w-100 mt-3">{{ labels('front_messages.proceed_to_checkout', 'Proceed to Checkout') }}</a>
                </div>
            </div>
        @endif
    </section>
@endsection
