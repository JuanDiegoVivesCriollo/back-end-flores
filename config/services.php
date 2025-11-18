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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'izipay' => [
        'shop_id' => env('IZIPAY_SHOP_ID', '64777864'),
        'username' => env('IZIPAY_USERNAME', '64777864'),

        // Claves para Formularios Tradicionales (V1, V2, SOAP)
        'test_key' => env('IZIPAY_TEST_KEY', 'y0zvHhS7F1H25wZR'),
        'prod_key' => env('IZIPAY_PROD_KEY', 'pSW2JP4L2DXUV9gs'),

        // Claves para API REST
        'test_password' => env('IZIPAY_TEST_PASSWORD', 'testpassword_AU6IaOFyN0z06uGAsS1omAh74w3kTGZGWARUISlfX7sSq'),
        'prod_password' => env('IZIPAY_PROD_PASSWORD', 'prodpassword_lDSW2xtfVurlXhz4OjpIZolKQYoYTZ0dYTDmSv4wtu0WI'),

        // Claves públicas para navegador (JavaScript SDK)
        'test_public_key' => env('IZIPAY_TEST_PUBLIC_KEY', '64777864:testpublickey_PBh2zlxZ32b34Zmro8TLhR5wZc3IGKHTNTP84zdOLZNiB'),
        'prod_public_key' => env('IZIPAY_PROD_PUBLIC_KEY', '64777864:publickey_c5GOaXEESw43yhyJmw2749QvDSImxjTBLNNCQxK1DAKcj'),

        // Claves HMAC-SHA-256 para verificación de firmas
        'test_hmac_key' => env('IZIPAY_TEST_HMAC_KEY', 'myF6fNK2w6eZpyKLJ2mrouIdehOE5tHKxbtBhC0aKhqZ6'),
        'prod_hmac_key' => env('IZIPAY_PROD_HMAC_KEY', 'lthS1d8gsRRHzJO0feJono5kGNnNcKIWzFGjdpo7H7Xvp'),

        // URLs y configuración
        'js_url' => env('IZIPAY_JS_URL', 'https://static.micuentaweb.pe/static/js/krypton-client/V4.0/stable/kr-payment-form.min.js'),
        'environment' => env('IZIPAY_ENVIRONMENT', 'production'),
        'ctx_mode' => env('IZIPAY_CTX_MODE', 'PRODUCTION'),
        'integration_mode' => env('IZIPAY_INTEGRATION_MODE', 'form'), // 'sdk' o 'form' - usando 'form' que funciona

        // Endpoints
        'api_endpoint' => 'https://api.micuentaweb.pe',
        'sandbox_endpoint' => 'https://sandbox-checkout.izipay.pe',
        'production_endpoint' => 'https://checkout.izipay.pe',
    ],

];
