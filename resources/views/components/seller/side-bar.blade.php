<!-- Sidebar -->

<nav class="navbar-vertical navbar bg-white" {{ session()->get('is_rtl') == 1 ? 'dir=rtl' : '' }}>
    <div class="nav-scroller bg-white">
        @php

            use Chatify\ChatifyMessenger;
            use App\Services\SettingService;
            use App\Services\SidebarService;
            $setting = app(SettingService::class)->getSettings('system_settings', true);
            $setting = json_decode($setting, true);

            $logo = file_exists(public_path(config('constants.MEDIA_PATH') . $setting['logo']))
                ? asset(config('constants.MEDIA_PATH') . $setting['logo'])
                : asset(config('constants.DEFAULT_LOGO'));

            // Unified Dynamic Sidebar Engine (32-phase SaaS brief, Phase 3) - see docs/SIDEBAR_ENGINE.md.
            $sidebarTree = app(SidebarService::class)->build(auth()->user(), 'seller');
         @endphp
        <div class="sidenav-header">
            <a class="navbar-brand m-0" href="{{ route('seller.home') }}" target="">
                <img src="{{ $logo }}" class="navbar-brand-img" alt="main_logo">
            </a>
        </div>

        <!-- code for menu search -->

        <div class="ps-2 pe-2 mt-4">
            <!-- Search Bar -->
            <input type="text" class="form-control menuSearch" placeholder="Search Menu">
        </div>

         <ul class="navbar-nav" id="menuList">
             <x-sidebar.tree :items="$sidebarTree" />
        </ul>
    </div>
</nav>
