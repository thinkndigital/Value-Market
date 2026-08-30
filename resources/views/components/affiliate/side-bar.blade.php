<nav class="navbar-vertical navbar bg-white" {{ session()->get('is_rtl') == 1 ? 'dir=rtl' : '' }}>
    <div class="nav-scroller bg-white">
        @php
            $store_logo =
                !empty($system_settings['logo']) &&
                file_exists(public_path(config('constants.MEDIA_PATH') . $system_settings['logo']))
                    ? app(\App\Services\MediaService::class)->getMediaImageUrl($system_settings['logo'])
                    : asset('assets/img/default_full_logo.png');
        @endphp
        <div class="sidenav-header">
            <a class="navbar-brand m-0" href="{{ route('affiliate.dashboard') }}">
                <img src="{{ $store_logo }}" class="navbar-brand-img" alt="main_logo">
            </a>
        </div>

        <ul class="navbar-nav" id="menuList">
            <li class="sidebar-title"><i class='bx bx-tachometer'></i>
                {{ labels('admin_labels.dashboard', 'Dashboard') }}
            </li>
            <li class="nav-item">
                <a class="nav-link {{ Request::is('affiliate/dashboard') ? 'active' : '' }}"
                    href="{{ route('affiliate.dashboard') }}">
                    <span class="nav-link-text">{{ labels('admin_labels.home', 'Home') }}</span>
                </a>
            </li>

            <li class="sidebar-title"><i class='bx bx-package'></i>
                {{ labels('admin_labels.products', 'Products') }}
            </li>
            <li class="nav-item">
                <a class="nav-link {{ Request::is('affiliate/products') || Request::is('affiliate/products/*') ? 'active' : '' }}"
                    href="{{ route('affiliate.products.page') }}">
                    <span class="nav-link-text">{{ labels('admin_labels.available_products', 'Available Products') }}</span>
                </a>
            </li>

            <li class="sidebar-title"><i class='bx bx-store'></i>
                {{ labels('admin_labels.stores', 'Stores') }}
            </li>
            <li class="nav-item">
                <a class="nav-link {{ Request::is('affiliate/private_stores') ? 'active' : '' }}"
                    href="{{ route('affiliate.stores.page') }}">
                    <span class="nav-link-text">{{ labels('admin_labels.private_stores', 'Private Stores') }}</span>
                </a>
            </li>

            <li class="sidebar-title"><i class='bx bx-line-chart'></i>
                {{ labels('admin_labels.earnings', 'Earnings') }}
            </li>
            <li class="nav-item">
                <a class="nav-link {{ Request::is('affiliate/commissions') ? 'active' : '' }}"
                    href="{{ route('affiliate.commissions.page') }}">
                    <span class="nav-link-text">{{ labels('admin_labels.commission_history', 'Commission History') }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ Request::is('affiliate/withdrawals') ? 'active' : '' }}"
                    href="{{ route('affiliate.withdrawals.page') }}">
                    <span class="nav-link-text">{{ labels('admin_labels.withdrawals', 'Withdrawals') }}</span>
                </a>
            </li>
        </ul>
    </div>
</nav>
