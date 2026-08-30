@extends('customer.layout')

@section('content')
    <section class="container py-4">
        <h1 class="h3 mb-4">{{ labels('front_messages.my_account', 'My Account') }}</h1>
        <ul class="list-unstyled">
            <li><strong>{{ labels('front_messages.name', 'Name') }}:</strong> {{ $user->username }}</li>
            <li><strong>{{ labels('front_messages.email', 'Email') }}:</strong> {{ $user->email }}</li>
            <li><strong>{{ labels('front_messages.mobile', 'Mobile') }}:</strong> {{ $user->mobile }}</li>
        </ul>
        <a href="{{ route('customer.account.orders') }}" class="btn btn-outline-dark">{{ labels('front_messages.my_orders', 'My Orders') }}</a>
    </section>
@endsection
