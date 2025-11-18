<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WebAuthnController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OptimizedFlowerController;
use App\Http\Controllers\Api\FlowerController;
use App\Http\Controllers\Api\ComplementController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\LandingPageController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Admin\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public health check endpoint (outside of authentication)
Route::get('health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now()->toISOString(),
        'environment' => app()->environment(),
        'version' => '1.0.0'
    ]);
});

// Test endpoint para diagnosticar problemas
Route::get('test-simple', function () {
    return response()->json(['message' => 'Test endpoint working']);
});

Route::get('test-config', function () {
    return response()->json([
        'app_key_exists' => !empty(config('app.key')),
        'app_key_length' => strlen(config('app.key')),
        'environment' => app()->environment()
    ]);
});

Route::get('test-db', function () {
    try {
        $count = \App\Models\Flower::count();
        return response()->json(['flowers_count' => $count]);
    } catch (Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Public routes
Route::prefix('v1')->group(function () {

    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);

        // Rutas de seguridad adicionales (requieren autenticación)
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::post('verify-token', [AuthController::class, 'verifyToken']);
            Route::get('profile', [AuthController::class, 'profile']);
            Route::put('profile', [AuthController::class, 'updateProfile']);
            Route::put('change-password', [AuthController::class, 'changePassword']);
        });

        // Ruta administrativa para limpiar tokens expirados
        Route::post('clean-expired-tokens', [AuthController::class, 'cleanExpiredTokens'])
            ->middleware(['auth:sanctum', 'role:admin']);
    });

    // WebAuthn routes (biometric authentication)
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

    // Public catalog routes
    Route::prefix('catalog')->group(function () {
        // Categories
        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('categories/{id}', [CategoryController::class, 'show']);
        Route::get('categories/{id}/statistics', [CategoryController::class, 'statistics']);

        // Flowers
        Route::get('flowers/featured', [OptimizedFlowerController::class, 'featured']);
        Route::get('flowers/on-sale', [OptimizedFlowerController::class, 'onSale']);
        Route::get('flowers', [OptimizedFlowerController::class, 'index']);
        Route::get('flowers/{id}', [OptimizedFlowerController::class, 'show']);
        Route::get('flowers/category/{categoryId}', [OptimizedFlowerController::class, 'byCategory']);

        // Complements
        Route::get('complements/types', [ComplementController::class, 'getTypes']);
        Route::get('complements/featured', [ComplementController::class, 'featured']);
        Route::get('complements/on-sale', [ComplementController::class, 'onSale']);
        Route::get('complements', [ComplementController::class, 'index']);
        Route::get('complements/{id}', [ComplementController::class, 'show']);
        Route::get('complements/type/{type}', [ComplementController::class, 'byType']);
    });

    // Public delivery routes
    Route::prefix('delivery')->group(function () {
        Route::get('districts', [\App\Http\Controllers\Api\DeliveryController::class, 'getDistricts']);
        Route::get('districts/by-zone', [\App\Http\Controllers\Api\DeliveryController::class, 'getDistrictsByZone']);
        Route::post('shipping-cost', [\App\Http\Controllers\Api\DeliveryController::class, 'getShippingCost']);
        Route::post('check-availability', [\App\Http\Controllers\Api\DeliveryController::class, 'checkAvailability']);
    });

    // Public order tracking
    Route::get('orders/tracking/{orderNumber}', [OrderController::class, 'tracking']);

    // Public order status (for payment success page)
    Route::get('orders/{orderNumber}/status', [OrderController::class, 'getOrderStatus']);

    // Public payment confirmation (for frontend payment success)
    Route::post('orders/confirm-payment', [OrderController::class, 'confirmPayment']);

    // Test endpoint for debugging payment confirmation
    Route::post('orders/test-confirm', function () {
        return response()->json([
            'success' => true,
            'message' => 'Test endpoint working with POST method',
            'timestamp' => now(),
            'method' => request()->method()
        ]);
    });

    // Public order creation (for guest users)
    Route::post('orders', [OrderController::class, 'store']);

    // Payment creation (public for frontend)
    Route::prefix('payment')->group(function () {
        Route::post('create', [PaymentController::class, 'createSessionByOrderNumber']);
    });

    // Landing page content (public read access)
    Route::prefix('landing')->group(function () {
        Route::get('content', [LandingPageController::class, 'index']);
        Route::get('content/{section}', [LandingPageController::class, 'getBySection']);
    });

    // Public statistics for dashboard (limited data)
    Route::prefix('stats')->group(function () {
        Route::get('general', [DashboardController::class, 'publicStats']);
    });

    // RUTAS TEMPORALES para flores sin autenticación
    Route::post('flowers-temp', [FlowerController::class, 'store']);
    Route::put('flowers-temp/{id}', [FlowerController::class, 'update']);
    Route::delete('flowers-temp/{id}', [FlowerController::class, 'destroy']);
});

// Protected routes (require authentication)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // Customer orders (authenticated users only)
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('{id}', [OrderController::class, 'show']);
        Route::patch('{id}/cancel', [OrderController::class, 'cancel']);
    });

    // Payments
    Route::prefix('payment')->group(function () {
        Route::get('token/{orderNumber}', [PaymentController::class, 'getPaymentToken']);
    });

    Route::prefix('payments')->group(function () {
        Route::post('session', [PaymentController::class, 'createSession']);
        Route::get('{id}/verify', [PaymentController::class, 'verifyPayment']);
        Route::get('history', [PaymentController::class, 'history']);
    });

    // User profile management
    Route::prefix('profile')->group(function () {
        Route::get('/', [UserController::class, 'profile']);
        Route::put('/', [UserController::class, 'updateProfile']);
        Route::put('/change-password', [UserController::class, 'changePassword']);
    });
});

// Public webhook routes (no authentication required)
Route::prefix('v1/webhooks')->group(function () {
    Route::post('izipay', [PaymentController::class, 'webhook']);
});

// Admin routes (require authentication and admin role)
Route::prefix('v1/admin')->middleware(['auth:sanctum', 'admin'])->group(function () {

    // Dashboard and analytics
    Route::prefix('dashboard')->group(function () {
        Route::get('overview', [DashboardController::class, 'overview']);
        Route::get('sales-analytics', [DashboardController::class, 'salesAnalytics']);
        Route::get('customer-analytics', [DashboardController::class, 'customerAnalytics']);
        Route::get('inventory-analytics', [DashboardController::class, 'inventoryAnalytics']);
    });

    // Category management
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::get('{id}', [CategoryController::class, 'show']);
        Route::put('{id}', [CategoryController::class, 'update']);
        Route::delete('{id}', [CategoryController::class, 'destroy']);
    });

    // Flower management
    Route::prefix('flowers')->group(function () {
        Route::get('/', [FlowerController::class, 'index']);
        Route::post('/', [FlowerController::class, 'store']);
        Route::get('{id}', [FlowerController::class, 'show']);
        Route::put('{id}', [FlowerController::class, 'update']);
        Route::delete('{id}', [FlowerController::class, 'destroy']);
    });

    // Complement management
    Route::prefix('complements')->group(function () {
        Route::get('/', [ComplementController::class, 'index']);
        Route::post('/', [ComplementController::class, 'store']);
        Route::get('{id}', [ComplementController::class, 'show']);
        Route::put('{id}', [ComplementController::class, 'update']);
        Route::delete('{id}', [ComplementController::class, 'destroy']);
    });

    // Order management
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'getAllOrders']); // Admin: todos los pedidos
        Route::get('{id}', [OrderController::class, 'show']);
        Route::patch('{id}/status', [OrderController::class, 'updateStatus']);
        Route::patch('{id}/payment-status', [OrderController::class, 'updatePaymentStatus']);
        Route::delete('{id}', [OrderController::class, 'destroy']); // Admin: eliminar pedido específico
        Route::delete('/', [OrderController::class, 'destroyAll']); // Admin: eliminar todos los pedidos
        Route::post('delete-multiple', [OrderController::class, 'destroyMultiple']); // Admin: eliminar pedidos seleccionados
    });

    // Image management
    Route::prefix('images')->group(function () {
        Route::post('upload', [App\Http\Controllers\Api\ImageController::class, 'upload']);
        Route::delete('delete', [App\Http\Controllers\Api\ImageController::class, 'delete']);
        Route::get('info', [App\Http\Controllers\Api\ImageController::class, 'info']);
    });

    // Landing page content management (admin only)
    Route::prefix('landing')->group(function () {
        Route::put('content', [LandingPageController::class, 'update']);
        Route::put('content/bulk', [LandingPageController::class, 'updateBulk']);
    });

    // User management (admin only)
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('statistics', [UserController::class, 'statistics']);
        Route::get('{id}', [UserController::class, 'show']);
        Route::get('{id}/orders', [UserController::class, 'getUserOrders']);
        Route::patch('{id}/status', [UserController::class, 'updateStatus']);
    });
});

// Storage image serving route
Route::get('v1/storage/{path}', function ($path) {
    // Capturar cualquier subruta después de storage/
    $fullPath = 'app/public/' . $path;
    $absolutePath = storage_path($fullPath);

    if (!file_exists($absolutePath)) {
        return response()->json([
            'success' => false,
            'message' => 'Image not found',
            'path_attempted' => $absolutePath
        ], 404);
    }

    $mimeType = mime_content_type($absolutePath);
    return response()->file($absolutePath, [
        'Content-Type' => $mimeType,
        'Access-Control-Allow-Origin' => '*'
    ]);
})->where('path', '.*'); // Permitir cualquier caracter en path incluyendo slashes});

// Rutas específicas para el frontend con estructura /public/api/v1/ (compatibilidad)
Route::prefix('public/api/v1')->group(function () {
    // Payment confirmation route específica para iZiPay
    Route::post('orders/confirm-payment', [OrderController::class, 'confirmPayment']);

    // Test endpoint for debugging
    Route::post('orders/test-confirm', function () {
        return response()->json([
            'success' => true,
            'message' => 'Test endpoint working with POST method via public/api/v1',
            'timestamp' => now(),
            'method' => request()->method(),
            'url' => request()->fullUrl()
        ]);
    });

    // Other critical endpoints that might be needed
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{orderNumber}/status', [OrderController::class, 'getOrderStatus']);
});

// Fallback route for API
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found'
    ], 404);
});
