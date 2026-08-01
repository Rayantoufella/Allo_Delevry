<?php

use App\Http\Controllers\Api\AiRequestDraftController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatMessageController;
use App\Http\Controllers\Api\DeliveryProofController;
use App\Http\Controllers\Api\DeliveryRequestController;
use App\Http\Controllers\Api\DeliveryZoneController;
use App\Http\Controllers\Api\DriverProfileController;
use App\Http\Controllers\Api\GpsLocationController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentTransactionController;
use App\Http\Controllers\Api\RequestStatusHistoryController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('role:driver')->group(function () {
        Route::apiResource('services', ServiceController::class);
        Route::apiResource('delivery-zones', DeliveryZoneController::class);
        Route::patch('/delivery-zones/{deliveryZone}/toggle-active', [DeliveryZoneController::class, 'toggleActive']);
        Route::apiResource('driver-profiles', DriverProfileController::class);
    });

    Route::apiResource('delivery-requests', DeliveryRequestController::class);
    Route::patch('/delivery-requests/{deliveryRequest}/status', [DeliveryRequestController::class, 'updateStatus']);

    Route::apiResource('notifications', NotificationController::class)->only(['index', 'show']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    Route::apiResource('ai-request-drafts', AiRequestDraftController::class);
    Route::apiResource('chat-messages', ChatMessageController::class);
    Route::apiResource('reviews', ReviewController::class);
    Route::apiResource('delivery-proofs', DeliveryProofController::class);
    Route::apiResource('incidents', IncidentController::class);
    Route::apiResource('gps-locations', GpsLocationController::class);
    Route::apiResource('payment-transactions', PaymentTransactionController::class);
    Route::apiResource('request-status-histories', RequestStatusHistoryController::class);
});
