<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => "Flores D'Jazmin API",
        'version' => '1.0.0',
        'documentation' => '/api/v1',
        'health' => '/api/health'
    ]);
});
