<?php

return [
    'DEMO' => '1',
    'DEMO_ERROR' => "This Operation is not allowed in DEMO Mode",

    'DIRECTORY_SEPARATOR' => '/',
    'UPDATE_PATH' => 'update/',
    'NO_USER_IMAGE' => 'assets/img/',
    'NO_SHOP_IMAGE' => 'assets/img/no-shop-img.png',
    'DEFAULT_LOGO' => 'assets/img/default_full_logo.png',
    'NO_IMAGE' => 'assets/img/no-image.jpg',
    'USER_IMG_PATH' => 'storage/user_image',
    'MEDIA_PATH' => 'storage/',
    'REVIEW_IMG_PATH' => 'storage/review_images',
    'STORE_IMG_PATH' => 'storage/store_images',
    'SELLER_IMG_PATH' => 'storage/sellers',
    'DELIVERY_BOY_IMG_PATH' => 'storage/delivery_boys',
    // ticket status
    'PENDING' => '1',
    'OPENED' => '2',
    'RESOLVED' => '3',
    'CLOSED' => '4',
    'REOPEN' => '5',
    'theme' => 'elegant',
    'APP_CODE' => '56320259',
    'WEB_CODE' => '56605998',
    // demo mode
    'ALLOW_MODIFICATION' => 1,

    // Phase 2 (docs/PHASE_2_IDOR_AUDIT.md §4): shared secret for the unauthenticated cron-trigger routes
    // (admin/cronjob/settleCashbackDiscount, admin/cronjob/sendCartReminders) - set CRON_SECRET in .env and
    // pass it as ?cron_secret=... in whatever hits these URLs on a schedule (cPanel cron job, external
    // scheduler). Empty/unset fails closed (VerifyCronSecret middleware blocks with 403), not open.
    'CRON_SECRET' => env('CRON_SECRET'),

    // Temporarily disables CheckPurchaseCode (app/Http/Middleware/CheckPurchaseCode.php), which otherwise
    // gates every admin/seller/delivery-boy route behind a purchase code verified against the original
    // eShop Plus vendor's (WrTeam) external license server - not something Value Market has its own code
    // for. Off for now by design; set PURCHASE_CODE_CHECK_ENABLED=true in .env once a Value Market-owned
    // licensing provider replaces that check in SettingController::systemRegister()/WebsystemRegister().
    'PURCHASE_CODE_CHECK_ENABLED' => env('PURCHASE_CODE_CHECK_ENABLED', false),
];
