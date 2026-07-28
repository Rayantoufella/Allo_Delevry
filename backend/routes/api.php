<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Driver\DeliveryZoneController;
use App\Http\Controllers\Api\Driver\ServiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [AuthController::class,  'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

Route::middleware(['auth:sanctum', 'role:driver'])
    ->prefix('driver')
    ->group(function () {
        Route::apiResource('services', ServiceController::class);
        Route::apiResource('zones', DeliveryZoneController::class);
    });
