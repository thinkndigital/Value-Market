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

    {{-- Why Choose Us / About - Admin > General Settings > Short Description as the intro line, plus a
    standard 4-point value-prop grid every marketplace-style storefront uses (delivery/payment/selection/
    support) - no per-feature admin setting exists for these, so the copy is static, only the intro is
    data-driven. --}}
    <section class="container py-5">
        <h2 class="h3 text-center mb-2">{{ labels('front_messages.why_choose_us', 'Why Choose Us') }}</h2>
        @if (!empty($web_settings['app_short_description']))
            <p class="text-muted text-center mx-auto mb-4" style="max-width:700px">{{ $web_settings['app_short_description'] }}</p>
        @endif
        <div class="row g-4 text-center">
            <div class="col-6 col-md-3">
                <i class="anm anm-free-delivery fs-1" style="color:var(--brand-primary)"></i>
                <h3 class="h6 mt-2 mb-1">{{ labels('front_messages.fast_delivery', 'Fast Delivery') }}</h3>
                <p class="small text-muted mb-0">{{ labels('front_messages.fast_delivery_desc', 'Get your orders delivered quickly to your door.') }}</p>
            </div>
            <div class="col-6 col-md-3">
                <i class="anm anm-lock fs-1" style="color:var(--brand-primary)"></i>
                <h3 class="h6 mt-2 mb-1">{{ labels('front_messages.secure_payments', 'Secure Payments') }}</h3>
                <p class="small text-muted mb-0">{{ labels('front_messages.secure_payments_desc', 'Shop with confidence using trusted payment methods.') }}</p>
            </div>
            <div class="col-6 col-md-3">
                <i class="anm anm-basket fs-1" style="color:var(--brand-primary)"></i>
                <h3 class="h6 mt-2 mb-1">{{ labels('front_messages.wide_selection', 'Wide Selection') }}</h3>
                <p class="small text-muted mb-0">{{ labels('front_messages.wide_selection_desc', 'Thousands of products from trusted sellers.') }}</p>
            </div>
            <div class="col-6 col-md-3">
                <i class="anm anm-chat fs-1" style="color:var(--brand-primary)"></i>
                <h3 class="h6 mt-2 mb-1">{{ labels('front_messages.support_247', '24/7 Support') }}</h3>
                <p class="small text-muted mb-0">{{ labels('front_messages.support_247_desc', "We're here to help whenever you need us.") }}</p>
            </div>
        </div>
    </section>

    {{-- How It Works - a plain, static 4-step explainer (browse -> cart -> checkout -> delivery); no admin
    setting backs this, it's the same flow for every visitor. --}}
    <section class="bg-light py-5">
        <div class="container">
            <h2 class="h3 text-center mb-4">{{ labels('front_messages.how_it_works', 'How It Works') }}</h2>
            <div class="row g-4 text-center">
                <div class="col-6 col-md-3">
                    <div class="cust-step-number mx-auto mb-2">1</div>
                    <i class="anm anm-search-l fs-2 d-block mb-2" style="color:var(--brand-primary)"></i>
                    <h3 class="h6">{{ labels('front_messages.step_browse', 'Browse Products') }}</h3>
                </div>
                <div class="col-6 col-md-3">
                    <div class="cust-step-number mx-auto mb-2">2</div>
                    <i class="anm anm-cart-l fs-2 d-block mb-2" style="color:var(--brand-primary)"></i>
                    <h3 class="h6">{{ labels('front_messages.step_add_to_cart', 'Add To Cart') }}</h3>
                </div>
                <div class="col-6 col-md-3">
                    <div class="cust-step-number mx-auto mb-2">3</div>
                    <i class="anm anm-check-circle fs-2 d-block mb-2" style="color:var(--brand-primary)"></i>
                    <h3 class="h6">{{ labels('front_messages.step_checkout', 'Checkout Securely') }}</h3>
                </div>
                <div class="col-6 col-md-3">
                    <div class="cust-step-number mx-auto mb-2">4</div>
                    <i class="anm anm-free-delivery fs-2 d-block mb-2" style="color:var(--brand-primary)"></i>
                    <h3 class="h6">{{ labels('front_messages.step_delivery', 'Get It Delivered') }}</h3>
                </div>
            </div>
        </div>
    </section>

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
    {{-- "Join our platform" - every panel (seller/delivery_boy/affiliate/admin) already has its own real
    login route; the storefront never linked to any of them. Seller is the only one with a real public
    self-registration route (seller.register) - delivery boys and affiliates use their existing account
    credentials (affiliate.login accepts any active user; delivery-boy accounts are provisioned by admin/
    seller, not self-registered), so those two only get a login CTA, not a register one. Customer login
    already lives in the header, so it isn't repeated here. --}}
    <section class="cust-portals bg-light border-top py-5">
        <div class="container">
            <h2 class="h4 text-center mb-2">{{ labels('front_messages.join_platform_title', 'Join Our Platform') }}</h2>
            <p class="text-muted text-center mb-4">{{ labels('front_messages.join_platform_subtitle', 'Whether you sell, deliver, or promote - there is a place for you here.') }}</p>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="cust-portal-card border rounded p-4 text-center h-100 d-flex flex-column">
                        <i class="anm anm-shopping-cart4 fs-2 d-block mb-2"></i>
                        <h3 class="h6">{{ labels('front_messages.for_sellers', 'For Sellers') }}</h3>
                        <p class="small text-muted flex-grow-1">{{ labels('front_messages.for_sellers_desc', 'Open your own store and start selling to thousands of customers.') }}</p>
                        <div class="d-grid gap-2">
                            <a href="{{ route('seller.register') }}" class="btn btn-brand btn-sm">{{ labels('front_messages.become_a_seller', 'Become a Seller') }}</a>
                            <a href="{{ route('seller.login') }}" class="btn btn-outline-dark btn-sm">{{ labels('front_messages.seller_login', 'Seller Login') }}</a>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="cust-portal-card border rounded p-4 text-center h-100 d-flex flex-column">
                        <i class="anm anm-free-delivery fs-2 d-block mb-2"></i>
                        <h3 class="h6">{{ labels('front_messages.for_delivery_partners', 'For Delivery Partners') }}</h3>
                        <p class="small text-muted flex-grow-1">{{ labels('front_messages.for_delivery_partners_desc', 'Deliver orders and earn on your own schedule.') }}</p>
                        <div class="d-grid gap-2">
                            <a href="{{ route('delivery_boy.login') }}" class="btn btn-outline-dark btn-sm">{{ labels('front_messages.delivery_boy_login', 'Delivery Partner Login') }}</a>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="cust-portal-card border rounded p-4 text-center h-100 d-flex flex-column">
                        <i class="anm anm-users-l fs-2 d-block mb-2"></i>
                        <h3 class="h6">{{ labels('front_messages.for_affiliates', 'For Affiliates') }}</h3>
                        <p class="small text-muted flex-grow-1">{{ labels('front_messages.for_affiliates_desc', 'Share product links and earn commission on every sale.') }}</p>
                        <div class="d-grid gap-2">
                            <a href="{{ route('affiliate.login') }}" class="btn btn-outline-dark btn-sm">{{ labels('front_messages.affiliate_login', 'Affiliate Login') }}</a>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="cust-portal-card border rounded p-4 text-center h-100 d-flex flex-column">
                        <i class="anm anm-cogs fs-2 d-block mb-2"></i>
                        <h3 class="h6">{{ labels('front_messages.for_admins', 'Platform Admin') }}</h3>
                        <p class="small text-muted flex-grow-1">{{ labels('front_messages.for_admins_desc', 'Manage the platform from the admin dashboard.') }}</p>
                        <div class="d-grid gap-2">
                            <a href="{{ route('admin.login') }}" class="btn btn-outline-dark btn-sm">{{ labels('front_messages.admin_login', 'Admin Login') }}</a>
                        </div>
                    </div>
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
