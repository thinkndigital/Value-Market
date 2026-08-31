<nav class="navbar-vertical navbar bg-white" {{ session()->get('is_rtl') == 1 ? 'dir=rtl' : '' }}>
    <div class="nav-scroller bg-white">
        @php
            $store_logo =
                !empty($system_settings['logo']) &&
                file_exists(public_path(config('constants.MEDIA_PATH') . $system_settings['logo']))
                    ? app(\App\Services\MediaService::class)->getMediaImageUrl($system_settings['logo'])
                    : asset('assets/img/default_full_logo.png');

            // Sidebar regroup (32-phase SaaS brief, same pass as admin/seller/delivery_boy - see
            // docs/ADMIN_SIDEBAR_REGROUP.md): every route/label below is unchanged, just wrapped in the same
            // collapsible group pattern used across the other panels.
            $group_marketplace_active = Request::is('affiliate/products*') || Request::is('affiliate/private_stores*');
            $group_earnings_active = Request::is('affiliate/commissions*') || Request::is('affiliate/withdrawals*');
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

            {{-- ===================== MARKETPLACE (Products / Stores) ===================== --}}
            <li class="nav-item">
                <a data-bs-toggle="collapse" href="#group_marketplace"
                    class="nav-link sidebar-group-toggle {{ $group_marketplace_active ? '' : 'collapsed' }} {{ $group_marketplace_active ? 'active' : '' }}"
                    aria-controls="group_marketplace" role="button" aria-expanded="false">
                    <i class='bx bx-package'></i>
                    <span class="nav-link-text">{{ labels('admin_labels.group_marketplace', 'Marketplace') }}</span>
                    <i class="fas fa-angle-down"></i>
                </a>
                <div class="collapse {{ $group_marketplace_active ? 'show' : '' }}" id="group_marketplace">
                    <ul class="nav">
                        <li class="nav-item">
                            <a class="nav-link {{ Request::is('affiliate/products') || Request::is('affiliate/products/*') ? 'active' : '' }}"
                                href="{{ route('affiliate.products.page') }}">
                                <span class="nav-link-text">{{ labels('admin_labels.available_products', 'Available Products') }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Request::is('affiliate/private_stores') ? 'active' : '' }}"
                                href="{{ route('affiliate.stores.page') }}">
                                <span class="nav-link-text">{{ labels('admin_labels.private_stores', 'Private Stores') }}</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            {{-- ===================== EARNINGS ===================== --}}
            <li class="nav-item">
                <a data-bs-toggle="collapse" href="#group_earnings"
                    class="nav-link sidebar-group-toggle {{ $group_earnings_active ? '' : 'collapsed' }} {{ $group_earnings_active ? 'active' : '' }}"
                    aria-controls="group_earnings" role="button" aria-expanded="false">
                    <i class='bx bx-line-chart'></i>
                    <span class="nav-link-text">{{ labels('admin_labels.earnings', 'Earnings') }}</span>
                    <i class="fas fa-angle-down"></i>
                </a>
                <div class="collapse {{ $group_earnings_active ? 'show' : '' }}" id="group_earnings">
                    <ul class="nav">
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
            </li>
        </ul>
    </div>
</nav>
