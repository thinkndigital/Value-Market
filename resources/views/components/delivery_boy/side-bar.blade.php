 <!-- Sidebar -->
 @php
 use App\Services\SettingService;
     $setting = app(SettingService::class)->getSettings('system_settings', true);
     $setting = json_decode($setting, true);
     $logo = file_exists(public_path(config('constants.MEDIA_PATH') . $setting['logo']))
         ? asset(config('constants.MEDIA_PATH') . $setting['logo'])
         : asset(config('constants.DEFAULT_LOGO'));

     // Sidebar regroup (32-phase SaaS brief, same pass as admin/seller - see docs/ADMIN_SIDEBAR_REGROUP.md):
     // every route/label below is unchanged, this only wraps the existing sections in the same collapsible
     // group pattern used across the other panels, for a consistent look. Deliberately still small and flat
     // per the brief's own instruction to keep the delivery boy dashboard minimal.
     $group_deliveries_active = Request::is('delivery_boy/orders*') || Request::is('delivery_boy/returned_orders*');
     $group_finance_active = Request::is('delivery_boy/cash_collection*') || Request::is('delivery_boy/fund_transfer*') || Request::is('delivery_boy/wallet_transaction*');
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
             <li class="sidebar-title ms-3"><i class='bx bx-tachometer'></i>
                 {{ labels('admin_labels.dashboard', 'Dashboard') }}
             </li>
             <li class="nav-item ms-3">
                 <a class="nav-link {{ Request::is('delivery_boy/home') || Request::is('delivery_boy/home/*') ? 'active' : '' }}"
                     href="{{ route('delivery_boy.home') }}">
                     <span class="nav-link-text ms-1">{{ labels('admin_labels.dashboard', 'Dashboard') }}</span>
                 </a>
             </li>

             {{-- ===================== DELIVERIES ===================== --}}
             <li class="nav-item ms-3">
                 <a data-bs-toggle="collapse" href="#group_deliveries"
                     class="nav-link sidebar-group-toggle {{ $group_deliveries_active ? '' : 'collapsed' }} {{ $group_deliveries_active ? 'active' : '' }}"
                     aria-controls="group_deliveries" role="button" aria-expanded="false">
                     <i class='bx bx-package'></i>
                     <span class="nav-link-text ms-1">{{ labels('admin_labels.group_deliveries', 'Deliveries') }}</span>
                     <i class="fas fa-angle-down"></i>
                 </a>
                 <div class="collapse {{ $group_deliveries_active ? 'show' : '' }}" id="group_deliveries">
                     <ul class="nav">
                         <li class="nav-item">
                             <a data-bs-toggle="collapse" href="#order_dropdown"
                                 class="nav-link collapsed {{ Request::is('delivery_boy/orders') || Request::is('delivery_boy/orders*') ? 'active' : '' }}"
                                 aria-controls="order_dropdown" role="button" aria-expanded="false">

                                 <span class="nav-link-text ms-1">{{ labels('admin_labels.orders_manage', 'Orders Manage') }}</span>
                             </a>
                             <div class="collapse" id="order_dropdown">
                                 <ul class="nav">
                                     <li class="nav-item">
                                         <a class="nav-link {{ Request::is('delivery_boy/orders') ? 'active' : '' }}"
                                             href="{{ route('delivery_boy.orders.index') }}">
                                             <span class="nav-link-text ms-1">{{ labels('admin_labels.orders', 'Orders') }}</span>
                                         </a>
                                     </li>
                                 </ul>
                             </div>
                         </li>
                         <li class="nav-item ms-3">
                            <a class="nav-link {{ Request::is('delivery_boy/returned_orders') ? 'active' : '' }}"
                                href="{{ route('delivery_boy.cash.returned_order') }}">
                                <span
                                    class="nav-link-text ms-1">{{ labels('admin_labels.returned_orders', 'Returned Orders') }}</span>
                            </a>
                        </li>
                     </ul>
                 </div>
             </li>

             {{-- ===================== FINANCE ===================== --}}
             <li class="nav-item ms-3">
                 <a data-bs-toggle="collapse" href="#group_finance"
                     class="nav-link sidebar-group-toggle {{ $group_finance_active ? '' : 'collapsed' }} {{ $group_finance_active ? 'active' : '' }}"
                     aria-controls="group_finance" role="button" aria-expanded="false">
                     <i class='bx bx-wallet-alt'></i>
                     <span class="nav-link-text ms-1">{{ labels('admin_labels.group_finance', 'Finance') }}</span>
                     <i class="fas fa-angle-down"></i>
                 </a>
                 <div class="collapse {{ $group_finance_active ? 'show' : '' }}" id="group_finance">
                     <ul class="nav">
                         <li class="nav-item ms-3">
                             <a class="nav-link {{ Request::is('delivery_boy/cash_collection') ? 'active' : '' }}"
                                 href="{{ route('delivery_boy.cash.collection') }}">
                                 <span
                                     class="nav-link-text ms-1">{{ labels('admin_labels.cash_collection', 'Cash Collection') }}</span>
                             </a>
                         </li>
                         <li class="nav-item ms-3">
                             <a class="nav-link {{ Request::is('delivery_boy/fund_transfer') ? 'active' : '' }}"
                                 href="{{ route('delivery_boy.fund.transfer') }}">
                                 <span
                                     class="nav-link-text ms-1">{{ labels('admin_labels.fund_transfer', 'Fund Transfer') }}</span>
                             </a>
                         </li>
                         <li class="nav-item ms-3">
                             <a class="nav-link {{ Request::is('delivery_boy/wallet_transaction') ? 'active' : '' }}"
                                 href="{{ route('delivery_boy.walletTransaction') }}">
                                 <span
                                     class="nav-link-text ms-1">{{ labels('admin_labels.wallet_transaction', 'Wallet Transaction') }}</span>
                             </a>
                         </li>
                     </ul>
                 </div>
             </li>
         </ul>



     </div>
 </nav>
