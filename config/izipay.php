<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Izipay Configuration - Flores D'Jazmin
    |--------------------------------------------------------------------------
    */

    'environment' => env('IZIPAY_ENVIRONMENT', 'test'),

    'credentials' => [
        'shop_id' => env('IZIPAY_SHOP_ID'),
        'username' => env('IZIPAY_USERNAME', env('IZIPAY_SHOP_ID')),
        
        // Password basado en el entorno
        'password' => env('IZIPAY_ENVIRONMENT') === 'production' 
            ? env('IZIPAY_PROD_PASSWORD') 
            : env('IZIPAY_TEST_PASSWORD'),
        
        // Public key basado en el entorno
        'public_key' => env('IZIPAY_ENVIRONMENT') === 'production' 
            ? env('IZIPAY_PROD_PUBLIC_KEY') 
            : env('IZIPAY_TEST_PUBLIC_KEY'),
        
        // HMAC key basado en el entorno (para verificar hash de respuestas)
        'hmac_key' => env('IZIPAY_ENVIRONMENT') === 'production' 
            ? env('IZIPAY_PROD_HMAC_KEY') 
            : env('IZIPAY_TEST_HMAC_KEY'),
        
        // Claves de API (para autenticación de formularios)
        'api_key' => env('IZIPAY_ENVIRONMENT') === 'production' 
            ? env('IZIPAY_PROD_KEY') 
            : env('IZIPAY_TEST_KEY'),
        
        // Todas las claves disponibles
        'test_key' => env('IZIPAY_TEST_KEY'),
        'prod_key' => env('IZIPAY_PROD_KEY'),
        'test_password' => env('IZIPAY_TEST_PASSWORD'),
        'prod_password' => env('IZIPAY_PROD_PASSWORD'),
        'test_public_key' => env('IZIPAY_TEST_PUBLIC_KEY'),
        'prod_public_key' => env('IZIPAY_PROD_PUBLIC_KEY'),
        'test_hmac_key' => env('IZIPAY_TEST_HMAC_KEY'),
        'prod_hmac_key' => env('IZIPAY_PROD_HMAC_KEY'),
    ],

    'api' => [
        'endpoint' => 'https://api.micuentaweb.pe/api-payment/V4/Charge/CreatePayment',
        'js_sdk' => 'https://static.micuentaweb.pe/static/js/krypton-client/V4.0/stable/kr-payment-form.min.js',
        'static_url' => 'https://static.micuentaweb.pe',
    ],

    'ctx_mode' => env('IZIPAY_CTX_MODE', 'TEST'),
    'integration_mode' => env('IZIPAY_INTEGRATION_MODE', 'form'),

    'return_urls' => [
        'success' => '/checkout/success',
        'error' => '/checkout/failed',
        'cancel' => '/checkout',
    ],

    'currency' => 'PEN',

];
