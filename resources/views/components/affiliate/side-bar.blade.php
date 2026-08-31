<nav class="navbar-vertical navbar bg-white" {{ session()->get('is_rtl') == 1 ? 'dir=rtl' : '' }}>
    <div class="nav-scroller bg-white">
        @php
            use App\Services\SidebarService;
            $store_logo =
                !empty($system_settings['logo']) &&
                file_exists(public_path(config('constants.MEDIA_PATH') . $system_settings['logo']))
                    ? app(\App\Services\MediaService::class)->getMediaImageUrl($system_settings['logo'])
                    : asset('assets/img/default_full_logo.png');

            // Unified Dynamic Sidebar Engine (32-phase SaaS brief, Phase 3) - see docs/SIDEBAR_ENGINE.md.
            $sidebarTree = app(SidebarService::class)->build(auth()->user(), 'affiliate');
        @endphp
        <div class="sidenav-header">
            <a class="navbar-brand m-0" href="{{ route('affiliate.dashboard') }}">
                <img src="{{ $store_logo }}" class="navbar-brand-img" alt="main_logo">
            </a>
        </div>

        <ul class="navbar-nav" id="menuList">
            <x-sidebar.tree :items="$sidebarTree" />
        </ul>
    </div>
</nav>
