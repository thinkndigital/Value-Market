@extends('customer.layout')

@section('content')
    <section class="container py-5" style="max-width:480px">
        <h1 class="h3 mb-4">{{ labels('front_messages.register', 'Register') }}</h1>
        <form method="POST" action="{{ route('customer.register.submit') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">{{ labels('front_messages.name', 'Name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ labels('front_messages.email', 'Email') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
            </div>
            <div class="row">
                <div class="col-4 mb-3">
                    <label class="form-label">{{ labels('front_messages.country_code', 'Country Code') }}</label>
                    <input type="text" name="country_code" value="{{ old('country_code', '+1') }}" class="form-control" required>
                </div>
                <div class="col-8 mb-3">
                    <label class="form-label">{{ labels('front_messages.mobile', 'Mobile') }}</label>
                    <input type="text" name="mobile" value="{{ old('mobile') }}" class="form-control" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ labels('front_messages.password', 'Password') }}</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ labels('front_messages.confirm_password', 'Confirm Password') }}</label>
                <input type="password" name="password_confirmation" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-dark w-100">{{ labels('front_messages.register', 'Register') }}</button>
        </form>
        <p class="mt-3">
            {{ labels('front_messages.already_have_account', 'Already have an account?') }}
            <a href="{{ route('customer.login') }}">{{ labels('front_messages.sign_in', 'Sign In') }}</a>
        </p>
    </section>
@endsection
