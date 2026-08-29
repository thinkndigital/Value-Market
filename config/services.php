<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID') ?? '',
        'client_secret' => env('GOOGLE_CLIENT_SECRET') ?? '',
        'redirect' => env('GOOGLE_REDIRECT_URI') ?? "",
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    // Shiprocket credentials themselves are NOT read from here - they live in the `shipping_method`
    // Setting row (admin/pages/forms/shipping_settings.blade.php), matching this app's existing convention
    // for gateway credentials (Razorpay/Stripe/Paystack). These are only the operational knobs that don't
    // belong in the database: the API base URL (overridable in tests so a real network call is never made)
    // and the timeouts/token TTL that app/Libraries/Shiprocket.php uses to avoid hanging requests and to
    // avoid re-authenticating against Shiprocket's /auth/login on every single API call.
    'shiprocket' => [
        'base_url' => env('SHIPROCKET_BASE_URL', 'https://apiv2.shiprocket.in/v1/external/'),
        'timeout' => (int) env('SHIPROCKET_TIMEOUT', 15),
        'connect_timeout' => (int) env('SHIPROCKET_CONNECT_TIMEOUT', 8),
        // Shiprocket bearer tokens are valid ~10 days; cached for 9 to stay safely inside that window.
        'token_ttl_minutes' => (int) env('SHIPROCKET_TOKEN_TTL_MINUTES', 9 * 24 * 60),
    ],
];
