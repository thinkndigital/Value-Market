@extends('customer.layout')

@section('content')
    {{-- Hero: real banners (Admin > Sliders) when configured, otherwise the app's own name/tagline/logo so
    the page never looks blank while sliders are empty. --}}
    @if ($sliders->isNotEmpty())
        <section class="cust-hero-slider">
            <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                    @foreach ($sliders as $index => $slider)
                        <div class="carousel-item {{ $index === 0 ? 'active' : '' }}">
                            @if (!empty($slider->link))
                                <a href="{{ $slider->link }}">
                                    <img src="{{ $slider->image }}" class="d-block w-100" alt="Banner" style="max-height:420px;object-fit:cover">
                                </a>
                            @else
                                <img src="{{ $slider->image }}" class="d-block w-100" alt="Banner" style="max-height:420px;object-fit:cover">
                            @endif
                        </div>
                    @endforeach
                </div>
                @if ($sliders->count() > 1)
                    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    </button>
                @endif
            </div>
        </section>
    @else
        <section class="cust-hero py-5">
            <div class="container text-center">
                <h1 class="display-5 fw-bold">{{ $system_settings['app_name'] ?? 'Store' }}</h1>
                @if (!empty($web_settings['app_short_description']))
                    <p class="fs-5 mx-auto" style="max-width:640px">{{ $web_settings['app_short_description'] }}</p>
                @endif
                <a href="{{ route('customer.products') }}" class="btn btn-brand btn-lg mt-2">{{ labels('front_messages.shop_now', 'Shop Now') }}</a>
            </div>
        </section>
    @endif

    {{-- About / what this platform is - Admin > General Settings > Short Description, the same field the
    old (never-wired) header component read as $settings->app_short_description. --}}
    @if (!empty($web_settings['app_short_description']) && $sliders->isNotEmpty())
        <section class="container py-4 text-center">
            <p class="fs-5 mx-auto text-muted" style="max-width:700px">{{ $web_settings['app_short_description'] }}</p>
        </section>
    @endif

    <section class="container py-4">
        <h2 class="h3 mb-4">{{ labels('front_messages.shop_by_category', 'Shop by Category') }}</h2>
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
        <h2 class="h3 mb-4">{{ labels('front_messages.new_arrivals', 'New Arrivals') }}</h2>
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

@push('home_extra')
    {{-- "Login to every dashboard" - every panel (admin/seller/delivery_boy/affiliate) already has its own
    real login route; the storefront never linked to any of them. Customer login is already in the header. --}}
    <section class="cust-portals bg-light border-top py-5">
        <div class="container">
            <h2 class="h4 text-center mb-4">{{ labels('front_messages.login_portals_title', 'Sign In To Your Dashboard') }}</h2>
            <div class="row g-3 text-center">
                <div class="col-6 col-md-3">
                    <a href="{{ route('seller.login') }}" class="cust-portal-card d-block border rounded p-4 text-decoration-none text-dark h-100">
                        <i class="anm anm-shopping-cart4 fs-2 d-block mb-2"></i>
                        {{ labels('front_messages.seller_login', 'Seller Login') }}
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="{{ route('delivery_boy.login') }}" class="cust-portal-card d-block border rounded p-4 text-decoration-none text-dark h-100">
                        <i class="anm anm-free-delivery fs-2 d-block mb-2"></i>
                        {{ labels('front_messages.delivery_boy_login', 'Delivery Partner Login') }}
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="{{ route('affiliate.login') }}" class="cust-portal-card d-block border rounded p-4 text-decoration-none text-dark h-100">
                        <i class="anm anm-users-l fs-2 d-block mb-2"></i>
                        {{ labels('front_messages.affiliate_login', 'Affiliate Login') }}
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="{{ route('admin.login') }}" class="cust-portal-card d-block border rounded p-4 text-decoration-none text-dark h-100">
                        <i class="anm anm-cogs fs-2 d-block mb-2"></i>
                        {{ labels('front_messages.admin_login', 'Admin Login') }}
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- App Download section - reuses the admin-configured web_settings.app_download_section_* fields
    (Admin > General Settings > App Download Section), already there for exactly this purpose but never
    rendered anywhere before this page existed. --}}
    @if (($web_settings['app_download_section'] ?? '0') == '1')
        <section class="cust-app-download py-5" style="background: var(--brand-primary);">
            <div class="container text-center">
                <h2 class="h4">{{ $web_settings['app_download_section_title'] ?? labels('front_messages.get_the_app', 'Get The App') }}</h2>
                @if (!empty($web_settings['app_download_section_tagline']))
                    <p class="fs-5">{{ $web_settings['app_download_section_tagline'] }}</p>
                @endif
                @if (!empty($web_settings['app_download_section_short_description']))
                    <p class="mx-auto" style="max-width:600px">{{ $web_settings['app_download_section_short_description'] }}</p>
                @endif
                <div class="d-flex gap-3 justify-content-center mt-3 flex-wrap">
                    @if (!empty($web_settings['app_download_section_playstore_url']))
                        <a href="{{ $web_settings['app_download_section_playstore_url'] }}" class="btn btn-brand" target="_blank" rel="noopener">
                            <i class="anm anm-google-play me-1"></i> {{ labels('front_messages.get_it_on_google_play', 'Get it on Google Play') }}
                        </a>
                    @endif
                    @if (!empty($web_settings['app_download_section_appstore_url']))
                        <a href="{{ $web_settings['app_download_section_appstore_url'] }}" class="btn btn-brand" target="_blank" rel="noopener">
                            <i class="anm anm-apple me-1"></i> {{ labels('front_messages.download_on_the_app_store', 'Download on the App Store') }}
                        </a>
                    @endif
                </div>
            </div>
        </section>
    @endif
@endpush
