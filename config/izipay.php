<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Izipay Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Izipay payment gateway with punycode domain support
    |
    */

    'environment' => env('IZIPAY_ENVIRONMENT', 'test'),

    'credentials' => [
        'username' => env('IZIPAY_USERNAME'),
        'password' => env('IZIPAY_ENVIRONMENT') === 'test' ? env('IZIPAY_TEST_PASSWORD') : env('IZIPAY_PROD_PASSWORD'),
        'public_key' => env('IZIPAY_ENVIRONMENT') === 'test' ? env('IZIPAY_TEST_PUBLIC_KEY') : env('IZIPAY_PROD_PUBLIC_KEY'),
        'hmac_key' => env('IZIPAY_ENVIRONMENT') === 'test' ? env('IZIPAY_TEST_KEY') : env('IZIPAY_PROD_KEY'),
    ],

    'api' => [
        'endpoint' => env('IZIPAY_ENVIRONMENT') === 'test'
            ? 'https://api.micuentaweb.pe/api-payment/V4/Charge/CreatePayment'
            : 'https://api.micuentaweb.pe/api-payment/V4/Charge/CreatePayment',
        'js_sdk' => 'https://static.micuentaweb.pe/static/js/krypton-client/V4.0/stable/kr-payment-form.min.js',
    ],

    'domain' => [
        'punycode' => 'xn--floresdejazmnflorera-04bh.com',
        'unicode' => 'floresdejazmínflorería.com',
        'protocol' => 'https',
    ],

    'return_urls' => [
        'success' => '/checkout/success',
        'error' => '/checkout/error',
        'cancel' => '/checkout/cancel',
    ],

    'cors' => [
        'allowed_origins' => [
            'https://xn--floresdejazmnflorera-04bh.com',
            'https://floresdejazmínflorería.com'
        ],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        'allow_credentials' => true,
    ],

];
