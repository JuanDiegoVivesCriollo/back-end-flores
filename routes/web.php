<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IzipayPopinController;

Route::get('/', function () {
    return response()->json([
        'message' => 'Flores D\' Jazmin API',
        'version' => '1.0.0',
        'status' => 'active',
        'frontend_url' => env('FRONTEND_URL'),
        'environment' => env('APP_ENV')
    ]);
});

// Test route to verify basic routing
Route::get('/test-web', function () {
    return response()->json(['message' => 'Web route working']);
});

// Test API simulation in web routes
Route::get('/test-api-health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now()->toISOString(),
        'environment' => app()->environment(),
        'version' => '1.0.0'
    ]);
});

// Test districts simulation
Route::get('/test-districts', function () {
    return response()->json([
        'success' => true,
        'data' => [
            ['id' => 1, 'name' => 'San Juan de Lurigancho', 'shipping_cost' => 15.00],
            ['id' => 2, 'name' => 'Lima Cercado', 'shipping_cost' => 20.00]
        ]
    ]);
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now(),
        'app_name' => config('app.name'),
        'environment' => config('app.env')
    ]);
});

// Izipay Pop-in Routes
Route::group(['prefix' => 'izipay'], function () {
    Route::get('', [IzipayPopinController::class, 'index'])->name('izipay.index');
    Route::post('checkout', [IzipayPopinController::class, 'checkout'])->name('izipay.checkout');
    Route::post('result', [IzipayPopinController::class, 'result'])->name('izipay.result');
    Route::post('ipn', [IzipayPopinController::class, 'ipn'])->name('izipay.ipn');
});

// Rutas adicionales para el frontend (si es necesario)
Route::group(['prefix' => 'payment'], function() {
    Route::get('success', function() {
        return redirect()->route('izipay.result');
    })->name('payment.success');

    Route::get('error', function() {
        return redirect()->route('izipay.result');
    })->name('payment.error');

    Route::get('cancelled', function() {
        return redirect()->route('izipay.result');
    })->name('payment.cancelled');

    Route::get('refused', function() {
        return redirect()->route('izipay.result');
    })->name('payment.refused');
});
