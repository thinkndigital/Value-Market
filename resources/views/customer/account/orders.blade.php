@extends('customer.layout')

@section('content')
    <section class="container py-4">
        <h1 class="h3 mb-4">{{ labels('front_messages.my_orders', 'My Orders') }}</h1>

        @if ($orders->isEmpty())
            <p class="text-muted">{{ labels('front_messages.no_orders_yet', "You haven't placed any orders yet.") }}</p>
            <a href="{{ route('customer.products') }}" class="btn btn-dark">{{ labels('front_messages.continue_shopping', 'Continue Shopping') }}</a>
        @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>{{ labels('front_messages.order_id', 'Order #') }}</th>
                            <th>{{ labels('front_messages.date', 'Date') }}</th>
                            <th>{{ labels('front_messages.total', 'Total') }}</th>
                            <th>{{ labels('front_messages.payment_method', 'Payment') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td>#{{ $order->id ?? '' }}</td>
                                <td>{{ $order->created_at ?? '' }}</td>
                                <td>{{ $currency_symbol ?? '$' }}{{ $order->total ?? 0 }}</td>
                                <td>{{ $order->payment_method ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
