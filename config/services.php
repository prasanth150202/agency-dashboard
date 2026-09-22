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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // BRIX's real Shopify App Store listing — never invent this value.
    // The Node/React Router Shopify app (cartdrawerv2_ui, repo
    // Cart_ninja_combo1, deployed at cartdrawer.fly.dev) owns the actual
    // OAuth flow; app_auth_url is its own /auth entry point, hit with
    // ?shop=... to install or re-authenticate.
    'shopify' => [
        'app_store_url' => env('BRIX_SHOPIFY_APP_STORE_URL', 'https://apps.shopify.com/thebrix-io'),
        'app_auth_url' => env('SHOPIFY_APP_AUTH_URL', 'https://cartdrawer.fly.dev/auth'),
        // The live BRIX Shopify backend (php_backend, deployed at
        // int.thebrix.io). Used to counter-check real Shopify install
        // state — store_install_status.php — when brix_superadmin's own
        // stores.installation_status mirror hasn't caught up.
        'backend_url' => env('BRIX_BACKEND_URL', 'https://int.thebrix.io'),
        // Must match AGENCY_DASHBOARD_INTERNAL_SECRET in cartdrawerv2_ui's
        // php_backend/install_shop.php and uninstall_shop.php (the live
        // int.thebrix.io backend — Cartninja_admin_dashboard is retired).
        // No fallback default on purpose — an unset secret must fail
        // closed (500), never silently accept a known placeholder value.
        'internal_secret' => env('AGENCY_DASHBOARD_INTERNAL_SECRET', ''),
    ],

    'referrals' => [
        // How long a referral click bound to a shop domain stays eligible
        // to be credited when that shop's BRIX install callback arrives.
        'attribution_days' => (int) env('REFERRAL_ATTRIBUTION_DAYS', 7),
        // How long the signed "enter your store domain" link handed out by
        // /ref/{code} stays valid.
        'capture_minutes' => (int) env('REFERRAL_CAPTURE_MINUTES', 30),
    ],

];
