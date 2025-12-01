<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WebAuthnController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FlowerController;
use App\Http\Controllers\Api\ComplementController;
use App\Http\Controllers\Api\BreakfastController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\DirectPaymentController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\CheckoutController;

/*
|--------------------------------------------------------------------------
| API Routes - Flores D'Jazmin
|--------------------------------------------------------------------------
*/

// Health check
Route::get('health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now()->toISOString(),
        'app' => "Flores D'Jazmin API",
        'version' => '1.0.0'
    ]);
});

// API v1 routes
Route::prefix('v1')->group(function () {

    // =====================================================
    // AUTHENTICATION ROUTES
    // =====================================================
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);

        // Protected auth routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::post('verify-token', [AuthController::class, 'verifyToken']);
            Route::get('profile', [AuthController::class, 'profile']);
            Route::put('profile', [AuthController::class, 'updateProfile']);
            Route::put('change-password', [AuthController::class, 'changePassword']);
        });
    });

    // =====================================================
    // WEBAUTHN ROUTES (Biometric Authentication)
    // =====================================================
    Route::prefix('webauthn')->group(function () {
        // Public routes - check availability before login
        Route::post('check-availability', [WebAuthnController::class, 'checkAvailability']);
        Route::post('login-options', [WebAuthnController::class, 'loginOptions']);
        Route::post('login', [WebAuthnController::class, 'login']);

        // Protected routes - require authentication
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('register-options', [WebAuthnController::class, 'registerOptions']);
            Route::post('register', [WebAuthnController::class, 'register']);
            Route::get('credentials', [WebAuthnController::class, 'listCredentials']);
            Route::delete('credentials/{id}', [WebAuthnController::class, 'deleteCredential']);
        });
    });

    // =====================================================
    // CATALOG ROUTES (Public)
    // =====================================================
    Route::prefix('catalog')->group(function () {
        // Categories
        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('categories/{id}', [CategoryController::class, 'show']);
        Route::get('categories/{id}/statistics', [CategoryController::class, 'statistics']);

        // Flowers
        Route::get('flowers', [FlowerController::class, 'index']);
        Route::get('flowers/featured', [FlowerController::class, 'featured']);
        Route::get('flowers/on-sale', [FlowerController::class, 'onSale']);
        Route::get('flowers/search', [FlowerController::class, 'search']);
        Route::get('flowers/colors', [FlowerController::class, 'getColors']);
        Route::get('flowers/occasions', [FlowerController::class, 'getOccasions']);
        Route::get('flowers/types', [FlowerController::class, 'getFlowerTypes']);
        Route::get('flowers/{id}', [FlowerController::class, 'show']);
        Route::get('flowers/category/{categoryId}', [FlowerController::class, 'byCategory']);

        // Complements
        Route::get('complements', [ComplementController::class, 'index']);
        Route::get('complements/types', [ComplementController::class, 'getTypes']);
        Route::get('complements/featured', [ComplementController::class, 'featured']);
        Route::get('complements/{id}', [ComplementController::class, 'show']);

        // Breakfasts
        Route::get('breakfasts', [BreakfastController::class, 'index']);
        Route::get('breakfasts/featured', [BreakfastController::class, 'featured']);
        Route::get('breakfasts/{id}', [BreakfastController::class, 'show']);
    });

    // =====================================================
    // DELIVERY ROUTES (Public)
    // =====================================================
    Route::prefix('delivery')->group(function () {
        Route::get('districts', [DeliveryController::class, 'getDistricts']);
        Route::post('calculate', [DeliveryController::class, 'calculateShipping']);
        Route::post('check-free-shipping', [DeliveryController::class, 'checkFreeShipping']);
    });

    // =====================================================
    // CHECKOUT ROUTES (Public)
    // =====================================================
    Route::prefix('checkout')->group(function () {
        Route::get('districts', [CheckoutController::class, 'getDistricts']);
        Route::get('store-info', [CheckoutController::class, 'getStoreInfo']);
        Route::get('payment-info', [CheckoutController::class, 'getPaymentInfo']);
        Route::post('guest-customer', [CheckoutController::class, 'createGuestCustomer']);
        Route::post('order', [CheckoutController::class, 'createOrder']);
        Route::post('order/{orderNumber}/payment-proof', [CheckoutController::class, 'uploadPaymentProof']);
        Route::get('order/{orderNumber}', [CheckoutController::class, 'getOrder']);
        Route::post('notify-whatsapp', [CheckoutController::class, 'notifyOwnerWhatsApp']);
    });

    // =====================================================
    // ORDER ROUTES
    // =====================================================
    Route::prefix('orders')->group(function () {
        // Direct payment orders (no auth required)
        Route::post('direct', [DirectPaymentController::class, 'store']);
        Route::get('direct/{orderNumber}', [DirectPaymentController::class, 'show']);
        Route::post('direct/{orderNumber}/confirm-payment', [DirectPaymentController::class, 'confirmPayment']);

        // Authenticated order routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::post('/', [OrderController::class, 'store']);
            Route::get('{orderNumber}', [OrderController::class, 'show']);
            Route::post('{orderNumber}/cancel', [OrderController::class, 'cancel']);
        });
    });

    // =====================================================
    // PAYMENT ROUTES
    // =====================================================
    Route::prefix('payments')->group(function () {
        Route::post('create-form-token', [PaymentController::class, 'createFormToken']);
        Route::post('confirm', [PaymentController::class, 'confirmPayment']);
        Route::post('ipn', [PaymentController::class, 'handleIPN']);
        Route::get('{paymentId}/verify', [PaymentController::class, 'verifyPayment']);
    });

    // =====================================================
    // ADMIN ROUTES (Protected - Admin only)
    // =====================================================
    Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
        // Dashboard
        Route::get('dashboard', [AdminController::class, 'getDashboardStats']);
        
        // Users management
        Route::get('users', [AdminController::class, 'getUsers']);
        Route::get('users/{id}', [AdminController::class, 'getUser']);
        Route::get('users/{id}/orders', [AdminController::class, 'getUserOrders']);
        Route::put('users/{id}/toggle-status', [AdminController::class, 'toggleUserStatus']);
        
        // Flowers management
        Route::post('flowers', [AdminController::class, 'createFlower']);
        Route::put('flowers/{id}', [AdminController::class, 'updateFlower']);
        Route::delete('flowers/{id}', [AdminController::class, 'deleteFlower']);
        
        // Breakfasts management
        Route::post('breakfasts', [AdminController::class, 'createBreakfast']);
        Route::put('breakfasts/{id}', [AdminController::class, 'updateBreakfast']);
        Route::delete('breakfasts/{id}', [AdminController::class, 'deleteBreakfast']);
        
        // Complements management
        Route::post('complements', [AdminController::class, 'createComplement']);
        Route::put('complements/{id}', [AdminController::class, 'updateComplement']);
        Route::delete('complements/{id}', [AdminController::class, 'deleteComplement']);
        
        // Orders management
        Route::get('orders', [AdminController::class, 'getOrders']);
        Route::get('orders/{id}', [AdminController::class, 'getOrder']);
        Route::put('orders/{id}/status', [AdminController::class, 'updateOrderStatus']);
    });
});
