 <!-- Sidebar -->
 <nav class="navbar-vertical navbar bg-white" {{ session()->get('is_rtl') == 1 ? 'dir=rtl' : '' }}>
     <div class="nav-scroller bg-white">
         @php
             $user = auth()->user();
             use Chatify\ChatifyMessenger;
             use App\Services\MediaService;
             use App\Services\SettingService;
             use App\Services\SidebarService;
             $setting = app(SettingService::class)->getSettings('system_settings', true);
             $setting = json_decode($setting, true);

             $sms_gateway_settings = app(SettingService::class)->getSettings('sms_gateway_settings');

             // Unified Dynamic Sidebar Engine (32-phase SaaS brief, Phase 3): the hand-written <li> list
             // that used to live here (see docs/ADMIN_SIDEBAR_REGROUP.md for its group structure) is now
             // config/sidebar.php['admin'], resolved per-user by SidebarService and rendered by the shared
             // <x-sidebar.tree> component - see docs/SIDEBAR_ENGINE.md.
             $sidebarTree = app(SidebarService::class)->build($user, 'admin');
         @endphp
         <input type="hidden" id="sms_gateway_data" value='{{ $sms_gateway_settings }}' />
         <div class="sidenav-header">
             <i class="fas fa-times p-3 cursor-pointer text-secondary opacity-5 position-absolute end-0 top-0 d-none d-xl-none"
                 aria-hidden="true" id="iconSidenav"></i>
             <a class="navbar-brand m-0" href="{{ route('admin.home') }}" target="">
                 @php
                     $store_logo =
                         !empty($setting['logo']) &&
                         file_exists(public_path(config('constants.MEDIA_PATH') . $setting['logo']))
                             ? app(MediaService::class)->getMediaImageUrl($setting['logo'])
                             : asset('assets/img/default_full_logo.png');
                 @endphp
                 <img src="{{ $store_logo }}" class="navbar-brand-img" alt="main_logo">
             </a>
         </div>
         <hr class="horizontal dark mt-0">

         <!-- code for menu search -->

         <div class="ps-2 pe-2">
             <!-- Search Bar -->
             <input type="text" class="form-control menuSearch" placeholder="Search Menu...">
         </div>


         <ul class="navbar-nav" id="menuList">
             <x-sidebar.tree :items="$sidebarTree" />
         </ul>
     </div>
 </nav>
