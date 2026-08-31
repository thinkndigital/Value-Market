<!-- Sidebar -->

<nav class="navbar-vertical navbar bg-white" {{ session()->get('is_rtl') == 1 ? 'dir=rtl' : '' }}>
    <div class="nav-scroller bg-white">
        @php

            use Chatify\ChatifyMessenger;
            use App\Services\SettingService;
            $setting = app(SettingService::class)->getSettings('system_settings', true);
            $setting = json_decode($setting, true);

            $messenger = new ChatifyMessenger();
            $unread = $messenger->totalUnseenMessages();

            $logo = file_exists(public_path(config('constants.MEDIA_PATH') . $setting['logo']))
                ? asset(config('constants.MEDIA_PATH') . $setting['logo'])
                : asset(config('constants.DEFAULT_LOGO'));

            // Sidebar regroup (32-phase SaaS brief, same pass as the admin sidebar - see
            // docs/ADMIN_SIDEBAR_REGROUP.md): every route/label below is unchanged, this only adds a
            // collapsible group wrapper around related sections, auto-expanded from the same Request::is()
            // patterns each item already checked individually.
            $group_sales_active = Request::is('seller/orders*') || Request::is('seller/point_of_sale*') || Request::is('seller/manage_stock*') || Request::is('seller/manage_combo_stock*') || Request::is('seller/manage_product_deliverability*') || Request::is('seller/manage_combo_product_deliverability*') || Request::is('seller/return_request*');
            $group_catalog_active = Request::is('seller/categories*') || Request::is('seller/brands*') || Request::is('seller/tax*') || Request::is('seller/products/attributes*') || Request::is('seller/products*') || Request::is('seller/product_faqs*') || Request::is('seller/product/*') || Request::is('seller/combo_product*') || Request::is('seller/combo_products*');
            $group_website_active = Request::is('seller/media*');
            $group_marketing_active = Request::is('seller/affiliate_program*');
            $group_finance_active = Request::is('seller/transaction/wallet_transactions*') || Request::is('seller/payment_request*') || Request::is('seller/payment_gateways*');
            $group_subscription_active = Request::is('seller/my_subscription*');
            $group_communication_active = Request::is('seller/chat*');
            $group_locations_active = Request::is('seller/area*');
            $group_languages_active = Request::is('seller/language/bulk_translation_upload*');
            $group_reports_active = Request::is('seller/reports*');
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
             <li class="sidebar-title"><i class='bx bx-tachometer'></i>
                 {{ labels('admin_labels.dashboard', 'Dashboard') }}
             </li>
             <li class="nav-item ">
                 <a class="nav-link {{ Request::is('seller/home') || Request::is('seller/home/*') ? 'active' : '' }}"
                     href="{{ route('seller.home') }}">
                     <span class="nav-link-text ">{{ labels('admin_labels.home', 'Home') }}</span>
                 </a>
             </li>

             {{-- ===================== SALES (Orders / POS / Stock / Deliverability / Returns) ===================== --}}
             <li class="nav-item">
                 <a data-bs-toggle="collapse" href="#group_sales"
                     class="nav-link sidebar-group-toggle {{ $group_sales_active ? '' : 'collapsed' }} {{ $group_sales_active ? 'active' : '' }}"
                     aria-controls="group_sales" role="button" aria-expanded="false">
                     <i class='bx bx-card'></i>
                     <span class="nav-link-text">{{ labels('admin_labels.group_sales', 'Sales') }}</span>
                     <i class="fas fa-angle-down"></i>
                 </a>
                 <div class="collapse {{ $group_sales_active ? 'show' : '' }}" id="group_sales">
                     <ul class="nav">
                         <li class="nav-item ">
                             <a data-bs-toggle="collapse" href="#order_dropdown"
                                 class="nav-link {{ Request::is('seller/orders') || Request::is('seller/orders*') ? 'active' : '' }}  {{ Request::is('seller/orders') || Request::is('seller/orders*') ? '' : 'collapsed' }}"
                                 aria-controls="order_dropdown" role="button" aria-expanded="false">
                                 <span class="nav-link-text ">{{ labels('admin_labels.orders_manage', 'Orders Manage') }}</span><i
                                     class="fas fa-angle-down"></i>
                             </a>
                             <div class="collapse {{ Request::is('seller/orders') || Request::is('seller/orders*') ? 'show' : '' }}"
                                 id="order_dropdown">
                                 <ul class="nav">
                                     <li
                                         class="nav-item {{ Request::is('seller/orders') || Request::is('seller/orders*') ? 'active' : '' }}">
                                         <a class="nav-link " href="{{ route('seller.orders.index') }}">
                                             <span class="nav-link-text ">{{ labels('admin_labels.orders', 'Orders') }}</span>
                                         </a>
                                     </li>
                                 </ul>
                             </div>
                         </li>
                         <li class="nav-item ">
                             <a class="nav-link {{ Request::is('seller/point_of_sale') || Request::is('seller/point_of_sale/*') ? 'active' : '' }}"
                                 href="{{ route('seller.point_of_sale.index') }}">
                                 <span class="nav-link-text ">{{ labels('admin_labels.point_of_sale', 'Point Of Sale') }}</span>
                             </a>
                         </li>
                         <li class="nav-item ">
                             <a class="nav-link {{ Request::is('seller/manage_stock') || Request::is('seller/manage_stock/*') ? 'active' : '' }}"
                                 href="{{ route('seller.manage_stock.index') }}">
                                 <span class="nav-link-text ">{{ labels('admin_labels.stock_manage', 'Stock Manage') }}</span>
                             </a>
                         </li>
                         <li class="nav-item">
                             <a class="nav-link {{ Request::is('seller/manage_combo_stock') || Request::is('seller/manage_combo_stock/*') ? 'active' : '' }}"
                                 href="{{ route('seller.manage_combo_stock.index') }}">
                                 <span
                                     class="nav-link-text ms-1">{{ labels('admin_labels.combo_stock_manage', 'Combo Stock Manage') }}</span>
                             </a>
                         </li>
                         <li class="nav-item ">
                             <a class="nav-link manage_product_deliverability {{ Request::is('seller/manage_product_deliverability') ? 'active' : '' }}"
                                 href="{{ route('seller.manage_product_deliverability.index') }}">
                                 <span
                                     class="nav-link-text ">{{ labels('admin_labels.manage_product_deliverability', 'Product Deliverability Manage') }}</span>
                             </a>
                         </li>
                         <li class="nav-item ">
                             <a class="nav-link manage_product_deliverability {{ Request::is('seller/manage_combo_product_deliverability') ? 'active' : '' }}"
                                 href="{{ route('seller.manage_combo_product_deliverability.index') }}">
                                 <span
                                     class="nav-link-text ">{{ labels('admin_labels.manage_combo_product_deliverability', 'Combo Product Deliverability Manage') }}</span>
                             </a>
                         </li>
                         <li class="sidebar-subtitle ms-3">{{ labels('admin_labels.return_requests', 'Return Requests') }}</li>
                         <li class="nav-item ms-3">
                             <a class="nav-link {{ Request::is('seller/return_request') || Request::is('seller/return_request/*') ? 'active' : '' }}"
                                 href="{{ route('seller.return_request.index') }}">
                                 <span
                                     class="nav-link-text ms-1">{{ labels('admin_labels.return_requests', 'Return Requests') }}</span>
                             </a>
                         </li>
                     </ul>
                 </div>
             </li>

             {{-- ===================== CATALOG (Categories / Brands / Tax / Attributes / Products / Combo Products) ===================== --}}
             <li class="nav-item">
                 <a data-bs-toggle="collapse" href="#group_catalog"
                     class="nav-link sidebar-group-toggle {{ $group_catalog_active ? '' : 'collapsed' }} {{ $group_catalog_active ? 'active' : '' }}"
                     aria-controls="group_catalog" role="button" aria-expanded="false">
                     <i class='bx bx-cart-alt'></i>
                     <span class="nav-link-text">{{ labels('admin_labels.group_catalog', 'Catalog') }}</span>
                     <i class="fas fa-angle-down"></i>
                 </a>
                 <div class="collapse {{ $group_catalog_active ? 'show' : '' }}" id="group_catalog">
                     <ul class="nav">
                         <li class="nav-item ">
                             <a class="nav-link {{ Request::is('seller/categories') || Request::is('seller/categories/*') ? 'active' : '' }}"
                                 href="{{ route('seller_categories.index') }}">
                                 <span class="nav-link-text ">{{ labels('admin_labels.categories', 'Categories') }}</span>
                             </a>
                         </li>
                         <li class="nav-item ">
                             <a class="nav-link {{ Request::is('seller/brands') || Request::is('seller/brands/*') ? 'active' : '' }}"
                                 href="{{ route('seller_brands.index') }}">
                                 <span class="nav-link-text ">{{ labels('admin_labels.brands', 'Brands') }}</span>
                             </a>
                         </li>

                         <li class="sidebar-subtitle ms-3">{{ labels('admin_labels.products', 'Products') }}</li>
                         <li class="nav-item ">
                             <a class="nav-link {{ Request::is('seller/tax') || Request::is('seller/tax/*') ? 'active' : '' }}"
                                 href="{{ route('tax.index') }}">
                                 <span class="nav-link-text ">{{ labels('admin_labels.tax', 'Tax') }}</span>
                             </a>
                         </li>
                         <li class="nav-item ">
                             <a class="nav-link {{ Request::is('seller/products/attributes') || Request::is('seller/products/attributes') ? 'active' : '' }}"
                                 href="{{ route('attributes.index') }}">
                                 <span class="sidenav-normal"> {{ labels('admin_labels.attributes', 'Attributes') }} </span>
                             </a>
                         </li>

                        <li class="nav-item ">
                            <a data-bs-toggle="collapse" href="#products_dropdown"
                                class="nav-link {{ Request::is('seller/products') || Request::is('seller/products/manage_product') || Request::is('seller/product_faqs') || Request::is('seller/product/product_bulk_upload') ? 'active' : '' }} {{ Request::is('seller/products') || Request::is('seller/products/*') || Request::is('seller/product_faqs') ? '' : 'collapsed' }}"
                                aria-controls="products_dropdown" role="button" aria-expanded="false">
                                <span
                                    class="nav-link-text ">{{ labels('admin_labels.products_manage', 'Products Manage') }}</span><i
                                    class="fas fa-angle-down"></i>
                            </a>
                            <div class="collapse {{ Request::is('seller/products') || Request::is('seller/products/*') || Request::is('seller/product_faqs') ? 'show' : '' }}"
                                id="products_dropdown">
                                <ul class="nav ">
                                    <li class="nav-item {{ Request::is('seller/products') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('seller.products.index') }}">
                                            <span class="nav-link-text">{{ labels('admin_labels.add_products', 'Add Products') }}
                                            </span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('seller/products/manage_product') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('seller.products.manage_product') }}">
                                            <span
                                                class="nav-link-text">{{ labels('admin_labels.manage_products', 'Manage Products') }}
                                            </span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ Request::is('seller/product_faqs') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('seller.product_faqs.index') }}">
                                            <span
                                                class="nav-link-text">{{ labels('admin_labels.product_faqs', 'Product FAQs') }}</span>
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link {{ Request::is('seller/product/product_bulk_upload') || Request::is('seller/product/product_bulk_upload') ? 'active' : '' }}"
                                            href="{{ route('seller.product_bulk_upload') }}">
                                            <span class="nav-link-text">{{ labels('admin_labels.bulk_upload', 'Bulk Upload') }}
                                            </span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li class="sidebar-subtitle ms-3">{{ labels('admin_labels.combo_products_manage', 'Combo Products Manage') }}</li>
                        <li class="nav-item ">
                            <a class="nav-link {{ Request::is('seller/combo_product_attributes') ? 'active' : '' }}"
                                href="{{ route('seller.combo_product_attributes.index') }}">
                                <span class="sidenav-normal"> {{ labels('admin_labels.attributes', 'Attributes') }} </span>
                            </a>
                        </li>
                        <li class="nav-item ">
                            <a class="nav-link {{ Request::is('seller/combo_products') ? 'active' : '' }}"
                                href="{{ route('seller.combo_products.index') }}">
                                <span class="sidenav-normal">
                                    {{ labels('admin_labels.add_combo_products', 'Add Combo Products') }} </span>
                            </a>
                        </li>
                        <li class="nav-item ">
                            <a class="nav-link {{ Request::is('seller/combo_products/manage_product') ? 'active' : '' }}"
                                href="{{ route('seller.combo_products.manage_product') }}">
                                <span class="sidenav-normal"> {{ labels('admin_labels.manage_products', 'Manage Products') }}
                                </span>
                            </a>
                        </li>
                        <li class="nav-item ">
                            <a class="nav-link {{ Request::is('seller/combo_product_faqs') || Request::is('seller/combo_product_faqs') ? 'active' : '' }}"
                                href="{{ route('seller.combo_product_faqs.index') }}">
                                <span class="sidenav-normal">{{ labels('admin_labels.product_faqs', 'Product FAQs') }} </span>
                            </a>
                        </li>
                     </ul>
                 </div>
             </li>

             {{-- ===================== WEBSITE (Media - theme/domain system doesn't exist yet) ===================== --}}
             <li class="nav-item">
                 <a data-bs-toggle="collapse" href="#group_website"
                     class="nav-link sidebar-group-toggle {{ $group_website_active ? '' : 'collapsed' }} {{ $group_website_active ? 'active' : '' }}"
                     aria-controls="group_website" role="button" aria-expanded="false">
                     <i class='bx bx-image-add'></i>
                     <span class="nav-link-text">{{ labels('admin_labels.group_website', 'Website') }}</span>
                     <i class="fas fa-angle-down"></i>
                 </a>
                 <div class="collapse {{ $group_website_active ? 'show' : '' }}" id="group_website">
                     <ul class="nav">
                         <li class="nav-item ">
                             <a class="nav-link {{ Request::is('seller/media') || Request::is('seller/media/*') ? 'active' : '' }}"
                                 href="{{ route('seller.media') }}">
                                 <span class="nav-link-text ">{{ labels('admin_labels.add_media', 'Add Media') }}</span>
                             </a>
                         </li>
                     </ul>
                 </div>
             </li>

             {{-- ===================== MARKETING (Affiliate Program) ===================== --}}
             <li class="nav-item">
                 <a data-bs-toggle="collapse" href="#group_marketing"
                     class="nav-link sidebar-group-toggle {{ $group_marketing_active ? '' : 'collapsed' }} {{ $group_marketing_active ? 'active' : '' }}"
                     aria-controls="group_marketing" role="button" aria-expanded="false">
                     <i class='bx bx-link-alt'></i>
                     <span class="nav-link-text">{{ labels('admin_labels.group_marketing', 'Marketing') }}</span>
                     <i class="fas fa-angle-down"></i>
                 </a>
                 <div class="collapse {{ $group_marketing_active ? 'show' : '' }}" id="group_marketing">
                     <ul class="nav">
                         <li class="nav-item ">
                             <a class="nav-link {{ Request::is('seller/affiliate_program') ? 'active' : '' }}"
                                 href="{{ route('seller.affiliate_program.index') }}">
                                 <span class="nav-link-text ">{{ labels('admin_labels.affiliate_program', 'Affiliate Program') }}</span>
                             </a>
                         </li>
                     </ul>
                 </div>
             </li>

             {{-- ===================== FINANCE (Wallet / Withdrawals / Payment Gateways) ===================== --}}
             <li class="nav-item">
                 <a data-bs-toggle="collapse" href="#group_finance"
                     class="nav-link sidebar-group-toggle {{ $group_finance_active ? '' : 'collapsed' }} {{ $group_finance_active ? 'active' : '' }}"
                     aria-controls="group_finance" role="button" aria-expanded="false">
                     <i class='bx bx-wallet-alt'></i>
                     <span class="nav-link-text">{{ labels('admin_labels.group_finance', 'Finance') }}</span>
                     <i class="fas fa-angle-down"></i>
                 </a>
                 <div class="collapse {{ $group_finance_active ? 'show' : '' }}" id="group_finance">
                     <ul class="nav">
                         <li class="nav-item ">
                             <a class="nav-link {{ Request::is('seller/transaction/wallet_transactions') ? 'active' : '' }}"
                                 href="{{ route('seller.transaction.wallet_transactions') }}">
                                 <span
                                     class="nav-link-text ">{{ labels('admin_labels.wallet_transaction', 'Wallet Transaction') }}</span>
                             </a>
                         </li>
                         <li class="nav-item ">
                             <a class="nav-link {{ Request::is('seller/payment_request/withdrawal_requests') ? 'active' : '' }}"
                                 href="{{ route('seller.payment_request.withdrawal_requests') }}">
                                 <span class="nav-link-text ">
                                     {{ labels('admin_labels.withdrawal_requests', 'Withdrawal Requests') }}</span>
                             </a>
                         </li>
                         <li class="nav-item ">
                             <a class="nav-link {{ Request::is('seller/payment_gateways') ? 'active' : '' }}"
                                 href="{{ route('seller.payment_gateways.index') }}">
                                 <span class="nav-link-text ">{{ labels('admin_labels.payment_gateways', 'Payment Gateways') }}</span>
                             </a>
                         </li>
                     </ul>
                 </div>
             </li>

             {{-- ===================== SUBSCRIPTION ===================== --}}
             <li class="nav-item">
                 <a data-bs-toggle="collapse" href="#group_subscription"
                     class="nav-link sidebar-group-toggle {{ $group_subscription_active ? '' : 'collapsed' }} {{ $group_subscription_active ? 'active' : '' }}"
                     aria-controls="group_subscription" role="button" aria-expanded="false">
                     <i class='bx bx-crown'></i>
                     <span class="nav-link-text">{{ labels('admin_labels.my_subscription', 'My Subscription') }}</span>
                     <i class="fas fa-angle-down"></i>
                 </a>
                 <div class="collapse {{ $group_subscription_active ? 'show' : '' }}" id="group_subscription">
                     <ul class="nav">
                         <li class="nav-item ">
                             <a class="nav-link {{ Request::is('seller/my_subscription') ? 'active' : '' }}"
                                 href="{{ route('seller.my_subscription.index') }}">
                                 <span class="nav-link-text ">{{ labels('admin_labels.my_subscription', 'My Subscription') }}</span>
                             </a>
                         </li>
                     </ul>
                 </div>
             </li>

             {{-- ===================== COMMUNICATION (Chat) ===================== --}}
             <li class="nav-item">
                 <a data-bs-toggle="collapse" href="#group_communication"
                     class="nav-link sidebar-group-toggle {{ $group_communication_active ? '' : 'collapsed' }} {{ $group_communication_active ? 'active' : '' }}"
                     aria-controls="group_communication" role="button" aria-expanded="false">
                     <i class='bx bx-chat'></i>
                     <span class="nav-link-text">{{ labels('admin_labels.group_communication', 'Communication') }}</span>
                     <i class="fas fa-angle-down"></i>
                 </a>
                 <div class="collapse {{ $group_communication_active ? 'show' : '' }}" id="group_communication">
                     <ul class="nav">
                         <li class="nav-item ">
                             <a class="nav-link {{ Request::is('seller/chat') || Request::is('seller/chat/*') ? 'active' : '' }}"
                                 href="{{ route('seller.chat.index') }}">
                                 <span class="nav-link-text ">{{ labels('admin_labels.chats', 'Chats') }}</span>
                                 @if ($unread > 0)
                                     <span
                                         class="flex-shrink-0 badge badge-center bg-danger w-px-20 h-px-20  rounded-pill">{{ $unread }}</span>
                                 @endif
                             </a>
                         </li>
                     </ul>
                 </div>
             </li>

             {{-- ===================== LOCATIONS ===================== --}}
             <li class="nav-item">
                 <a data-bs-toggle="collapse" href="#group_locations"
                     class="nav-link sidebar-group-toggle {{ $group_locations_active ? '' : 'collapsed' }} {{ $group_locations_active ? 'active' : '' }}"
                     aria-controls="group_locations" role="button" aria-expanded="false">
                     <i class='bx bx-map'></i>
                     <span class="nav-link-text">{{ labels('admin_labels.location_management', 'Locations') }}</span>
                     <i class="fas fa-angle-down"></i>
                 </a>
                 <div class="collapse {{ $group_locations_active ? 'show' : '' }}" id="group_locations">
                     <ul class="nav">
                         <li class="nav-item {{ Request::is('seller/area/zipcodes') ? 'active' : '' }}">
                             <a class="nav-link" href="{{ route('seller.zipcodes') }}">
                                 <span class="nav-link-text">{{ labels('admin_labels.zipcodes', 'Zipcodes') }}</span>
                             </a>
                         </li>
                         <li class="nav-item {{ Request::is('seller/area/city') ? 'active' : '' }}">
                             <a class="nav-link" href="{{ route('seller.city') }}">
                                 <span class="nav-link-text">{{ labels('admin_labels.city', 'City') }}</span>
                             </a>
                         </li>
                         <li class="nav-item {{ Request::is('seller/area/zones') ? 'active' : '' }}">
                             <a class="nav-link" href="{{ route('seller.zones') }}">
                                 <span class="nav-link-text">{{ labels('admin_labels.zones', 'Zones') }}</span>
                             </a>
                         </li>
                     </ul>
                 </div>
             </li>
             {{-- <li class="nav-item ">
                 <a class="nav-link {{ Request::is('seller/pickup_locations') || Request::is('seller/pickup_locations/*') ? 'active' : '' }}"
                     href="{{ route('pickup_locations.index') }}">
                     <span class="nav-link-text ">{{ labels('admin_labels.pickup_locations', 'Pickup Locations')
                         }}</span>
                 </a>
             </li> --}}

             {{-- ===================== LANGUAGES ===================== --}}
             <li class="nav-item">
                 <a data-bs-toggle="collapse" href="#group_languages"
                     class="nav-link sidebar-group-toggle {{ $group_languages_active ? '' : 'collapsed' }} {{ $group_languages_active ? 'active' : '' }}"
                     aria-controls="group_languages" role="button" aria-expanded="false">
                     <i class='bx bx-text'></i>
                     <span class="nav-link-text">{{ labels('admin_labels.language_settings', 'Languages') }}</span>
                     <i class="fas fa-angle-down"></i>
                 </a>
                 <div class="collapse {{ $group_languages_active ? 'show' : '' }}" id="group_languages">
                     <ul class="nav">
                         <li class="nav-item ms-3">
                             <a class="nav-link {{ Request::is('seller/language/bulk_translation_upload') || Request::is('seller/language/bulk_translation_upload/*') ? 'active' : '' }}"
                                 href="{{ route('seller.translation_bulk_upload.index') }}">
                                 {!! labels('admin_labels.bulk_upload', 'Multi Language Bulk<br>Import') !!}
                             </a>
                         </li>
                     </ul>
                 </div>
             </li>

             {{-- ===================== REPORTS ===================== --}}
             <li class="nav-item">
                 <a data-bs-toggle="collapse" href="#group_reports"
                     class="nav-link sidebar-group-toggle {{ $group_reports_active ? '' : 'collapsed' }} {{ $group_reports_active ? 'active' : '' }}"
                     aria-controls="group_reports" role="button" aria-expanded="false">
                     <i class='bx bx-bar-chart-alt-2'></i>
                     <span class="nav-link-text">{{ labels('admin_labels.reports_and_sales_management', 'Reports') }}</span>
                     <i class="fas fa-angle-down"></i>
                 </a>
                 <div class="collapse {{ $group_reports_active ? 'show' : '' }}" id="group_reports">
                     <ul class="nav">
                         <li class="nav-item ">
                             <a class="nav-link {{ Request::is('seller/reports/sales_report') ? 'active' : '' }}"
                                 href="{{ route('seller.reports.sales_report') }}">
                                 <span class="nav-link-text ">{{ labels('admin_labels.sales_report', 'Sales Report') }}</span>
                             </a>
                         </li>
                     </ul>
                 </div>
             </li>
        </ul>
    </div>
</nav>
