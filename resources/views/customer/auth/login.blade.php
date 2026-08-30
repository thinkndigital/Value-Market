@extends('customer.layout')

@section('content')
    <section class="container py-5" style="max-width:420px">
        <h1 class="h3 mb-4">{{ labels('front_messages.sign_in', 'Sign In') }}</h1>
        <form method="POST" action="{{ route('customer.login.submit') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">{{ labels('front_messages.mobile', 'Mobile') }}</label>
                <input type="text" name="mobile" value="{{ old('mobile') }}" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ labels('front_messages.password', 'Password') }}</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-dark w-100">{{ labels('front_messages.sign_in', 'Sign In') }}</button>
        </form>
        <p class="mt-3">
            {{ labels('front_messages.dont_have_account', "Don't have an account?") }}
            <a href="{{ route('customer.register') }}">{{ labels('front_messages.register', 'Register') }}</a>
        </p>
    </section>
@endsection
