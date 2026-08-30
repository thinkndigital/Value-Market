<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" {{ (session('is_rtl') ?? 0) == 1 ? 'dir=rtl' : '' }}>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? ($system_settings['app_name'] ?? 'Store') }}</title>
    @if (!empty($web_settings['favicon']))
        <link rel="shortcut icon" href="{{ asset('storage/' . $web_settings['favicon']) }}" type="image/x-icon">
    @endif

    {{-- plugins.css bundles the real Bootstrap 5 framework this theme's own markup assumes (.container,
    .d-flex, .btn, .form-control, .row/.col-*, etc.) - style.css/theme.css only add this theme's own classes
    on top of it, they don't include Bootstrap itself. Omitting this rendered every Bootstrap-class element
    on every storefront page as unstyled plain text (confirmed live: no header layout, no button styling,
    no form styling) - matches app.blade.php's own asset order, which loads this first for the same reason. --}}
    <link rel="stylesheet" href="{{ asset('frontend/elegant/css/plugins.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/elegant/css/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/elegant/css/swiper-bundle.min.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/elegant/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/elegant/css/theme.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/elegant/css/responsive.css') }}">
    @stack('styles')
</head>

<body>
    <!-- Storefront header (Phase 21 storefront build: adapted from components/header/header.blade.php -
    stripped of the Livewire wiring header.blade.php assumed, since app/Livewire/ doesn't exist in this
    codebase; kept the same nav shape - home/products/cart/account - with plain <a href> links instead). -->
    <header class="cust-header border-bottom">
        <div class="container d-flex align-items-center justify-content-between py-3">
            <a href="{{ route('home') }}" class="cust-logo text-decoration-none fw-bold fs-4">
                @if (!empty($web_settings['logo']))
                    <img src="{{ asset('storage/' . $web_settings['logo']) }}" alt="{{ $system_settings['app_name'] ?? 'Store' }}" style="max-height:40px">
                @else
                    {{ $system_settings['app_name'] ?? 'Store' }}
                @endif
            </a>
            <nav class="cust-nav d-none d-md-flex gap-4">
                <a href="{{ route('home') }}">{{ labels('front_messages.home', 'Home') }}</a>
                <a href="{{ route('customer.products') }}">{{ labels('front_messages.products', 'Products') }}</a>
            </nav>
            <div class="cust-actions d-flex align-items-center gap-3">
                <a href="{{ route('customer.cart') }}" class="position-relative">
                    <i class="bi bi-cart3 fs-5"></i>
                </a>
                @auth('web')
                    @if ((int) auth('web')->user()->role_id === \App\Models\Role::CUSTOMER)
                        <a href="{{ route('customer.account') }}">{{ labels('front_messages.my_account', 'My Account') }}</a>
                        <form method="POST" action="{{ route('customer.logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-link p-0">{{ labels('front_messages.sign_out', 'Sign out') }}</button>
                        </form>
                    @else
                        <a href="{{ route('customer.login') }}">{{ labels('front_messages.sign_in', 'Sign In') }}</a>
                    @endif
                @else
                    <a href="{{ route('customer.login') }}">{{ labels('front_messages.sign_in', 'Sign In') }}</a>
                    <a href="{{ route('customer.register') }}">{{ labels('front_messages.register', 'Register') }}</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="cust-main">
        @if (session('status'))
            <div class="container mt-3">
                <div class="alert alert-success">{{ session('status') }}</div>
            </div>
        @endif
        @if (session('error'))
            <div class="container mt-3">
                <div class="alert alert-danger">{{ session('error') }}</div>
            </div>
        @endif
        @if ($errors->any())
            <div class="container mt-3">
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="cust-footer border-top mt-5 py-4">
        <div class="container d-flex flex-wrap justify-content-between align-items-center">
            <div>&copy; {{ date('Y') }} {{ $system_settings['app_name'] ?? 'Store' }}</div>
            <div class="d-flex gap-3">
                <a href="{{ route('home') }}">{{ labels('front_messages.home', 'Home') }}</a>
                <a href="{{ route('customer.products') }}">{{ labels('front_messages.products', 'Products') }}</a>
            </div>
        </div>
    </footer>

    <script src="{{ asset('frontend/elegant/js/plugins.js') }}"></script>
    @stack('scripts')
</body>

</html>
