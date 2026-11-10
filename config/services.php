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

    // BC gov SSO via PDEX / Keycloak. PDEX authenticates the user and POSTs the
    // signed token back to /pdex-login; the API credentials drive PdexService.
    'pdex' => [
        'login_url' => env('PDEX_LOGIN_URL'),
        'logout_url' => env('PDEX_LOGOUT_URL'),
        'jwt_audience' => env('PDEX_JWT_AUDIENCE'),
        'api_url' => env('PDEX_API_URL'),
        'client_id' => env('PDEX_CLIENT_ID'),
        'client_secret' => env('PDEX_CLIENT_SECRET'),
        'token_endpoint' => env('PDEX_TOKEN_ENDPOINT'),
    ],

    // BC Express Pay (Worldline) through the Payment Card Industry Redirect (PCIR).
    // URLs default to the PCIR test gateway; prod sets them in Vault.
    'expresspay' => [
        'enabled' => env('EXPRESS_PAY_ENABLED'),
        'merchant_id' => env('EXPRESS_PAY_MERCHANT_ID'),
        'hash_key' => env('EXPRESS_PAY_HASH_KEY'),
        'payment_url' => env('EXPRESS_PAY_PAYMENT_URL'),
        'query_url' => env('EXPRESS_PAY_QUERY_URL'),
        'hash_expiry_minutes' => (int) env('EXPRESS_PAY_HASH_EXPIRY_MINUTES'),
        // Must differ per environment that shares a merchant account (order numbers must be unique there).
        'order_number_base' => (int) env('EXPRESS_PAY_ORDER_NUMBER_BASE'),
    ],

];
