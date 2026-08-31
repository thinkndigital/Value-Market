<?php

/**
 * Unified Dynamic Sidebar Engine — navigation source of truth (32-phase SaaS brief, Phase 3).
 *
 * One nav tree per role. App\Services\Sidebar\SidebarService walks it, drops any node the current
 * user isn't allowed to see (permission/subscription/feature checks), computes active/expanded
 * state, and hands the pruned tree to resources/views/components/sidebar/tree.blade.php - the one
 * recursive renderer every role's side-bar.blade.php now includes instead of hand-writing <li> markup.
 *
 * This is a migration of the navigation that already existed per role (see docs/ADMIN_SIDEBAR_REGROUP.md
 * for the group structure it was ported from) into config-driven form - no route, permission, or label
 * was invented or dropped in the port. Per the "Supplier vs Wholesaler" naming decision, the Wholesaler
 * module keeps its wholesaler_* routes/tables/permissions in code; only the label text shown here reads
 * "Supplier" - see docs/SIDEBAR_ENGINE.md.
 *
 * Node shape:
 *   'key'              unique string within its parent (used as the Bootstrap collapse id)
 *   'label_key'        translation key passed to the labels() helper
 *   'label_fallback'   English fallback text passed to labels()
 *   'icon'             boxicons class, rendered on top-level items/groups only
 *   'route'            a named route this item links to (omit on a pure group)
 *   'match'            Request::is() glob pattern(s) used for the active/expanded state - ported
 *                      as-is from the pre-engine Blade sidebars
 *   'permission'       Spatie permission name, or null = always visible once the parent is
 *   'super_admin_only' true hides the node from admin/editor (kept from the old $user_role checks)
 *   'badge'            'unread_messages' is the only dynamic badge source today
 *   'children'         nested nodes; a group with no visible children after resolution is dropped
 */

return [

    'admin' => [
        [
            'key' => 'dashboard', 'label_key' => 'admin_labels.dashboard', 'label_fallback' => 'Dashboard',
            'icon' => 'bx bx-tachometer', 'route' => 'admin.home', 'match' => ['admin/home', 'admin/home/*'],
        ],
        [
            'key' => 'group_platform', 'label_key' => 'admin_labels.group_platform', 'label_fallback' => 'Platform',
            'icon' => 'bx bx-store-alt',
            'match' => ['admin/store*', 'admin/stores*', 'admin/seller*', 'admin/sellers*', 'admin/customers*', 'admin/delivery_boys*', 'admin/wholesalers*'],
            'children' => [
                ['key' => 'stores_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.stores', 'label_fallback' => 'Stores'],
                ['key' => 'store_add', 'label_key' => 'admin_labels.add_store', 'label_fallback' => 'Add Store', 'route' => 'admin.stores.index', 'match' => ['admin/store']],
                ['key' => 'store_manage', 'label_key' => 'admin_labels.manage_stores', 'label_fallback' => 'Manage Stores', 'route' => 'admin.stores.manage_store', 'match' => ['admin/stores/manage_store', 'admin/stores/manage_store*'], 'permission' => 'view store'],
                ['key' => 'store_custom_fields', 'label_key' => 'admin_labels.custom_fields', 'label_fallback' => 'Custom Fields', 'route' => 'admin.custom_fields.index', 'match' => ['admin/store/custom_fields']],

                ['key' => 'sellers_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.sellers', 'label_fallback' => 'Sellers'],
                ['key' => 'seller_add', 'label_key' => 'admin_labels.add_sellers', 'label_fallback' => 'Add Sellers', 'route' => 'admin.sellers.create', 'match' => ['admin/seller/*', 'admin/seller/create']],
                ['key' => 'seller_manage', 'label_key' => 'admin_labels.sellers', 'label_fallback' => 'Sellers', 'route' => 'sellers.index', 'match' => ['admin/sellers'], 'permission' => 'view seller'],

                ['key' => 'suppliers_title', 'is_subtitle' => true, 'label_key' => 'wholesaler_labels.wholesalers', 'label_fallback' => 'Suppliers'],
                ['key' => 'supplier_manage', 'label_key' => 'wholesaler_labels.wholesalers', 'label_fallback' => 'Suppliers', 'route' => 'admin.wholesalers.index', 'match' => ['admin/wholesalers']],
                ['key' => 'supplier_queue', 'label_key' => 'wholesaler_labels.products_approval_queue', 'label_fallback' => 'Products Approval Queue', 'route' => 'admin.wholesalers.products_queue', 'match' => ['admin/wholesalers/products_queue']],

                ['key' => 'customers_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.customers', 'label_fallback' => 'Customers'],
                ['key' => 'customers_view', 'label_key' => 'admin_labels.view_customers', 'label_fallback' => 'View Customers', 'route' => 'admin.customers', 'match' => ['admin/customers'], 'permission' => 'view customers'],
                ['key' => 'customers_addresses', 'label_key' => 'admin_labels.addresses', 'label_fallback' => 'Addresses', 'route' => 'admin.customers.getCustomersAddresses', 'match' => ['admin/customers/customers_addresses'], 'permission' => 'view address'],
                ['key' => 'customers_transactions', 'label_key' => 'admin_labels.transactions', 'label_fallback' => 'Transactions', 'route' => 'admin.customers.viewTransactions', 'match' => ['admin/customers/view_transactions'], 'permission' => 'view customer_transaction'],
                ['key' => 'customers_wallet', 'label_key' => 'admin_labels.wallet_transactions', 'label_fallback' => 'Wallet Transactions', 'route' => 'admin.customers.walletTransaction', 'match' => ['admin/customers/wallet_transaction'], 'permission' => 'view customer_wallet_transaction'],

                ['key' => 'delivery_boys_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.delivery_boys', 'label_fallback' => 'Delivery Boys'],
                ['key' => 'delivery_boys_manage', 'label_key' => 'admin_labels.delivery_boys', 'label_fallback' => 'Delivery Boys', 'route' => 'delivery_boys.index', 'match' => ['admin/delivery_boys']],
                ['key' => 'delivery_boys_cash', 'label_key' => 'admin_labels.cash_collection', 'label_fallback' => 'Cash Collection', 'route' => 'admin.get_cash_collection.index', 'match' => ['admin/delivery_boys/manage_cash', 'admin/delivery_boys/manage_cash*'], 'permission' => 'view delivery_boy_cash_collection'],
                ['key' => 'delivery_boys_fund', 'label_key' => 'admin_labels.fund_transfer', 'label_fallback' => 'Fund Transfer', 'route' => 'admin.delivery_boys.fund_transfers.index', 'match' => ['admin/delivery_boys/fund_transfers', 'admin/delivery_boys/fund_transfers*'], 'permission' => 'view fund_transfer'],
            ],
        ],
        [
            'key' => 'group_catalog', 'label_key' => 'admin_labels.group_catalog', 'label_fallback' => 'Catalog',
            'icon' => 'bx bx-cart-alt',
            'match' => ['admin/categories*', 'admin/brands*', 'admin/taxes*', 'admin/attributes*', 'admin/products*', 'admin/product_faqs*', 'admin/product/*', 'admin/combo_product*'],
            'children' => [
                [
                    'key' => 'categories_dropdown', 'label_key' => 'admin_labels.categories', 'label_fallback' => 'Categories',
                    'match' => ['admin/categories', 'admin/categories/*'],
                    'children' => [
                        ['key' => 'categories_index', 'label_key' => 'admin_labels.categories', 'label_fallback' => 'Categories', 'route' => 'categories.index', 'match' => ['admin/categories']],
                        ['key' => 'categories_order', 'label_key' => 'admin_labels.categories_order', 'label_fallback' => 'Categories Order', 'route' => 'category_order.index', 'match' => ['admin/categories/category_order', 'admin/categories/category_order/*'], 'permission' => 'view category_order'],
                        ['key' => 'categories_slider', 'label_key' => 'admin_labels.categories_sliders', 'label_fallback' => 'Categories Sliders', 'route' => 'category_slider.index', 'match' => ['admin/categories/category_slider', 'admin/categories/category_slider/*']],
                        ['key' => 'categories_bulk', 'label_key' => 'admin_labels.bulk_upload', 'label_fallback' => 'Bulk Upload', 'route' => 'categories.bulk_upload', 'match' => ['admin/categories/bulk_upload', 'admin/categories/bulk_upload/*']],
                    ],
                ],
                ['key' => 'brand_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.brand', 'label_fallback' => 'Brand'],
                ['key' => 'brand_index', 'label_key' => 'admin_labels.brand', 'label_fallback' => 'Brand', 'route' => 'brands.index', 'match' => ['admin/brands']],
                ['key' => 'brand_bulk', 'label_key' => 'admin_labels.bulk_upload', 'label_fallback' => 'Bulk Upload', 'route' => 'brands.bulk_upload', 'match' => ['admin/brands/bulk_upload', 'admin/brands/bulk_upload/*']],

                ['key' => 'products_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.products', 'label_fallback' => 'Products'],
                ['key' => 'tax_index', 'label_key' => 'admin_labels.tax', 'label_fallback' => 'Tax', 'route' => 'taxes.index', 'match' => ['admin/taxes', 'admin/taxes/*'], 'permission' => 'view tax'],
                ['key' => 'attributes_index', 'label_key' => 'admin_labels.attributes_manage', 'label_fallback' => 'Attributes Manage', 'route' => 'admin.attributes.index', 'match' => ['admin/attributes', 'admin/attributes*']],
                [
                    'key' => 'products_dropdown', 'label_key' => 'admin_labels.products_manage', 'label_fallback' => 'Products Manage',
                    'match' => ['admin/products', 'admin/products/*', 'admin/product_faqs'],
                    'children' => [
                        ['key' => 'products_add', 'label_key' => 'admin_labels.add_products', 'label_fallback' => 'Add Products', 'route' => 'admin.products.index', 'match' => ['admin/products']],
                        ['key' => 'products_manage', 'label_key' => 'admin_labels.manage_products', 'label_fallback' => 'Manage Products', 'route' => 'admin.products.manage_product', 'match' => ['admin/products/manage_product'], 'permission' => 'view product'],
                        ['key' => 'products_faqs', 'label_key' => 'admin_labels.product_faqs', 'label_fallback' => 'Product FAQs', 'route' => 'admin.product_faqs.index', 'match' => ['admin/product_faqs']],
                        ['key' => 'products_bulk', 'label_key' => 'admin_labels.bulk_upload', 'label_fallback' => 'Bulk Upload', 'route' => 'admin.product_bulk_upload', 'match' => ['admin/product/product_bulk_upload']],
                    ],
                ],

                ['key' => 'combo_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.combo_products_manage', 'label_fallback' => 'Combo Products Manage'],
                ['key' => 'combo_attributes', 'label_key' => 'admin_labels.attributes_manage', 'label_fallback' => 'Attributes Manage', 'route' => 'admin.combo_product_attributes.index', 'match' => ['admin/combo_product_attributes', 'admin/combo_product_attributes/*']],
                [
                    'key' => 'combo_dropdown', 'label_key' => 'admin_labels.products_manage', 'label_fallback' => 'Products Manage',
                    'match' => ['admin/combo_products', 'admin/combo_products*', 'admin/combo_product_faqs'],
                    'children' => [
                        ['key' => 'combo_add', 'label_key' => 'admin_labels.add_combo_products', 'label_fallback' => 'Add Products', 'route' => 'admin.combo_products.index', 'match' => ['admin/combo_products']],
                        ['key' => 'combo_manage', 'label_key' => 'admin_labels.manage_products', 'label_fallback' => 'Manage Products', 'route' => 'admin.combo_products.manage_product', 'match' => ['admin/combo_products/manage_product'], 'permission' => 'view combo_product'],
                        ['key' => 'combo_faqs', 'label_key' => 'admin_labels.product_faqs', 'label_fallback' => 'Product FAQs', 'route' => 'admin.combo_product_faqs.index', 'match' => ['admin/combo_product_faqs']],
                    ],
                ],
            ],
        ],
        [
            'key' => 'group_orders', 'label_key' => 'admin_labels.group_orders', 'label_fallback' => 'Orders & Operations',
            'icon' => 'bx bx-card',
            'match' => ['admin/orders*', 'admin/order_items*', 'admin/manage_stock*', 'admin/manage_combo_stock*', 'admin/return_request*'],
            'children' => [
                [
                    'key' => 'orders_dropdown', 'label_key' => 'admin_labels.orders_manage', 'label_fallback' => 'Orders Manage',
                    'match' => ['admin/orders', 'admin/orders*', 'admin/order_items'], 'permission' => 'view orders',
                    'children' => [
                        ['key' => 'orders_index', 'label_key' => 'admin_labels.orders', 'label_fallback' => 'Orders', 'route' => 'admin.orders.index', 'match' => ['admin/orders']],
                        ['key' => 'orders_items', 'label_key' => 'admin_labels.order_items', 'label_fallback' => 'Order Items', 'route' => 'admin.order_items.index', 'match' => ['admin/order_items', 'admin/order_items*']],
                        ['key' => 'orders_tracking', 'label_key' => 'admin_labels.order_tracking', 'label_fallback' => 'Order Tracking', 'route' => 'admin.orders.order_tracking', 'match' => ['admin/orders/order_tracking']],
                    ],
                ],
                ['key' => 'stock_manage', 'label_key' => 'admin_labels.stock_manage', 'label_fallback' => 'Stock Manage', 'route' => 'admin.manage_stock.index', 'match' => ['admin/manage_stock', 'admin/manage_stock/*'], 'permission' => 'view stock'],
                ['key' => 'combo_stock_manage', 'label_key' => 'admin_labels.combo_stock_manage', 'label_fallback' => 'Combo Stock Manage', 'route' => 'admin.manage_combo_stock.index', 'match' => ['admin/manage_combo_stock', 'admin/manage_combo_stock/*'], 'permission' => 'view combo_stock'],
                ['key' => 'returns_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.return_requests', 'label_fallback' => 'Return Requests', 'permission' => 'view return_request'],
                ['key' => 'returns_index', 'label_key' => 'admin_labels.return_requests', 'label_fallback' => 'Return Requests', 'route' => 'admin.return_request.index', 'match' => ['admin/return_request', 'admin/return_request/*'], 'permission' => 'view return_request'],
            ],
        ],
        [
            'key' => 'group_finance', 'label_key' => 'admin_labels.group_finance', 'label_fallback' => 'Finance',
            'icon' => 'bx bx-wallet-alt',
            'match' => ['admin/payment_request*', 'admin/sellers/seller_wallet_transaction*'],
            'children' => [
                ['key' => 'seller_wallet', 'label_key' => 'admin_labels.wallet_transactions', 'label_fallback' => 'Seller Wallet Transaction', 'route' => 'admin.sellers.sellerWallet', 'match' => ['admin/sellers/seller_wallet_transaction', 'admin/sellers/seller_wallet_transaction/*'], 'permission' => 'view seller_wallet_transaction'],
                ['key' => 'payment_request', 'label_key' => 'admin_labels.payment_request', 'label_fallback' => 'Payment Request', 'route' => 'admin.payment_request.index', 'match' => ['admin/payment_request', 'admin/payment_request/*'], 'permission' => 'view payment_request'],
            ],
        ],
        [
            'key' => 'group_marketing', 'label_key' => 'admin_labels.group_marketing', 'label_fallback' => 'Marketing',
            'icon' => 'bx bx-gift',
            'match' => ['admin/offers*', 'admin/offer_sliders*', 'admin/promo_codes*', 'admin/sliders*', 'admin/feature_section*', 'admin/commission_rules*', 'admin/affiliate*', 'admin/blogs*', 'admin/manage_blogs*'],
            'children' => [
                ['key' => 'offers_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.offers', 'label_fallback' => 'Offers'],
                ['key' => 'offers_index', 'label_key' => 'admin_labels.offers', 'label_fallback' => 'Offers', 'route' => 'offers.index', 'match' => ['admin/offers']],
                ['key' => 'offer_sliders', 'label_key' => 'admin_labels.offer_sliders', 'label_fallback' => 'Offer Sliders', 'route' => 'offer_sliders.index', 'match' => ['admin/offer_sliders', 'admin/offer_sliders/*']],
                ['key' => 'promo_codes', 'label_key' => 'admin_labels.promo_codes', 'label_fallback' => 'Promo Codes', 'route' => 'promo_codes.index', 'match' => ['admin/promo_codes', 'admin/promo_codes/*']],

                ['key' => 'slider_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.slider', 'label_fallback' => 'Slider'],
                ['key' => 'slider_index', 'label_key' => 'admin_labels.add_slider', 'label_fallback' => 'Add Slider', 'route' => 'sliders.index', 'match' => ['admin/sliders', 'admin/sliders/*']],

                ['key' => 'featured_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.featured_section', 'label_fallback' => 'Featured Section'],
                ['key' => 'featured_index', 'label_key' => 'admin_labels.featured', 'label_fallback' => 'Featured', 'route' => 'feature_section.index', 'match' => ['admin/feature_section']],
                ['key' => 'featured_order', 'label_key' => 'admin_labels.sections_order', 'label_fallback' => 'Sections Order', 'route' => 'feature_section.section_order', 'match' => ['admin/feature_section/section_order']],

                ['key' => 'affiliate_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.affiliate', 'label_fallback' => 'Affiliate', 'super_admin_only' => true],
                ['key' => 'commission_rules', 'label_key' => 'admin_labels.commission_rules', 'label_fallback' => 'Commission Rules', 'route' => 'admin.commission_rules.index', 'match' => ['admin/commission_rules/manage'], 'super_admin_only' => true],
                ['key' => 'affiliate_links', 'label_key' => 'admin_labels.affiliate_links', 'label_fallback' => 'Affiliate Links', 'route' => 'admin.affiliate.links.index', 'match' => ['admin/affiliate/links'], 'super_admin_only' => true],
                ['key' => 'creator_marketplace', 'label_key' => 'admin_labels.creator_marketplace', 'label_fallback' => 'Creator Marketplace', 'route' => 'admin.creator.marketplace.index', 'match' => ['admin/creator/marketplace*'], 'super_admin_only' => true, 'feature_flag' => 'creator_marketplace'],

                ['key' => 'blogs_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.blogs', 'label_fallback' => 'Blogs'],
                ['key' => 'blogs_categories', 'label_key' => 'admin_labels.blog_categories', 'label_fallback' => 'Blog Categories', 'route' => 'admin.blogs.index', 'match' => ['admin/blogs']],
                ['key' => 'blogs_create', 'label_key' => 'admin_labels.create_blog', 'label_fallback' => 'Create Blog', 'route' => 'manage_blogs.index', 'match' => ['admin/manage_blogs', 'admin/manage_blogs/*']],
            ],
        ],
        [
            'key' => 'group_subscriptions', 'label_key' => 'admin_labels.subscription_plans', 'label_fallback' => 'Subscriptions',
            'icon' => 'bx bx-crown', 'match' => ['admin/subscription_plans*'], 'super_admin_only' => true,
            'children' => [
                ['key' => 'subscription_plans', 'label_key' => 'admin_labels.subscription_plans', 'label_fallback' => 'Subscription Plans', 'route' => 'admin.subscription_plans.index', 'match' => ['admin/subscription_plans/manage']],
            ],
        ],
        [
            'key' => 'group_website', 'label_key' => 'admin_labels.group_website', 'label_fallback' => 'Themes & Website',
            'icon' => 'bx bx-image-add', 'match' => ['admin/media*', 'admin/storage_type*', 'admin/web_settings/theme*'],
            'children' => [
                ['key' => 'media_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.media_manage', 'label_fallback' => 'Media Manage'],
                ['key' => 'media_add', 'label_key' => 'admin_labels.add_media', 'label_fallback' => 'Add Media', 'route' => 'admin.media', 'match' => ['admin/media', 'admin/media/*']],
                ['key' => 'storage_type', 'label_key' => 'admin_labels.storage_type', 'label_fallback' => 'Storage Type', 'route' => 'admin.storage_type', 'match' => ['admin/storage_type', 'admin/storage_type/*']],
            ],
        ],
        [
            'key' => 'group_communication', 'label_key' => 'admin_labels.group_communication', 'label_fallback' => 'Communication',
            'icon' => 'bx bx-support',
            'match' => ['admin/tickets*', 'admin/chat*', 'admin/send_notification*', 'admin/send_seller_notification*', 'admin/seller_email_notification*', 'admin/custom_message*', 'admin/faq*'],
            'children' => [
                ['key' => 'tickets_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.support_tickets', 'label_fallback' => 'Support Tickets'],
                ['key' => 'ticket_types', 'label_key' => 'admin_labels.ticket_types', 'label_fallback' => 'Ticket Types', 'route' => 'ticket_types.index', 'match' => ['admin/tickets/ticket_types', 'admin/tickets/ticket_types*']],
                ['key' => 'tickets_view', 'label_key' => 'admin_labels.tickets', 'label_fallback' => 'Tickets', 'route' => 'admin.tickets.viewTickets', 'match' => ['admin/tickets'], 'permission' => 'view tickets'],

                ['key' => 'chat_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.chat_manage', 'label_fallback' => 'Chat Manage'],
                ['key' => 'chat_index', 'label_key' => 'admin_labels.chats', 'label_fallback' => 'Chats', 'route' => 'admin.chat.index', 'match' => ['admin/chat', 'admin/chat*'], 'badge' => 'unread_messages'],

                ['key' => 'notify_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.send_notification', 'label_fallback' => 'Send Notification'],
                ['key' => 'notify_customer', 'label_key' => 'admin_labels.notification', 'label_fallback' => 'Notification', 'route' => 'notifications.index', 'match' => ['admin/send_notification', 'admin/send_notification/*']],
                ['key' => 'notify_seller', 'label_key' => 'admin_labels.seller_notification', 'label_fallback' => 'Seller Notification', 'route' => 'seller_notifications.index', 'match' => ['admin/send_seller_notification', 'admin/send_seller_notification/*']],
                ['key' => 'notify_seller_email', 'label_key' => 'admin_labels.seller_email_notification', 'label_fallback' => 'Seller Email Notification', 'route' => 'seller_email_notifications.index', 'match' => ['admin/seller_email_notification', 'admin/seller_email_notification/*']],

                ['key' => 'custom_message_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.custom_message', 'label_fallback' => 'Custom Message'],
                ['key' => 'custom_message_add', 'label_key' => 'admin_labels.add_custom_message', 'label_fallback' => 'Add Custom Message', 'route' => 'admin.custom_message.index', 'match' => ['admin/custom_message', 'admin/custom_message/*']],

                ['key' => 'faqs_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.faqs', 'label_fallback' => 'FAQs'],
                ['key' => 'faqs_index', 'label_key' => 'admin_labels.faqs', 'label_fallback' => 'FAQs', 'route' => 'faqs.index', 'match' => ['admin/faq', 'admin/faq/*']],
            ],
        ],
        [
            'key' => 'group_locations', 'label_key' => 'admin_labels.location_management', 'label_fallback' => 'Locations',
            'icon' => 'bx bx-map', 'match' => ['admin/area*', 'admin/zones*'],
            'children' => [
                ['key' => 'zipcodes', 'label_key' => 'admin_labels.zipcodes', 'label_fallback' => 'Zipcodes', 'route' => 'admin.display_zipcodes', 'match' => ['admin/area/zipcodes']],
                ['key' => 'city', 'label_key' => 'admin_labels.city', 'label_fallback' => 'City', 'route' => 'admin.display_city', 'match' => ['admin/area/city']],
                ['key' => 'zones', 'label_key' => 'admin_labels.zones', 'label_fallback' => 'Zones', 'route' => 'admin.zones.index', 'match' => ['admin/zones']],
                ['key' => 'location_bulk_upload', 'label_key' => 'admin_labels.bulk_upload', 'label_fallback' => 'Bulk Upload', 'route' => 'admin.location_bulk_upload.index', 'match' => ['admin/area/location_bulk_upload', 'admin/area/location_bulk_upload/*']],
            ],
        ],
        [
            'key' => 'group_languages', 'label_key' => 'admin_labels.language_settings', 'label_fallback' => 'Languages',
            'icon' => 'bx bx-text', 'match' => ['admin/settings/language*', 'admin/settings/manage_language*', 'admin/language/bulk_translation_upload*', 'admin/web_settings/language*'],
            'children' => [
                ['key' => 'language_index', 'label_key' => 'admin_labels.language', 'label_fallback' => 'Language', 'route' => 'language.index', 'match' => ['admin/settings/language', 'admin/settings/language/*']],
                ['key' => 'language_manage', 'label_key' => 'admin_labels.manage_language', 'label_fallback' => 'Manage Language', 'route' => 'manage_language.index', 'match' => ['admin/settings/manage_language', 'admin/settings/manage_language/*']],
                ['key' => 'language_bulk', 'label_key' => 'admin_labels.bulk_upload', 'label_fallback' => 'Multi Language Bulk<br>Import', 'label_html' => true, 'route' => 'translation_bulk_upload.index', 'match' => ['admin/language/bulk_translation_upload', 'admin/language/bulk_translation_upload/*']],
                ['key' => 'web_languages', 'label_key' => 'admin_labels.languages', 'label_fallback' => 'Web Languages', 'route' => 'web_language', 'match' => ['admin/web_settings/language', 'admin/web_settings/language/*']],
            ],
        ],
        [
            'key' => 'group_reports', 'label_key' => 'admin_labels.reports', 'label_fallback' => 'Reports',
            'icon' => 'bx bx-bar-chart-alt-2', 'match' => ['admin/reports*', 'admin/settings/sales_reports*'],
            'children' => [
                ['key' => 'sales_reports', 'label_key' => 'admin_labels.sales_reports', 'label_fallback' => 'Sales Reports', 'route' => 'admin.sales_reports.index', 'match' => ['admin/reports/', 'admin/settings/sales_reports/*']],
            ],
        ],
        [
            'key' => 'group_system', 'label_key' => 'admin_labels.group_system', 'label_fallback' => 'System',
            'icon' => 'bx bx-cog',
            'match' => ['admin/settings', 'admin/web_settings/general_settings*', 'admin/web_settings/pwa_settings*', 'admin/web_settings/firebase*', 'admin/system_users*', 'admin/manage_system_users*'],
            'children' => [
                ['key' => 'settings_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.system_settings', 'label_fallback' => 'System Settings'],
                ['key' => 'settings_index', 'label_key' => 'admin_labels.settings', 'label_fallback' => 'Settings', 'route' => 'settings.index', 'match' => ['admin/settings']],

                ['key' => 'web_settings_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.web_settings', 'label_fallback' => 'Web Settings'],
                [
                    'key' => 'web_settings_dropdown', 'label_key' => 'admin_labels.web_settings', 'label_fallback' => 'Web Settings',
                    'match' => ['admin/web_settings*'],
                    'children' => [
                        ['key' => 'general_settings', 'label_key' => 'admin_labels.general_settings', 'label_fallback' => 'General Settings', 'route' => 'general_settings', 'match' => ['admin/web_settings/general_settings', 'admin/web_settings/general_settings*']],
                        ['key' => 'pwa_settings', 'label_key' => 'admin_labels.general_settings', 'label_fallback' => 'PWA Settings', 'route' => 'pwa_settings', 'match' => ['admin/web_settings/pwa_settings', 'admin/web_settings/pwa_settings*']],
                        ['key' => 'firebase', 'label_key' => 'admin_labels.firebase', 'label_fallback' => 'Firebase', 'route' => 'firebase', 'match' => ['admin/web_settings/firebase', 'admin/web_settings/firebase*']],
                    ],
                ],

                ['key' => 'system_users_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.system_users', 'label_fallback' => 'System Users'],
                ['key' => 'system_users_index', 'label_key' => 'admin_labels.system_users', 'label_fallback' => 'System Users', 'route' => 'admin.system_users.index', 'match' => ['admin/system_users', 'admin/system_users/*']],
                ['key' => 'system_users_manage', 'label_key' => 'admin_labels.manage_system_users', 'label_fallback' => 'Manage System Users', 'route' => 'admin.manage_system_users', 'match' => ['admin/manage_system_users', 'admin/manage_system_users/*'], 'permission' => 'view system_user'],
            ],
        ],
    ],

    'seller' => [
        [
            'key' => 'dashboard', 'label_key' => 'admin_labels.home', 'label_fallback' => 'Home',
            'icon' => 'bx bx-tachometer', 'route' => 'seller.home', 'match' => ['seller/home', 'seller/home/*'],
        ],
        [
            'key' => 'group_sales', 'label_key' => 'admin_labels.group_sales', 'label_fallback' => 'Sales',
            'icon' => 'bx bx-card',
            'match' => ['seller/orders*', 'seller/point_of_sale*', 'seller/manage_stock*', 'seller/manage_combo_stock*', 'seller/manage_product_deliverability*', 'seller/manage_combo_product_deliverability*', 'seller/return_request*'],
            'children' => [
                [
                    'key' => 'orders_dropdown', 'label_key' => 'admin_labels.orders_manage', 'label_fallback' => 'Orders Manage',
                    'match' => ['seller/orders', 'seller/orders*'],
                    'children' => [
                        ['key' => 'orders_index', 'label_key' => 'admin_labels.orders', 'label_fallback' => 'Orders', 'route' => 'seller.orders.index', 'match' => ['seller/orders', 'seller/orders*']],
                    ],
                ],
                ['key' => 'pos', 'label_key' => 'admin_labels.point_of_sale', 'label_fallback' => 'Point Of Sale', 'route' => 'seller.point_of_sale.index', 'match' => ['seller/point_of_sale', 'seller/point_of_sale/*']],
                ['key' => 'stock_manage', 'label_key' => 'admin_labels.stock_manage', 'label_fallback' => 'Stock Manage', 'route' => 'seller.manage_stock.index', 'match' => ['seller/manage_stock', 'seller/manage_stock/*']],
                ['key' => 'combo_stock_manage', 'label_key' => 'admin_labels.combo_stock_manage', 'label_fallback' => 'Combo Stock Manage', 'route' => 'seller.manage_combo_stock.index', 'match' => ['seller/manage_combo_stock', 'seller/manage_combo_stock/*']],
                ['key' => 'product_deliverability', 'label_key' => 'admin_labels.manage_product_deliverability', 'label_fallback' => 'Product Deliverability Manage', 'route' => 'seller.manage_product_deliverability.index', 'match' => ['seller/manage_product_deliverability']],
                ['key' => 'combo_product_deliverability', 'label_key' => 'admin_labels.manage_combo_product_deliverability', 'label_fallback' => 'Combo Product Deliverability Manage', 'route' => 'seller.manage_combo_product_deliverability.index', 'match' => ['seller/manage_combo_product_deliverability']],
                ['key' => 'returns_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.return_requests', 'label_fallback' => 'Return Requests'],
                ['key' => 'returns_index', 'label_key' => 'admin_labels.return_requests', 'label_fallback' => 'Return Requests', 'route' => 'seller.return_request.index', 'match' => ['seller/return_request', 'seller/return_request/*']],
            ],
        ],
        [
            'key' => 'group_catalog', 'label_key' => 'admin_labels.group_catalog', 'label_fallback' => 'Catalog',
            'icon' => 'bx bx-cart-alt',
            'match' => ['seller/categories*', 'seller/brands*', 'seller/tax*', 'seller/products/attributes*', 'seller/products*', 'seller/product_faqs*', 'seller/product/*', 'seller/combo_product*', 'seller/combo_products*', 'seller/wholesaler_marketplace*'],
            'children' => [
                ['key' => 'categories', 'label_key' => 'admin_labels.categories', 'label_fallback' => 'Categories', 'route' => 'seller_categories.index', 'match' => ['seller/categories', 'seller/categories/*']],
                ['key' => 'brands', 'label_key' => 'admin_labels.brands', 'label_fallback' => 'Brands', 'route' => 'seller_brands.index', 'match' => ['seller/brands', 'seller/brands/*']],

                ['key' => 'products_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.products', 'label_fallback' => 'Products'],
                ['key' => 'tax', 'label_key' => 'admin_labels.tax', 'label_fallback' => 'Tax', 'route' => 'tax.index', 'match' => ['seller/tax', 'seller/tax/*']],
                ['key' => 'attributes', 'label_key' => 'admin_labels.attributes', 'label_fallback' => 'Attributes', 'route' => 'attributes.index', 'match' => ['seller/products/attributes']],
                [
                    'key' => 'products_dropdown', 'label_key' => 'admin_labels.products_manage', 'label_fallback' => 'Products Manage',
                    'match' => ['seller/products', 'seller/products/*', 'seller/product_faqs'],
                    'children' => [
                        ['key' => 'products_add', 'label_key' => 'admin_labels.add_products', 'label_fallback' => 'Add Products', 'route' => 'seller.products.index', 'match' => ['seller/products']],
                        ['key' => 'products_manage', 'label_key' => 'admin_labels.manage_products', 'label_fallback' => 'Manage Products', 'route' => 'seller.products.manage_product', 'match' => ['seller/products/manage_product']],
                        ['key' => 'products_faqs', 'label_key' => 'admin_labels.product_faqs', 'label_fallback' => 'Product FAQs', 'route' => 'seller.product_faqs.index', 'match' => ['seller/product_faqs']],
                        ['key' => 'products_bulk', 'label_key' => 'admin_labels.bulk_upload', 'label_fallback' => 'Bulk Upload', 'route' => 'seller.product_bulk_upload', 'match' => ['seller/product/product_bulk_upload']],
                    ],
                ],

                ['key' => 'supplier_marketplace_title', 'is_subtitle' => true, 'label_key' => 'wholesaler_labels.wholesaler_marketplace', 'label_fallback' => 'Supplier Marketplace'],
                ['key' => 'supplier_marketplace_browse', 'label_key' => 'wholesaler_labels.browse_import', 'label_fallback' => 'Browse & Import', 'route' => 'seller.wholesaler_marketplace.index', 'match' => ['seller/wholesaler_marketplace']],
                ['key' => 'supplier_marketplace_orders', 'label_key' => 'wholesaler_labels.my_supplier_orders', 'label_fallback' => 'My Supplier Orders', 'route' => 'seller.wholesaler_marketplace.orders', 'match' => ['seller/wholesaler_marketplace/orders']],

                ['key' => 'combo_title', 'is_subtitle' => true, 'label_key' => 'admin_labels.combo_products_manage', 'label_fallback' => 'Combo Products Manage'],
                ['key' => 'combo_attributes', 'label_key' => 'admin_labels.attributes', 'label_fallback' => 'Attributes', 'route' => 'seller.combo_product_attributes.index', 'match' => ['seller/combo_product_attributes']],
                ['key' => 'combo_add', 'label_key' => 'admin_labels.add_combo_products', 'label_fallback' => 'Add Combo Products', 'route' => 'seller.combo_products.index', 'match' => ['seller/combo_products']],
                ['key' => 'combo_manage', 'label_key' => 'admin_labels.manage_products', 'label_fallback' => 'Manage Products', 'route' => 'seller.combo_products.manage_product', 'match' => ['seller/combo_products/manage_product']],
                ['key' => 'combo_faqs', 'label_key' => 'admin_labels.product_faqs', 'label_fallback' => 'Product FAQs', 'route' => 'seller.combo_product_faqs.index', 'match' => ['seller/combo_product_faqs']],
            ],
        ],
        [
            'key' => 'group_website', 'label_key' => 'admin_labels.group_website', 'label_fallback' => 'Website',
            'icon' => 'bx bx-image-add', 'match' => ['seller/media*'],
            'children' => [
                ['key' => 'media', 'label_key' => 'admin_labels.add_media', 'label_fallback' => 'Add Media', 'route' => 'seller.media', 'match' => ['seller/media', 'seller/media/*']],
            ],
        ],
        [
            'key' => 'group_marketing', 'label_key' => 'admin_labels.group_marketing', 'label_fallback' => 'Marketing',
            'icon' => 'bx bx-link-alt', 'match' => ['seller/affiliate_program*'],
            'children' => [
                ['key' => 'affiliate_program', 'label_key' => 'admin_labels.affiliate_program', 'label_fallback' => 'Affiliate Program', 'route' => 'seller.affiliate_program.index', 'match' => ['seller/affiliate_program']],
                ['key' => 'creator_marketplace', 'label_key' => 'admin_labels.creator_marketplace', 'label_fallback' => 'Creator Marketplace', 'route' => 'seller.creator_marketplace.index', 'match' => ['seller/creator_marketplace*'], 'feature_flag' => 'creator_marketplace'],
            ],
        ],
        [
            'key' => 'group_finance', 'label_key' => 'admin_labels.group_finance', 'label_fallback' => 'Finance',
            'icon' => 'bx bx-wallet-alt', 'match' => ['seller/transaction/wallet_transactions*', 'seller/payment_request*', 'seller/payment_gateways*'],
            'children' => [
                ['key' => 'wallet_transaction', 'label_key' => 'admin_labels.wallet_transaction', 'label_fallback' => 'Wallet Transaction', 'route' => 'seller.transaction.wallet_transactions', 'match' => ['seller/transaction/wallet_transactions']],
                ['key' => 'withdrawal_requests', 'label_key' => 'admin_labels.withdrawal_requests', 'label_fallback' => 'Withdrawal Requests', 'route' => 'seller.payment_request.withdrawal_requests', 'match' => ['seller/payment_request/withdrawal_requests']],
                ['key' => 'payment_gateways', 'label_key' => 'admin_labels.payment_gateways', 'label_fallback' => 'Payment Gateways', 'route' => 'seller.payment_gateways.index', 'match' => ['seller/payment_gateways']],
            ],
        ],
        [
            'key' => 'group_subscription', 'label_key' => 'admin_labels.my_subscription', 'label_fallback' => 'My Subscription',
            'icon' => 'bx bx-crown', 'match' => ['seller/my_subscription*'],
            'children' => [
                ['key' => 'my_subscription', 'label_key' => 'admin_labels.my_subscription', 'label_fallback' => 'My Subscription', 'route' => 'seller.my_subscription.index', 'match' => ['seller/my_subscription']],
            ],
        ],
        [
            'key' => 'group_communication', 'label_key' => 'admin_labels.group_communication', 'label_fallback' => 'Communication',
            'icon' => 'bx bx-chat', 'match' => ['seller/chat*'],
            'children' => [
                ['key' => 'chat', 'label_key' => 'admin_labels.chats', 'label_fallback' => 'Chats', 'route' => 'seller.chat.index', 'match' => ['seller/chat', 'seller/chat/*'], 'badge' => 'unread_messages'],
            ],
        ],
        [
            'key' => 'group_locations', 'label_key' => 'admin_labels.location_management', 'label_fallback' => 'Locations',
            'icon' => 'bx bx-map', 'match' => ['seller/area*'],
            'children' => [
                ['key' => 'zipcodes', 'label_key' => 'admin_labels.zipcodes', 'label_fallback' => 'Zipcodes', 'route' => 'seller.zipcodes', 'match' => ['seller/area/zipcodes']],
                ['key' => 'city', 'label_key' => 'admin_labels.city', 'label_fallback' => 'City', 'route' => 'seller.city', 'match' => ['seller/area/city']],
                ['key' => 'zones', 'label_key' => 'admin_labels.zones', 'label_fallback' => 'Zones', 'route' => 'seller.zones', 'match' => ['seller/area/zones']],
            ],
        ],
        [
            'key' => 'group_languages', 'label_key' => 'admin_labels.language_settings', 'label_fallback' => 'Languages',
            'icon' => 'bx bx-text', 'match' => ['seller/language/bulk_translation_upload*'],
            'children' => [
                ['key' => 'language_bulk', 'label_key' => 'admin_labels.bulk_upload', 'label_fallback' => 'Multi Language Bulk<br>Import', 'label_html' => true, 'route' => 'seller.translation_bulk_upload.index', 'match' => ['seller/language/bulk_translation_upload', 'seller/language/bulk_translation_upload/*']],
            ],
        ],
        [
            'key' => 'group_reports', 'label_key' => 'admin_labels.reports_and_sales_management', 'label_fallback' => 'Reports',
            'icon' => 'bx bx-bar-chart-alt-2', 'match' => ['seller/reports*'],
            'children' => [
                ['key' => 'sales_report', 'label_key' => 'admin_labels.sales_report', 'label_fallback' => 'Sales Report', 'route' => 'seller.reports.sales_report', 'match' => ['seller/reports/sales_report']],
            ],
        ],
    ],

    'delivery_boy' => [
        [
            'key' => 'dashboard', 'label_key' => 'admin_labels.dashboard', 'label_fallback' => 'Dashboard',
            'icon' => 'bx bx-tachometer', 'route' => 'delivery_boy.home', 'match' => ['delivery_boy/home', 'delivery_boy/home/*'],
        ],
        [
            'key' => 'group_deliveries', 'label_key' => 'admin_labels.group_deliveries', 'label_fallback' => 'Deliveries',
            'icon' => 'bx bx-package', 'match' => ['delivery_boy/orders*', 'delivery_boy/returned_orders*'],
            'children' => [
                [
                    'key' => 'orders_dropdown', 'label_key' => 'admin_labels.orders_manage', 'label_fallback' => 'Orders Manage',
                    'match' => ['delivery_boy/orders', 'delivery_boy/orders*'],
                    'children' => [
                        ['key' => 'orders_index', 'label_key' => 'admin_labels.orders', 'label_fallback' => 'Orders', 'route' => 'delivery_boy.orders.index', 'match' => ['delivery_boy/orders']],
                    ],
                ],
                ['key' => 'returned_orders', 'label_key' => 'admin_labels.returned_orders', 'label_fallback' => 'Returned Orders', 'route' => 'delivery_boy.cash.returned_order', 'match' => ['delivery_boy/returned_orders']],
            ],
        ],
        [
            'key' => 'group_finance', 'label_key' => 'admin_labels.group_finance', 'label_fallback' => 'Finance',
            'icon' => 'bx bx-wallet-alt', 'match' => ['delivery_boy/cash_collection*', 'delivery_boy/fund_transfer*', 'delivery_boy/wallet_transaction*'],
            'children' => [
                ['key' => 'cash_collection', 'label_key' => 'admin_labels.cash_collection', 'label_fallback' => 'Cash Collection', 'route' => 'delivery_boy.cash.collection', 'match' => ['delivery_boy/cash_collection']],
                ['key' => 'fund_transfer', 'label_key' => 'admin_labels.fund_transfer', 'label_fallback' => 'Fund Transfer', 'route' => 'delivery_boy.fund.transfer', 'match' => ['delivery_boy/fund_transfer']],
                ['key' => 'wallet_transaction', 'label_key' => 'admin_labels.wallet_transaction', 'label_fallback' => 'Wallet Transaction', 'route' => 'delivery_boy.walletTransaction', 'match' => ['delivery_boy/wallet_transaction']],
            ],
        ],
    ],

    'affiliate' => [
        [
            'key' => 'dashboard', 'label_key' => 'admin_labels.home', 'label_fallback' => 'Home',
            'icon' => 'bx bx-tachometer', 'route' => 'affiliate.dashboard', 'match' => ['affiliate/dashboard'],
        ],
        [
            'key' => 'group_marketplace', 'label_key' => 'admin_labels.group_marketplace', 'label_fallback' => 'Marketplace',
            'icon' => 'bx bx-package', 'match' => ['affiliate/products*', 'affiliate/private_stores*'],
            'children' => [
                ['key' => 'available_products', 'label_key' => 'admin_labels.available_products', 'label_fallback' => 'Available Products', 'route' => 'affiliate.products.page', 'match' => ['affiliate/products', 'affiliate/products/*']],
                ['key' => 'private_stores', 'label_key' => 'admin_labels.private_stores', 'label_fallback' => 'Private Stores', 'route' => 'affiliate.stores.page', 'match' => ['affiliate/private_stores']],
            ],
        ],
        [
            'key' => 'group_creator', 'label_key' => 'admin_labels.group_creator', 'label_fallback' => 'Creator',
            'icon' => 'bx bx-camera-movie', 'match' => ['affiliate/creator*'], 'feature_flag' => 'creator_marketplace',
            'children' => [
                ['key' => 'creator_dashboard', 'label_key' => 'admin_labels.creator_dashboard', 'label_fallback' => 'Creator Dashboard', 'route' => 'affiliate.creator.dashboard', 'match' => ['affiliate/creator/dashboard']],
                ['key' => 'creator_requests', 'label_key' => 'admin_labels.content_requests', 'label_fallback' => 'Content Requests', 'route' => 'affiliate.creator.requests.index', 'match' => ['affiliate/creator/requests*']],
                ['key' => 'creator_content', 'label_key' => 'admin_labels.my_content', 'label_fallback' => 'My Content', 'route' => 'affiliate.creator.content.index', 'match' => ['affiliate/creator/content*']],
                ['key' => 'creator_profile', 'label_key' => 'admin_labels.creator_profile', 'label_fallback' => 'Creator Profile', 'route' => 'affiliate.creator.profile.edit', 'match' => ['affiliate/creator/profile*']],
            ],
        ],
        [
            'key' => 'group_earnings', 'label_key' => 'admin_labels.earnings', 'label_fallback' => 'Earnings',
            'icon' => 'bx bx-line-chart', 'match' => ['affiliate/commissions*', 'affiliate/withdrawals*'],
            'children' => [
                ['key' => 'commission_history', 'label_key' => 'admin_labels.commission_history', 'label_fallback' => 'Commission History', 'route' => 'affiliate.commissions.page', 'match' => ['affiliate/commissions']],
                ['key' => 'withdrawals', 'label_key' => 'admin_labels.withdrawals', 'label_fallback' => 'Withdrawals', 'route' => 'affiliate.withdrawals.page', 'match' => ['affiliate/withdrawals']],
            ],
        ],
    ],

    // Kept as 'wholesaler' in code (routes/tables/permissions) per the Supplier-vs-Wholesaler naming
    // decision - only the labels below read "Supplier" for the master-prompt-aligned UI.
    'wholesaler' => [
        [
            'key' => 'dashboard', 'label_key' => 'admin_labels.dashboard', 'label_fallback' => 'Dashboard',
            'icon' => 'bx bx-tachometer', 'route' => 'wholesaler.home', 'match' => ['wholesaler/home'],
        ],
        [
            'key' => 'products', 'label_key' => 'wholesaler_labels.my_products', 'label_fallback' => 'My Products',
            'icon' => 'bx bx-package', 'route' => 'wholesaler.products.index', 'match' => ['wholesaler/products*'],
        ],
        [
            'key' => 'pricing', 'label_key' => 'wholesaler_labels.wholesale_pricing', 'label_fallback' => 'Wholesale Pricing',
            'icon' => 'bx bx-purchase-tag-alt', 'route' => 'wholesaler.pricing.index', 'match' => ['wholesaler/pricing*'],
        ],
        [
            'key' => 'orders', 'label_key' => 'wholesaler_labels.orders', 'label_fallback' => 'Orders',
            'icon' => 'bx bx-cart', 'route' => 'wholesaler.orders.index', 'match' => ['wholesaler/orders*'],
        ],
        [
            'key' => 'stock', 'label_key' => 'wholesaler_labels.stock', 'label_fallback' => 'Stock',
            'icon' => 'bx bx-box', 'route' => 'wholesaler.stock.index', 'match' => ['wholesaler/stock*'],
        ],
        [
            'key' => 'sales', 'label_key' => 'wholesaler_labels.sales', 'label_fallback' => 'Sales',
            'icon' => 'bx bx-line-chart', 'route' => 'wholesaler.reports.sales', 'match' => ['wholesaler/reports*'],
        ],
        [
            'key' => 'clients', 'label_key' => 'wholesaler_labels.my_buyers', 'label_fallback' => 'My Buyers',
            'icon' => 'bx bx-group', 'route' => 'wholesaler.clients.index', 'match' => ['wholesaler/clients*'],
        ],
    ],

];
