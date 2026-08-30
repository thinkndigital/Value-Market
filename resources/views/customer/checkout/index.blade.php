@extends('customer.layout')

@section('content')
    <section class="container py-4">
        <h1 class="h3 mb-4">{{ labels('front_messages.checkout', 'Checkout') }}</h1>

        <div class="row g-4">
            <div class="col-md-7">
                <h2 class="h5">{{ labels('front_messages.delivery_address', 'Delivery Address') }}</h2>
                @if ($addresses->isEmpty())
                    <p class="text-muted">{{ labels('front_messages.no_saved_addresses', 'No saved addresses yet.') }}</p>
                @else
                    <form method="POST" action="{{ route('customer.checkout.store') }}">
                        @csrf
                        @foreach ($addresses as $address)
                            <div class="form-check border rounded p-3 mb-2">
                                <input class="form-check-input" type="radio" name="address_id" id="address-{{ $address->id }}" value="{{ $address->id }}" {{ $loop->first ? 'checked' : '' }} required>
                                <label class="form-check-label" for="address-{{ $address->id }}">
                                    <strong>{{ $address->name }}</strong><br>
                                    {{ $address->address }}, {{ $address->city }}, {{ $address->state }} {{ $address->pincode }}
                                </label>
                            </div>
                        @endforeach

                        <p class="text-muted small mt-2">{{ labels('front_messages.payment_cod_only', 'Payment: Cash on Delivery') }}</p>
                        <button type="submit" class="btn btn-dark">{{ labels('front_messages.place_order', 'Place Order') }}</button>
                    </form>
                @endif
            </div>
            <div class="col-md-5">
                <h2 class="h5">{{ labels('front_messages.order_summary', 'Order Summary') }}</h2>
                <div class="d-flex justify-content-between"><span>{{ labels('front_messages.sub_total', 'Sub Total') }}</span><span>{{ $currency_symbol ?? '$' }}{{ $totals['sub_total'] ?? 0 }}</span></div>
                <div class="d-flex justify-content-between"><span>{{ labels('front_messages.delivery_charge', 'Delivery') }}</span><span>{{ $currency_symbol ?? '$' }}{{ $totals['delivery_charge'] ?? 0 }}</span></div>
                <div class="d-flex justify-content-between fw-bold"><span>{{ labels('front_messages.total', 'Total') }}</span><span>{{ $currency_symbol ?? '$' }}{{ $totals['overall_amount'] ?? ($totals['sub_total'] ?? 0) }}</span></div>
            </div>
        </div>
    </section>
@endsection
