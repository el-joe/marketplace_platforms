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

    'firebase' => [
        'project_id'  => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS_PATH', storage_path('app/firebase-service-account.json')),
    ],

    // ─── Payment Gateways ─────────────────────────────────────────────────────

    'paytabs' => [
        'profile_id' => env('PAYTABS_PROFILE_ID'),
        'server_key' => env('PAYTABS_SERVER_KEY'),
        'region' => env('PAYTABS_REGION', 'ARE'),
        'base_url' => env('PAYTABS_BASE_URL', 'https://secure.paytabs.com'),
    ],

    'tabby' => [
        'secret_key' => env('TABBY_SECRET_KEY'),
        'public_key' => env('TABBY_PUBLIC_KEY'),
        'merchant_code' => env('TABBY_MERCHANT_CODE'),
    ],

    'noon_pay' => [
        'app_key' => env('NOON_PAY_APP_KEY'),
        'app_secret' => env('NOON_PAY_APP_SECRET'),
        'business_id' => env('NOON_PAY_BUSINESS_ID'),
        'env' => env('NOON_PAY_ENV', 'live'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    // ─── Shipping Carriers ────────────────────────────────────────────────────

    'aramex' => [
        'username' => env('ARAMEX_USERNAME'),
        'password' => env('ARAMEX_PASSWORD'),
        'account_number' => env('ARAMEX_ACCOUNT_NUMBER'),
        'account_pin' => env('ARAMEX_ACCOUNT_PIN'),
        'account_entity' => env('ARAMEX_ACCOUNT_ENTITY'),
        'account_country' => env('ARAMEX_ACCOUNT_COUNTRY', 'AE'),
        'env' => env('ARAMEX_ENV', 'live'),
    ],

    'bosta' => [
        'api_key' => env('BOSTA_API_KEY'),
        'env' => env('BOSTA_ENV', 'live'),
    ],

    'dhl' => [
        'username' => env('DHL_API_USERNAME'),
        'password' => env('DHL_API_PASSWORD'),
        'env' => env('DHL_ENV', 'live'),
    ],

    'fedex' => [
        'client_id' => env('FEDEX_CLIENT_ID'),
        'client_secret' => env('FEDEX_CLIENT_SECRET'),
        'account_number' => env('FEDEX_ACCOUNT_NUMBER'),
        'env' => env('FEDEX_ENV', 'live'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    | Set AI_PROVIDER in .env to switch from the mock stub to a real provider.
    | Options (once implemented): openai | replicate | falai
    | See: App\Services\AiProviderFactory
    */
    'ai_provider' => env('AI_PROVIDER', 'mock'),

    'turn' => [
        'url'        => env('TURN_URL', ''),
        'username'   => env('TURN_USERNAME', ''),
        'credential' => env('TURN_CREDENTIAL', ''),
    ],

];
