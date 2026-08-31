 <!-- Sidebar -->
 @php
 use App\Services\SettingService;
     $setting = app(SettingService::class)->getSettings('system_settings', true);
     $setting = json_decode($setting, true);
     $logo = file_exists(public_path(config('constants.MEDIA_PATH') . $setting['logo']))
         ? asset(config('constants.MEDIA_PATH') . $setting['logo'])
         : asset(config('constants.DEFAULT_LOGO'));
 @endphp

<nav class="navbar-vertical navbar bg-white" {{ session()->get('is_rtl') == 1 ? 'dir=rtl' : '' }}>
     <div class="nav-scroller bg-white">
         <div class="sidenav-header">
             <i class="fas fa-times p-3 cursor-pointer text-secondary opacity-5 position-absolute end-0 top-0 d-none d-xl-none"
                 aria-hidden="true" id="iconSidenav"></i>
             <a class="navbar-brand m-0" href="{{ route('wholesaler.home') }}" target="">
                 <img src="{{ $logo }}" class="navbar-brand-img" alt="main_logo">
             </a>
         </div>
         <hr class="horizontal dark mt-0">

         <div class="ps-2 pe-2 mt-4">
             <input type="text" class="form-control menuSearch" placeholder="Search Menu">
         </div>

         <ul class="navbar-nav">
             <li class="sidebar-title ms-3"><i class='bx bx-tachometer'></i>
                 {{ labels('admin_labels.dashboard', 'Dashboard') }}
             </li>
             <li class="nav-item ms-3">
                 <a class="nav-link {{ Request::is('wholesaler/home') ? 'active' : '' }}"
                     href="{{ route('wholesaler.home') }}">
                     <span class="nav-link-text ms-1">{{ labels('admin_labels.dashboard', 'Dashboard') }}</span>
                 </a>
             </li>
             <li class="nav-item ms-3">
                 <a class="nav-link {{ Request::is('wholesaler/products*') ? 'active' : '' }}"
                     href="{{ route('wholesaler.products.index') }}">
                     <i class='bx bx-package'></i>
                     <span class="nav-link-text ms-1">{{ labels('wholesaler_labels.my_products', 'My Products') }}</span>
                 </a>
             </li>
             <li class="nav-item ms-3">
                 <a class="nav-link {{ Request::is('wholesaler/orders*') ? 'active' : '' }}"
                     href="{{ route('wholesaler.orders.index') }}">
                     <i class='bx bx-cart'></i>
                     <span class="nav-link-text ms-1">{{ labels('wholesaler_labels.orders', 'Orders') }}</span>
                 </a>
             </li>
             <li class="nav-item ms-3">
                 <a class="nav-link {{ Request::is('wholesaler/stock*') ? 'active' : '' }}"
                     href="{{ route('wholesaler.stock.index') }}">
                     <i class='bx bx-box'></i>
                     <span class="nav-link-text ms-1">{{ labels('wholesaler_labels.stock', 'Stock') }}</span>
                 </a>
             </li>
             <li class="nav-item ms-3">
                 <a class="nav-link {{ Request::is('wholesaler/reports*') ? 'active' : '' }}"
                     href="{{ route('wholesaler.reports.sales') }}">
                     <i class='bx bx-line-chart'></i>
                     <span class="nav-link-text ms-1">{{ labels('wholesaler_labels.sales', 'Sales') }}</span>
                 </a>
             </li>
             <li class="nav-item ms-3">
                 <a class="nav-link {{ Request::is('wholesaler/clients*') ? 'active' : '' }}"
                     href="{{ route('wholesaler.clients.index') }}">
                     <i class='bx bx-group'></i>
                     <span class="nav-link-text ms-1">{{ labels('wholesaler_labels.my_buyers', 'My Buyers') }}</span>
                 </a>
             </li>
         </ul>
     </div>
 </nav>
