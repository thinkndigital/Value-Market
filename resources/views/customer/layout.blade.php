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
    <link rel="stylesheet" href="{{ asset('frontend/elegant/css/swiper-bundle.min.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/elegant/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/elegant/css/theme.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/elegant/css/responsive.css') }}">

    @php
        // Real store branding (Admin > Stores > primary/secondary/active/hover color) applied as CSS custom
        // properties, matching the values components/header/header.blade.php originally exposed the same
        // way (#store-primary-color etc. hidden inputs) - here as actual page color, not just JS hooks.
        $storeDetails = app(\App\Services\StoreService::class)->getCurrentStoreData(session('store_id'));
        $storeDetails = json_decode($storeDetails ?? '[]');
        $brandPrimary = $storeDetails[0]->primary_color ?? '#041632';
        $brandSecondary = $storeDetails[0]->secondary_color ?? '#f4a51c';
        $brandActive = $storeDetails[0]->active_color ?? $brandPrimary;
        $brandHover = $storeDetails[0]->hover_color ?? $brandSecondary;
    @endphp
    <style>
        :root {
            --brand-primary: {{ $brandPrimary }};
            --brand-secondary: {{ $brandSecondary }};
            --brand-active: {{ $brandActive }};
            --brand-hover: {{ $brandHover }};
        }

        .cust-header {
            background: var(--brand-primary);
        }

        .cust-header .cust-logo,
        .cust-header .cust-nav a,
        .cust-header .cust-actions a,
        .cust-header .cust-actions .btn-link {
            color: #fff;
        }

        .cust-header .cust-nav a:hover,
        .cust-header .cust-actions a:hover {
            color: var(--brand-secondary);
        }

        .btn-brand {
            background: var(--brand-secondary);
            border-color: var(--brand-secondary);
            color: #212529;
        }

        .btn-brand:hover {
            background: var(--brand-hover);
            border-color: var(--brand-hover);
            color: #212529;
        }

        .cust-hero {
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-active));
        }

        /* style.css/theme.css set their own color directly on h1/p, which beats a merely-inherited value
        regardless of specificity - targeted explicitly here instead of relying on inheritance from .cust-hero. */
        .cust-hero,
        .cust-hero h1,
        .cust-hero p {
            color: #fff;
        }

        .cust-portal-card:hover {
            border-color: var(--brand-secondary) !important;
        }

        .cust-app-download,
        .cust-app-download h2,
        .cust-app-download p {
            color: #fff;
        }

        .cust-step-number {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--brand-secondary);
            color: #212529;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
    </style>
    @stack('styles')
</head>

<body>
    <!-- Storefront header (Phase 21 storefront build: adapted from components/header/header.blade.php -
    stripped of the Livewire wiring header.blade.php assumed, since app/Livewire/ doesn't exist in this
    codebase; kept the same nav shape - home/products/cart/account - with plain <a href> links instead). -->
    <header class="cust-header">
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
                    <i class="anm anm-cart-l fs-5"></i>
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
                    <a href="{{ route('customer.register') }}" class="btn btn-brand btn-sm">{{ labels('front_messages.register', 'Register') }}</a>
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
        {{-- "Login to every dashboard" and the App Download section are home-page-only marketing content
        (@stack('home_extra')) - showing a full portals/app-download block mid-checkout or on every product
        page would be clutter; the home page pushes its own copy of these via @push('home_extra'). --}}
        @stack('home_extra')
    </main>

    <footer class="cust-footer border-top mt-0 py-4">
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
