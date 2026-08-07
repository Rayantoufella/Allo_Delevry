<?php

use App\Http\Controllers\Api\AiRequestDraftController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatMessageController;
use App\Http\Controllers\Api\DashboardController;
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
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::get('/drivers/{slug}', [DriverProfileController::class, 'showPublic'])->middleware('throttle:60,1');
Route::get('/drivers/{slug}/qr', [DriverProfileController::class, 'qrCode'])->middleware('throttle:60,1');

Route::get('/tracking/{privateToken}', [DeliveryRequestController::class, 'tracking'])->middleware('throttle:60,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/drivers/{slug}/delivery-requests', [DeliveryRequestController::class, 'storeForDriver']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('role:driver')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::apiResource('services', ServiceController::class);
        Route::apiResource('delivery-zones', DeliveryZoneController::class);
        Route::patch('/delivery-zones/{deliveryZone}/toggle-active', [DeliveryZoneController::class, 'toggleActive']);
        Route::apiResource('driver-profiles', DriverProfileController::class);
    });

    Route::apiResource('delivery-requests', DeliveryRequestController::class)->except(['store']);
    Route::patch('/delivery-requests/{deliveryRequest}/status', [DeliveryRequestController::class, 'updateStatus']);
    Route::post('/delivery-requests/{deliveryRequest}/confirm-price', [DeliveryRequestController::class, 'confirmPrice']);
    Route::post('/delivery-requests/{deliveryRequest}/cancel', [DeliveryRequestController::class, 'cancel']);
    Route::post('/delivery-requests/{deliveryRequest}/generate-code', [DeliveryRequestController::class, 'generateCode']);
    Route::post('/delivery-requests/{deliveryRequest}/confirm-delivery', [DeliveryRequestController::class, 'confirmDelivery']);

    Route::apiResource('notifications', NotificationController::class)->only(['index', 'show']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    Route::apiResource('ai-request-drafts', AiRequestDraftController::class);
    Route::post('/ai-request-drafts/analyze', [AiRequestDraftController::class, 'analyze'])->middleware('throttle:10,1');
    Route::apiResource('chat-messages', ChatMessageController::class);
    Route::apiResource('reviews', ReviewController::class);
    Route::apiResource('delivery-proofs', DeliveryProofController::class);
    Route::apiResource('incidents', IncidentController::class);
    Route::apiResource('gps-locations', GpsLocationController::class);
    Route::apiResource('payment-transactions', PaymentTransactionController::class);
    Route::apiResource('request-status-histories', RequestStatusHistoryController::class)->only(['index', 'show']);
});
