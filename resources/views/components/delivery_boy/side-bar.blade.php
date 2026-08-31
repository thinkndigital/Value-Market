 <!-- Sidebar -->
 @php
 use App\Services\SettingService;
 use App\Services\SidebarService;
     $setting = app(SettingService::class)->getSettings('system_settings', true);
     $setting = json_decode($setting, true);
     $logo = file_exists(public_path(config('constants.MEDIA_PATH') . $setting['logo']))
         ? asset(config('constants.MEDIA_PATH') . $setting['logo'])
         : asset(config('constants.DEFAULT_LOGO'));

     // Unified Dynamic Sidebar Engine (32-phase SaaS brief, Phase 3) - see docs/SIDEBAR_ENGINE.md.
     $sidebarTree = app(SidebarService::class)->build(auth()->user(), 'delivery_boy');
 @endphp

<nav class="navbar-vertical navbar bg-white" {{ session()->get('is_rtl') == 1 ? 'dir=rtl' : '' }}>
     <div class="nav-scroller bg-white">
         <div class="sidenav-header">
             <i class="fas fa-times p-3 cursor-pointer text-secondary opacity-5 position-absolute end-0 top-0 d-none d-xl-none"
                 aria-hidden="true" id="iconSidenav"></i>
             <a class="navbar-brand m-0" href="{{ route('delivery_boy.home') }}" target="">
                 <img src="{{ $logo }}" class="navbar-brand-img" alt="main_logo">
             </a>
         </div>
         <hr class="horizontal dark mt-0">

         <!-- code for menu search -->

         <div class="ps-2 pe-2 mt-4">
             <!-- Search Bar -->
             <input type="text" class="form-control menuSearch" placeholder="Search Menu">
         </div>

         <ul class="navbar-nav">
             <x-sidebar.tree :items="$sidebarTree" />
         </ul>
     </div>
 </nav>
