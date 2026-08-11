<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'bkash' => [
        'app_key' => env('BKASH_APP_KEY', 'sandbox_app_key'),
        'app_secret' => env('BKASH_APP_SECRET', 'sandbox_app_secret'),
        'username' => env('BKASH_USERNAME', 'sandbox_user'),
        'password' => env('BKASH_PASSWORD', 'sandbox_pass'),
        'script_url' => env('BKASH_SCRIPT_URL', 'https://scripts.sandbox.bKash.com/bKash-checkout-sandbox.js'),
        'baseUrl' => env('BKASH_BASE_URL', 'https://tokenized.sandbox.bKash.com/v1.2.0-beta'),
    ],

    'nagad' => [
        'merchant_id' => env('NAGAD_MERCHANT_ID', 'sandbox_merchant_id'),
        'public_key' => env('NAGAD_PUBLIC_KEY', 'sandbox_pub_key'),
        'private_key' => env('NAGAD_PRIVATE_KEY', 'sandbox_priv_key'),
        'baseUrl' => env('NAGAD_BASE_URL', 'https://sandbox.mypay.com.bd/api/dfs'),
    ],

    'sslcommerz' => [
        'store_id' => env('SSLCOMMERZ_STORE_ID', 'sandbox_store_id'),
        'store_password' => env('SSLCOMMERZ_STORE_PASSWORD', 'sandbox_store_pass'),
        'baseUrl' => env('SSLCOMMERZ_BASE_URL', 'https://sandbox.sslcommerz.com'),
    ],

];
